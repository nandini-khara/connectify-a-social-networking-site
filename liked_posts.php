<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

require 'connect.php';
$uid = $_SESSION['user_id'];

/* 1. grab every post the user has liked, newest first */
$sql = "
  SELECT p.id           AS post_id,
         p.post_img,
         p.post_video,
         p.post_text,
         p.created_at
  FROM   likes   l
  JOIN   post    p ON p.id = l.post_id
  WHERE  l.user_id = ?
  ORDER  BY l.id DESC        /* or p.created_at DESC */
";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<?php include 'getdark_mode.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Liked Posts | Connectify</title>
<link rel="stylesheet" href="profile.css"><!-- already contains grid styles -->
<style>
/* make it look like your grid pages */
body{font-family:Poppins,sans-serif;background:#f9f9f9;padding:1.5rem}
h2{color:#6a1b9a;margin-bottom:1rem;font-weight:600}


.container{max-width:900px;margin:auto}
/* ───── uniform square tiles & responsive 4‑up grid ───── */
.posts{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); /* 4 on desktop, 3/2/1 on narrow */
  gap:10px;
}

.post{
  position:relative;
  width:100%;
  padding-top:100%;     /* 1 : 1 aspect ratio – height = width */
  overflow:hidden;
  border-radius:12px;
  background:#ccc;
  cursor:pointer;
}

.post img,
.post video{
  position:absolute;    /* stretch to fill the square */
  inset:0;
  width:100%;
  height:100%;
  object-fit:cover;     /* no distortion */
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

</style>
</head>
<body>
<!-- back button -->
<a href="settings_frontend.php" class="back-btn">← Settings</a>
<div class="container">
  <h2>Your liked posts</h2>

  <?php if (!$posts): ?>
    <p>No likes yet 👀</p>
  <?php else: ?>
    <div class="posts">
      <?php foreach ($posts as $p): 
            $media = $p['post_img'] ?: $p['post_video'];
            $isVid = !empty($p['post_video']); ?>
        <div>
          <div class="post" onclick="showFullImageFromPath('<?= htmlspecialchars($media) ?>')">
            <?php if ($isVid): ?>
              <video src="<?= htmlspecialchars($media) ?>" muted></video>
            <?php else: ?>
              <img src="<?= htmlspecialchars($media) ?>" alt="post">
            <?php endif; ?>
            <?php if(!empty($p['post_text'])): ?>
              <div class="caption"><?= htmlspecialchars($p['post_text']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- reuse your fullscreen popup script if you like -->
<script>
function showFullImageFromPath(path){
  // … paste the same popup code you use elsewhere …
}
</script>
</body>
</html>
