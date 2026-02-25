<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

require 'connect.php';
$uid = $_SESSION['user_id'];

/*  grab every comment the user wrote, newest first,
    plus the post it belongs to  */
$sql = "
  SELECT c.comment_text,
         c.commented_at        AS comment_time,
         p.id                AS post_id,
         p.post_img,
         p.post_video,
         p.post_text
  FROM   comments c
  JOIN   post     p ON p.id = c.post_id
  WHERE  c.user_id = ?
  ORDER  BY c.id DESC           /* newest comment first */
";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<?php include 'getdark_mode.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your Comments | Connectify</title>
<link rel="stylesheet" href="profile.css"> <!-- contains square‑tile grid -->
<style>
body{font-family:Poppins,sans-serif;background:#f9f9f9;padding:1.5rem}
h2{color:#6a1b9a;margin-bottom:1rem;font-weight:600}
.back-btn{display:inline-block;margin-bottom:1rem;background:#6a1b9a;color:#fff;
          padding:6px 14px;border-radius:22px;font-weight:500;text-decoration:none}
.back-btn:hover{transform:scale(1.05);}
.posts{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.tile{position:relative;width:100%;padding-top:100%;border-radius:12px;overflow:hidden;
      background:#ccc;cursor:pointer}
.tile img,.tile video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.overlay{position:absolute;inset:0;background:rgba(0,0,0,.55);color:#fff;
         display:flex;flex-direction:column;justify-content:flex-end;font-size:.78rem;
         padding:6px}
.overlay p{margin:0 0 4px 0;font-size:.8rem;max-height:40%;overflow:hidden}

/* text‑only post placeholder */
.no-media{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
          background:#bdbdbd;color:#333;padding:6px;text-align:center}
/* full‑screen popup */
.fullscreen-popup{
  display:none;
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.8);
  justify-content:center;
  align-items:center;
  z-index:200;        /* above everything */
}
.fullscreen-popup img,
.fullscreen-popup video{
  max-width:90%;
  max-height:90%;
  border-radius:10px;
  box-shadow:0 5px 15px rgba(0,0,0,.5);
}

</style>
</head>
<body>
<a href="settings_frontend.php" class="back-btn">← Settings</a>
<h2>Your comments</h2>

<?php if (!$rows): ?>
  <p>You haven’t written any comments yet.</p>
<?php else: ?>
  <div class="posts">
  <?php foreach ($rows as $r):
        $media = $r['post_img'] ?: $r['post_video'];
        $isVid = !empty($r['post_video']); ?>
    <div>
      <div class="tile" onclick="showFullImageFromPath('<?= htmlspecialchars($media) ?>')">
        <?php if ($isVid): ?>
           <video src="<?= htmlspecialchars($media) ?>" muted></video>
        <?php elseif ($media): ?>
           <img src="<?= htmlspecialchars($media) ?>" alt="post">
        <?php else: ?>
           <div class="no-media"><?= htmlspecialchars(mb_strimwidth($r['post_text'],0,60,'…')) ?></div>
        <?php endif; ?>

        <div class="overlay">
          <p><?= htmlspecialchars(mb_strimwidth($r['comment_text'],0,90,'…')) ?></p>
          <small><?= date('d M Y H:i', strtotime($r['comment_time'])) ?></small>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- optional: reuse your fullscreen viewer -->
<script>
function showFullImageFromPath(path){
  /* copy the same popup code you already use elsewhere */
}
</script>
<!-- popup container -->
<div class="fullscreen-popup" id="fullscreenPopup" onclick="closeFullImage()">
  <img   id="fullscreenImage" style="display:none;">
  <video id="fullscreenVideo" controls style="display:none;"></video>
</div>
<script>
function showFullImageFromPath(path){
  const pop  = document.getElementById('fullscreenPopup');
  const img  = document.getElementById('fullscreenImage');
  const vid  = document.getElementById('fullscreenVideo');

  img.style.display = 'none';
  vid.style.display = 'none';

  if (/\.(mp4|webm|ogg)$/i.test(path)){   // video file?
    vid.src    = path;
    vid.style.display = 'block';
    vid.play();
  } else {
    img.src    = path;
    img.style.display = 'block';
  }
  pop.style.display = 'flex';
}

function closeFullImage(){
  const pop = document.getElementById('fullscreenPopup');
  const vid = document.getElementById('fullscreenVideo');
  pop.style.display = 'none';
  vid.pause();              // stop video if open
  vid.currentTime = 0;
}
</script>

</body>
</html>
