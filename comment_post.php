<?php
/* comment_post.php — with text moderation */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Please log in']);
    exit;
}

require 'connect.php';
require_once 'content_moderation.php';
require_once __DIR__ . '/lib/push_notification.php';

$user_id = (int)$_SESSION['user_id'];
$post_id = (int)($_POST['post_id'] ?? 0);
$comment = trim($_POST['comment']  ?? '');

if (!$post_id || $comment === '') {
    echo json_encode(['status'=>'error','msg'=>'Invalid data']);
    exit;
}

/* ── TEXT MODERATION ── */
$textCheck = moderateText($comment);
if (!$textCheck['ok']) {
    echo json_encode(['status'=>'error','msg'=>$textCheck['reason']]);
    exit;
}

/* Insert */
$stmt = $con->prepare("INSERT INTO comments (post_id, user_id, comment_text) VALUES (?,?,?)");
$stmt->bind_param('iis', $post_id, $user_id, $comment);
if (!$stmt->execute()) {
    echo json_encode(['status'=>'error','msg'=>'Database error']);
    exit;
}
$new_id = (int)$stmt->insert_id;
$stmt->close();

/* Commenter info */
$uq = $con->prepare("SELECT user_name, profile_image FROM users WHERE user_id = ?");
$uq->bind_param('i', $user_id);
$uq->execute();
$author = $uq->get_result()->fetch_assoc();
$uq->close();

$avatar      = htmlspecialchars($author['profile_image'] ?: 'default_profile.png', ENT_QUOTES, 'UTF-8');
$name        = htmlspecialchars($author['user_name'], ENT_QUOTES, 'UTF-8');
$time        = date('d M Y, h:i A');
$safeComment = nl2br(htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'));
$link        = 'myprofile_frontend.php';

$html = '
<div class="comment d-flex mb-2 align-items-start" data-comment-id="' . $new_id . '">
  <a href="' . $link . '" class="me-2 comment-author">
    <img src="' . $avatar . '" alt="" class="c-avatar">
  </a>
  <div class="flex-grow-1 c-body">
    <a href="' . $link . '" class="comment-author"><strong>@' . $name . '</strong></a>
    ' . $safeComment . '<br>
    <small class="text-muted">' . $time . '</small>
  </div>
  <button class="delete-comment btn btn-sm btn-link text-danger p-0 ms-2"
          title="Delete" data-comment-id="' . $new_id . '">
    <i class="fas fa-trash-alt"></i>
  </button>
</div>';

/* Notification */
$ownerQ = $con->prepare("SELECT user_id FROM post WHERE id = ?");
$ownerQ->bind_param('i', $post_id);
$ownerQ->execute();
$owner = $ownerQ->get_result()->fetch_assoc();
$ownerQ->close();

if ($owner && (int)$owner['user_id'] !== $user_id) {
    $actorName = $_SESSION['user_name'] ?? 'Someone';
    pushNotification($con, (int)$owner['user_id'], $user_id, 'comment', $post_id, $new_id, "$actorName commented on your post");
}

echo json_encode(['status'=>'success','html'=>$html]);
exit;