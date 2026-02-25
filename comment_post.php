<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Please log in']);
    exit;
}

require 'connect.php';
require_once __DIR__ . '/lib/push_notification.php'; // ✅ ADD THIS

$user_id = $_SESSION['user_id'];
$post_id = (int)($_POST['post_id']   ?? 0);
$comment = trim($_POST['comment']    ?? '');

if (!$post_id || $comment === '') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid data']);
    exit;
}

/* 1. Insert the comment ------------------------------------------------ */
$stmt = $con->prepare(
    "INSERT INTO comments (post_id, user_id, comment_text) VALUES (?,?,?)"
);
$stmt->bind_param('iis', $post_id, $user_id, $comment);

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'msg' => 'Database error']);
    exit;
}

$comment_id = $stmt->insert_id; // ✅ use this for the notification later

/* 2. Fetch commenter info --------------------------------------------- */
$userQ = $con->prepare(
    "SELECT user_name, profile_image FROM users WHERE user_id=?"
);
$userQ->bind_param('i', $user_id);
$userQ->execute();
$author = $userQ->get_result()->fetch_assoc();

/* 3. Build the HTML snippet ------------------------------------------- */
$avatar = $author['profile_image'] ?: 'default_profile.png';
$name   = htmlspecialchars($author['user_name'], ENT_QUOTES, 'UTF-8');
$time   = date('d M Y, h:i A');

$link = 'myprofile_frontend.php';  // commenter == current user

$safeComment = nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'));

$snippet = <<<HTML
<div class="comment" data-comment-id="$comment_id">

  <a href="$link" class="comment-author">
    <img src="$avatar" alt="" class="c-avatar">
  </a>
  <div class="c-body">
    <a href="$link" class="comment-author"><strong>@$name</strong></a>
    $safeComment<br>
    <small class="text-muted">$time</small>
<button class="delete-comment-btn" style="border:none; background:none; color:red; cursor:pointer;">🗑️</button>

  </div>
</div>
HTML;

/* 4. 🔔 Notification -------------------------------------------------- */
$postOwnerStmt = $con->prepare("SELECT user_id FROM post WHERE id = ?");
$postOwnerStmt->bind_param("i", $post_id);
$postOwnerStmt->execute();
$ownerResult = $postOwnerStmt->get_result();
$owner = $ownerResult->fetch_assoc();

if ($owner && $owner['user_id'] != $user_id) {
    $actorName = $_SESSION['user_name'] ?? 'Someone';
    pushNotification(
        $con,
        $owner['user_id'],     // recipient
        $user_id,              // actor
        'comment',             // type
        $post_id,
        $comment_id,
        "$actorName commented on your post"
    );
}

/* 5. Return JSON and stop --------------------------------------------- */
echo json_encode(['status' => 'success', 'html' => $snippet]);
exit;