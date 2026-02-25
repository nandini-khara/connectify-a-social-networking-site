<?php
session_start();
require 'connect.php';
require 'getdark_mode.php';

if (!isset($_GET['post_id'])) {
    echo "No post specified.";
    exit();
}

$post_id = intval($_GET['post_id']);
$actor_id = isset($_GET['actor_id']) ? intval($_GET['actor_id']) : null;
$action = strtolower($_GET['action'] ?? '');



// Fetch post
$stmt = $con->prepare("
    SELECT p.*, u.user_name AS owner_name, u.profile_image AS owner_image
    FROM post p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Post not found.";
    exit();
}
$post = $result->fetch_assoc();

// Fetch actor details and interaction
$actor = null;
$actionText = null;
$commentText = null;

if ($actor_id && $action) {
    $stmtActor = $con->prepare("SELECT user_name, profile_image FROM users WHERE user_id = ?");
    $stmtActor->bind_param("i", $actor_id);
    $stmtActor->execute();
    $actorResult = $stmtActor->get_result();
    if ($actorResult->num_rows > 0) {
        $actor = $actorResult->fetch_assoc();
    }

    if ($action === 'liked') {
        // No created_at in likes table, so just verify like exists
        $stmtLike = $con->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
        $stmtLike->bind_param("ii", $post_id, $actor_id);
        $stmtLike->execute();
        $resLike = $stmtLike->get_result();
        if ($resLike->num_rows > 0 && $actor) {
            $actionText = "@{$actor['user_name']} liked your post";
        }
    } elseif (in_array($action, ['comment', 'commented'])) {
        // Only comments table has commented_at
        $stmtComment = $con->prepare("SELECT comment_text, commented_at FROM comments WHERE post_id = ? AND user_id = ? ORDER BY commented_at DESC LIMIT 1");
        $stmtComment->bind_param("ii", $post_id, $actor_id);
        $stmtComment->execute();
        $resComment = $stmtComment->get_result();
        if ($resComment->num_rows > 0) {
            $comment = $resComment->fetch_assoc();
            $commentText = $comment['comment_text'];
            $time = $comment['commented_at'] ? date("d M Y, h:i A", strtotime($comment['commented_at'])) : '';
            $actionText = "@{$actor['user_name']} commented" . ($time ? " on $time" : "") . ":";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Post</title>
  <style>
  * {
    box-sizing: border-box;
  }

  body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
    min-height: 100vh;
    background: linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%);
  }

  .post {
    background: white;
    border-radius: 12px;
    padding: 20px;
    max-width: 600px;
    width: 100%;
    margin: auto;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
  }

  .post-header,
  .action-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
  }

  .post-header img,
  .action-header img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
  }

  .post-content {
    font-size: 1rem;
    margin-top: 10px;
    word-wrap: break-word;
  }

  .comment-bubble {
    margin-top: 5px;
    background: #f0f0f0;
    padding: 8px;
    border-radius: 6px;
    word-wrap: break-word;
  }

  img,
  video {
    max-width: 100%;
    width: 100%;
    border-radius: 10px;
    display: block;
    margin-top: 10px;
  }
</style>


</head>
<body>

<div class="post">

  <!-- Show what actor did -->
  <?php if ($actor && $actionText): ?>
    <div class="action-header">
      <img src="<?= htmlspecialchars($actor['profile_image'] ?: 'uploads/default-profile.png') ?>" alt="Actor">
      <div>
        <strong><?= htmlspecialchars($actionText) ?></strong>
        <?php if (in_array($action, ['comment', 'commented']) && $commentText): ?>
          <div class="comment-bubble"><?= nl2br(htmlspecialchars($commentText)) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <hr>
  <?php endif; ?>

  <!-- Show the actual post -->
  <div class="post-header">
    <img src="<?= htmlspecialchars($post['owner_image'] ?: 'uploads/default-profile.png') ?>" alt="Post Owner">
    <strong>@<?= htmlspecialchars($post['owner_name']) ?></strong>
  </div>

  <?php if (!empty($post['post_img'])): ?>
    <img src="<?= htmlspecialchars($post['post_img']) ?>" alt="Post Image">
  <?php elseif (!empty($post['post_video'])): ?>
    <video controls style="max-width:100%; border-radius:10px;">
      <source src="<?= htmlspecialchars($post['post_video']) ?>" type="video/mp4">
      Your browser does not support the video tag.
    </video>
  <?php endif; ?>

  <?php if (!empty($post['post_text'])): ?>
    <div class="post-content">
      <?= nl2br(htmlspecialchars($post['post_text'])) ?>
    </div>
  <?php endif; ?>

</div>

</body>
</html>  