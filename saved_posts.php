<?php
session_start();

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// DB connection
require 'connect.php';

$user_id = $_SESSION['user_id'];

// Example: Get dark mode value from DB or session (adjust to your logic)
$dark_mode = false;

// If you store it in session:
if (isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] == 1) {
    $dark_mode = true;
}

// Or get from DB:
$query_mode = "SELECT dark_mode FROM users WHERE user_id = ?";
$stmt_mode = $con->prepare($query_mode);
$stmt_mode->bind_param("i", $user_id);
$stmt_mode->execute();
$result_mode = $stmt_mode->get_result();
if ($row_mode = $result_mode->fetch_assoc()) {
    $dark_mode = ($row_mode['dark_mode'] == 1);
}

// Fetch saved posts
$query = "
    SELECT p.*
    FROM saves s
    JOIN post p ON s.post_id = p.id
    WHERE s.user_id = ?
    ORDER BY s.saved_at DESC
";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Saved Posts</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 2rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      background: #f4f4f4;
      color: #222;
      transition: background 0.3s, color 0.3s;
    }

    .dark {
      background: #121212;
      color: #ddd;
    }

    .post {
      background: #fff;
      padding: 1rem;
      margin-bottom: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 600px;
      box-sizing: border-box;
      transition: background 0.3s, color 0.3s;
    }

    .dark .post {
      background: #1e1e1e;
      color: #ddd;
    }

    .post p {
      margin-bottom: 1rem;
      word-wrap: break-word;
    }

    .post img,
    .post video {
      width: 100%;
      max-height: 400px;
      object-fit: cover;
      border-radius: 4px;
      display: block;
      margin: 0 auto 1rem;
    }

    small {
      color: #666;
    }

    .dark small {
      color: #aaa;
    }
.back-btn{
  display:inline-block;
  margin-bottom:1rem;
  background:#6a1b9a;
  color:#fff;
  padding:6px 14px;
  border-radius:22px;
  font-weight:500;
  text-decoration:none;
  box-shadow:0 2px 5px rgba(0,0,0,.15);
  transition:transform .15s;
}
.back-btn:hover{transform:scale(1.05);}
/* same rules you used on liked_posts.php */
.posts{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
  gap:10px;
}
.tile{position:relative;width:100%;padding-top:100%;border-radius:12px;overflow:hidden;background:#ccc;cursor:pointer}
.tile img,.tile video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.caption{position:absolute;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);color:#fff;font-size:.75rem;padding:4px 6px;text-align:center}
/* put this after the existing .posts rule */

/* 1️⃣ let the grid grow full width inside the flex column */
.posts{
  align-self:stretch;   /* overrides body’s align-items:center */
  width:100%;
  max-width:1000px;     /* optional – cap the width if you like */
}

/* 2️⃣ centre the whole block again when viewport is wider than the cap */
@media (min-width:1000px){
  .posts{margin-inline:auto;}
}

  </style>
</head>
<body class="<?= $dark_mode ? 'dark' : '' ?>">


  <h1>Your Saved Posts</h1>
<!-- back button -->
<a href="settings_frontend.php" class="back-btn">← Settings</a>
  <?php if ($result->num_rows): ?>
  <div class="posts">
    <?php while ($row = $result->fetch_assoc()): 
          $media = $row['post_img'] ?: $row['post_video'];
          $isVid = !empty($row['post_video']); ?>
      <div>
        <div class="tile" onclick="showFullImageFromPath('<?= htmlspecialchars($media) ?>')">
          <?php if ($isVid): ?>
            <video src="<?= htmlspecialchars($media) ?>" muted></video>
          <?php elseif ($media): ?>
            <img src="<?= htmlspecialchars($media) ?>" alt="post">
          <?php else: /* text‑only post → simple coloured box */ ?>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#bdbdbd;color:#333;padding:6px;text-align:center">
              <?= htmlspecialchars(mb_strimwidth($row['post_text'],0,60,'…')) ?>
            </div>
          <?php endif; ?>

          <?php if(!empty($row['post_text'])): ?>
            <div class="caption"><?= htmlspecialchars($row['post_text']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php else: ?>
  <p>No saved posts yet.</p>
<?php endif; ?>

</body>
</html>
