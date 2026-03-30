<?php
/* load_comments.php */
session_start();
require 'connect.php';

if (!isset($_POST['post_id'])) { http_response_code(400); exit('No post ID'); }

$post_id = (int)$_POST['post_id'];
$user_id = (int)($_SESSION['user_id'] ?? 0);

/* Who owns the post? */
$postQ = $con->prepare("SELECT user_id FROM post WHERE id = ? LIMIT 1");
$postQ->bind_param('i', $post_id);
$postQ->execute();
$postOwnerId = (int)$postQ->get_result()->fetch_column();
$postQ->close();

/* Fetch comments — PK column is `id` */
$stmt = $con->prepare("
    SELECT c.id          AS cid,
           c.comment_text,
           c.commented_at,
           c.user_id,
           u.user_name,
           u.profile_image
    FROM   comments c
    JOIN   users    u ON u.user_id = c.user_id
    WHERE  c.post_id = ?
    ORDER  BY c.commented_at ASC
");
$stmt->bind_param('i', $post_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

while ($c = $result->fetch_assoc()) {
    $cid      = (int)$c['cid'];          // aliased so no ambiguity
    $isAuthor = ($user_id === (int)$c['user_id']);
    $isOwner  = ($user_id === $postOwnerId);
    $canDel   = ($isAuthor || $isOwner);
    $avatar   = htmlspecialchars($c['profile_image'] ?: 'uploads/default-profile.png', ENT_QUOTES, 'UTF-8');
    $name     = htmlspecialchars($c['user_name'], ENT_QUOTES, 'UTF-8');
    $text     = nl2br(htmlspecialchars($c['comment_text'], ENT_QUOTES, 'UTF-8'));
    $time     = date('d M Y, h:i A', strtotime($c['commented_at']));
    $link     = $isAuthor
        ? 'myprofile_frontend.php'
        : 'public_profile.php?user_id=' . (int)$c['user_id'];

    echo '
    <div class="comment d-flex mb-2 align-items-start" data-comment-id="' . $cid . '">
      <a href="' . $link . '" class="me-2">
        <img src="' . $avatar . '" class="c-avatar" alt="">
      </a>
      <div class="flex-grow-1 c-body">
        <a href="' . $link . '"><strong>@' . $name . '</strong></a>
        ' . $text . '<br>
        <small class="text-muted">' . $time . '</small>
      </div>';

    if ($canDel) {
        echo '
      <button class="delete-comment btn btn-sm btn-link text-danger p-0 ms-2"
              title="Delete"
              data-comment-id="' . $cid . '">
        <i class="fas fa-trash-alt"></i>
      </button>';
    }

    echo '
    </div>';
}