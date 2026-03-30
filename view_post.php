<?php
/**
 * view_post.php
 * Shows a post. Supports:
 *   ?back=            — back button URL (returns to chat or wherever)
 *   ?highlight_comment=ID — scrolls to and highlights that comment
 *   ?actor_id=        — shows who interacted
 *   ?action=          — like | comment | share etc.
 */
session_start();
require 'connect.php';
require 'getdark_mode.php';

if (!isset($_GET['post_id'])) { echo "No post specified."; exit(); }

$post_id           = (int)$_GET['post_id'];
$actor_id          = isset($_GET['actor_id'])   ? (int)$_GET['actor_id']   : null;
$action            = strtolower($_GET['action']  ?? '');
$highlight_comment = isset($_GET['highlight_comment']) ? (int)$_GET['highlight_comment'] : 0;

/* Safe back URL */
$back_url = '';
if (!empty($_GET['back'])) {
    $decoded = urldecode($_GET['back']);
    $parsed  = parse_url($decoded);
    $host    = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($parsed['host']) || $parsed['host'] === $host)
        $back_url = htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
}

/* Fetch post */
$stmt = $con->prepare("
    SELECT p.*, u.user_name AS owner_name, u.profile_image AS owner_image, u.user_id AS owner_id
    FROM post p JOIN users u ON p.user_id = u.user_id WHERE p.id = ?
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { echo "Post not found."; exit(); }
$post = $result->fetch_assoc();
$stmt->close();

/* Fetch actor */
$actor = null; $actionText = null; $commentText = null;
if ($actor_id && $action) {
    $stmtActor = $con->prepare("SELECT user_name, profile_image FROM users WHERE user_id = ?");
    $stmtActor->bind_param("i", $actor_id);
    $stmtActor->execute();
    $actorResult = $stmtActor->get_result();
    if ($actorResult->num_rows > 0) $actor = $actorResult->fetch_assoc();

    if ($action === 'liked' || $action === 'like') {
        $actionText = "@{$actor['user_name']} liked your post";
    } elseif (in_array($action, ['comment','commented'])) {
        $stmtComment = $con->prepare("SELECT comment_text, commented_at FROM comments WHERE post_id=? AND user_id=? ORDER BY commented_at DESC LIMIT 1");
        $stmtComment->bind_param("ii", $post_id, $actor_id);
        $stmtComment->execute();
        $resComment = $stmtComment->get_result();
        if ($resComment->num_rows > 0) {
            $comment     = $resComment->fetch_assoc();
            $commentText = $comment['comment_text'];
            $time        = $comment['commented_at'] ? date("d M Y, h:i A", strtotime($comment['commented_at'])) : '';
            $actionText  = "@{$actor['user_name']} commented" . ($time ? " on $time" : "") . ":";
        }
    } elseif ($action === 'follow') {
        $actionText = "@{$actor['user_name']} started following you";
    } elseif ($action === 'save') {
        $actionText = "@{$actor['user_name']} saved your post";
    } elseif ($action === 'share') {
        $actionText = "@{$actor['user_name']} shared your post";
    }
}

/* Fetch comments for this post (needed to highlight one) */
$comments = [];
if ($highlight_comment) {
    $cStmt = $con->prepare("
        SELECT c.id AS cid, c.comment_text, c.commented_at, c.user_id,
               u.user_name, u.profile_image
        FROM   comments c
        JOIN   users    u ON u.user_id = c.user_id
        WHERE  c.post_id = ?
        ORDER  BY c.commented_at ASC
    ");
    $cStmt->bind_param('i', $post_id);
    $cStmt->execute();
    $comments = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Post | Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{
      font-family:'Poppins',sans-serif;
      min-height:100vh;
      background:<?= $dark_mode?'#111':'linear-gradient(135deg,#fbc2eb 0%,#a6c1ee 100%)'?>;
      color:<?= $dark_mode?'#eee':'#333'?>;
      padding:20px;
    }

    /* Back button */
    .back-btn{
      display:block;width:fit-content;
      margin:0 auto 16px;
      background:rgba(255,255,255,.9);border:none;border-radius:10px;
      padding:8px 16px;font-size:.88rem;font-weight:600;
      color:#6a1b9a;cursor:pointer;text-decoration:none;
      box-shadow:0 2px 8px rgba(0,0,0,.12);transition:background .18s,transform .15s;
    }
    .back-btn:hover{background:#fff;transform:translateY(-1px);}

    /* Post card */
    .post{
      background:<?= $dark_mode?'#1e1e1e':'white'?>;
      border-radius:16px;padding:20px;
      max-width:600px;width:100%;margin:auto;
      box-shadow:0 5px 20px rgba(0,0,0,<?= $dark_mode?.3:.1?>);
    }

    /* Action header (who did what) */
    .action-header{
      display:flex;align-items:center;gap:10px;
      margin-bottom:12px;padding-bottom:12px;
      border-bottom:1px solid <?= $dark_mode?'#2a2a2a':'#eee'?>;
    }
    .action-header img{width:38px;height:38px;border-radius:50%;object-fit:cover;}
    .action-text{font-size:.87rem;font-weight:600;color:<?= $dark_mode?'#bb86fc':'#6a1b9a'?>;}
    .comment-quote{
      margin-top:6px;background:<?= $dark_mode?'#2a2a2a':'#f5f5f5'?>;
      padding:8px 12px;border-radius:8px;font-size:.83rem;
      border-left:3px solid #9b5de5;
      color:<?= $dark_mode?'#ccc':'#555'?>;
    }

    /* Author row — clickable */
    .author-link{
      display:flex;align-items:center;gap:10px;
      text-decoration:none;color:inherit;
      border-radius:8px;padding:4px 6px;transition:background .15s;
      margin-bottom:10px;
    }
    .author-link:hover{background:<?= $dark_mode?'rgba(155,93,229,.1)':'rgba(106,27,154,.06)'?>;}
    .author-link img{width:40px;height:40px;border-radius:50%;object-fit:cover;}
    .author-link strong{color:<?= $dark_mode?'#bb86fc':'#6a1b9a'?>;}

    .post-content{font-size:1rem;margin-top:10px;word-wrap:break-word;line-height:1.6;}
    img.post-media,video.post-media{max-width:100%;width:100%;border-radius:10px;display:block;margin-top:10px;}

    /* Comments section */
    .comments-section{margin-top:20px;}
    .comments-title{
      font-size:.85rem;font-weight:700;
      color:<?= $dark_mode?'#888':'#999'?>;
      text-transform:uppercase;letter-spacing:.5px;
      margin-bottom:12px;
    }
    .comment-item{
      display:flex;align-items:flex-start;gap:10px;
      padding:10px 12px;border-radius:12px;margin-bottom:6px;
      background:<?= $dark_mode?'#252525':'#f8f8f8'?>;
      transition:background .3s,box-shadow .3s;
    }
    /* ★ Highlighted comment */
    .comment-item.highlighted{
      background:<?= $dark_mode?'rgba(155,93,229,.2)':'rgba(155,93,229,.12)'?> !important;
      box-shadow:0 0 0 2px #9b5de5;
      animation:pulseHighlight 1.5s ease 0.5s 2;
    }
    @keyframes pulseHighlight{
      0%,100%{box-shadow:0 0 0 2px #9b5de5;}
      50%{box-shadow:0 0 0 5px rgba(155,93,229,.3);}
    }
    .comment-item img{width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;}
    .comment-body{flex:1;}
    .comment-author-name{font-weight:700;font-size:.82rem;color:<?= $dark_mode?'#bb86fc':'#6a1b9a'?>;}
    .comment-text{font-size:.85rem;margin-top:2px;line-height:1.45;color:<?= $dark_mode?'#ddd':'#333'?>;}
    .comment-time{font-size:.7rem;color:<?= $dark_mode?'#666':'#bbb'?>;margin-top:3px;}
  </style>
</head>
<body>

  <?php if ($back_url): ?>
    <a href="<?= $back_url ?>" class="back-btn">← Back</a>
  <?php else: ?>
    <button class="back-btn" onclick="history.back()">← Back</button>
  <?php endif; ?>

  <div class="post">

    <!-- Who interacted -->
    <?php if ($actor && $actionText): ?>
      <div class="action-header">
        <img src="<?= htmlspecialchars($actor['profile_image'] ?: 'uploads/default-profile.png') ?>" alt="">
        <div>
          <div class="action-text"><?= htmlspecialchars($actionText) ?></div>
          <?php if (in_array($action, ['comment','commented']) && $commentText): ?>
            <div class="comment-quote"><?= nl2br(htmlspecialchars($commentText)) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Post author — clickable → their profile -->
    <a href="public_profile.php?user_id=<?= (int)$post['owner_id'] ?>&back=<?= urlencode($back_url ?: $_SERVER['REQUEST_URI']) ?>"
       class="author-link">
      <img src="<?= htmlspecialchars($post['owner_image'] ?: 'uploads/default-profile.png') ?>" alt="">
      <strong>@<?= htmlspecialchars($post['owner_name']) ?></strong>
    </a>

    <!-- Media -->
    <?php if (!empty($post['post_img'])): ?>
      <img class="post-media" src="<?= htmlspecialchars($post['post_img']) ?>" alt="Post Image">
    <?php elseif (!empty($post['post_video'])): ?>
      <video class="post-media" controls>
        <source src="<?= htmlspecialchars($post['post_video']) ?>" type="video/mp4">
      </video>
    <?php endif; ?>

    <!-- Caption -->
    <?php if (!empty($post['post_text'])): ?>
      <div class="post-content"><?= nl2br(htmlspecialchars($post['post_text'])) ?></div>
    <?php endif; ?>

    <!-- Comments (only loaded when highlight_comment is set) -->
    <?php if ($highlight_comment && !empty($comments)): ?>
      <div class="comments-section">
        <div class="comments-title">💬 Comments</div>
        <?php foreach ($comments as $c):
          $isHighlighted = ($c['cid'] === $highlight_comment);
          $cAvatar = htmlspecialchars($c['profile_image'] ?: 'uploads/default-profile.png');
          $cName   = htmlspecialchars($c['user_name']);
          $cText   = nl2br(htmlspecialchars($c['comment_text']));
          $cTime   = date('d M Y, h:i A', strtotime($c['commented_at']));
        ?>
          <div class="comment-item <?= $isHighlighted ? 'highlighted' : '' ?>"
               id="comment-<?= $c['cid'] ?>">
            <img src="<?= $cAvatar ?>" alt="">
            <div class="comment-body">
              <div class="comment-author-name">@<?= $cName ?></div>
              <div class="comment-text"><?= $cText ?></div>
              <div class="comment-time"><?= $cTime ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

  <?php if ($highlight_comment): ?>
  <script>
    // Scroll to the highlighted comment after page loads
    window.addEventListener('load', () => {
      const el = document.getElementById('comment-<?= $highlight_comment ?>');
      if (el) {
        setTimeout(() => {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 400);
      }
    });
  </script>
  <?php endif; ?>

</body>
</html>