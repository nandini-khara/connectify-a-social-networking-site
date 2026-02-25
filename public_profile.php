<?php
session_start();
require 'connect.php';
include 'getdark_mode.php';

if (!isset($_GET['user_id'])) {
    echo "User not selected.";
    exit();
}

$profile_user_id = intval($_GET['user_id']);
$logged_in_user_id = $_SESSION['user_id'] ?? null;
// Check if the logged-in user is following this profile user
$isFollowing = false;
if ($logged_in_user_id && $logged_in_user_id != $profile_user_id) {
    $follow_check = $con->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $follow_check->bind_param("ii", $logged_in_user_id, $profile_user_id);
    $follow_check->execute();
    $follow_check_result = $follow_check->get_result();
    if ($follow_check_result->num_rows > 0) {
        $isFollowing = true;
    }
}
/*  Liked posts of the logged‑in viewer  */
$likedPosts = [];
if ($logged_in_user_id) {
    $likedStmt = $con->prepare("SELECT post_id FROM likes WHERE user_id = ?");
    $likedStmt->bind_param("i", $logged_in_user_id);
    $likedStmt->execute();
    $likedRes = $likedStmt->get_result();
    while ($l = $likedRes->fetch_assoc()) {
        $likedPosts[(int)$l['post_id']] = true;
    }
}
/*  Saved posts of the logged‑in viewer  */
$savedPosts = [];
if ($logged_in_user_id) {
    $savedStmt = $con->prepare("SELECT post_id FROM saves WHERE user_id = ?");
    $savedStmt->bind_param("i", $logged_in_user_id);
    $savedStmt->execute();
    $savedRes = $savedStmt->get_result();
    while ($s = $savedRes->fetch_assoc()) {
        $savedPosts[(int)$s['post_id']] = true;
    }
}
// Fetch number of followers
$followers_stmt = $con->prepare("SELECT COUNT(*) AS total_followers FROM follows WHERE following_id = ?");
$followers_stmt->bind_param("i", $profile_user_id);
$followers_stmt->execute();
$followers_result = $followers_stmt->get_result();
$followers_count = $followers_result->fetch_assoc()['total_followers'] ?? 0;

// Fetch number of following
$following_stmt = $con->prepare("SELECT COUNT(*) AS total_following FROM follows WHERE follower_id = ?");
$following_stmt->bind_param("i", $profile_user_id);
$following_stmt->execute();
$following_result = $following_stmt->get_result();
$following_count = $following_result->fetch_assoc()['total_following'] ?? 0;

// Fetch public user details
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit();
}

$profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'uploads/default-profile.png';
$background_image = !empty($user['background_image']) ? $user['background_image'] : 'uploads/default-background.jpg';
/* ------------------------------------------------------------------
   BLOCK GUARD – does *this* profile owner block the current viewer?
   ------------------------------------------------------------------ */
if ($logged_in_user_id) {                           // only if viewer is logged in
    $check_block = $con->prepare(
        "SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ?"
    );
    $check_block->bind_param("ii", $profile_user_id, $logged_in_user_id);
    $check_block->execute();
    $isBlockedByUser = $check_block->get_result()->num_rows > 0;

    if ($isBlockedByUser) {
        echo "<h2 style='text-align:center;margin-top:120px;
                     font-family:Poppins,sans-serif'>
                 This user has blocked you.
              </h2>";
        exit();                                     // stop rendering anything else
    }
}
/* --------- carry on (load images, posts, etc.) ---------- */
$profile_image    = !empty($user['profile_image'])
                    ? $user['profile_image']
                    : 'uploads/default-profile.png';
$background_image = !empty($user['background_image'])
                    ? $user['background_image']
                    : 'uploads/default-background.jpg';

/* Fetch posts … */

// Fetch posts
$posts = [];
$post_query = "
    SELECT id AS post_id, post_img, post_text, post_video
    FROM   post
    WHERE  user_id = ?
    ORDER  BY created_at DESC
";

$post_stmt = $con->prepare($post_query);
$post_stmt->bind_param("i", $profile_user_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();
while ($row = $post_result->fetch_assoc()) {
    $posts[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Public Profile | Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="profile.css">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body {
      <?php if ($dark_mode == 1): ?>
        background: #121212; color: #eee;
      <?php else: ?>
        background: linear-gradient(120deg, #f0ecff, #ffffff); color: #333;
      <?php endif; ?>
      overflow-x: hidden;
    }
    .top-bar {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  display: flex;
  justify-content: space-between;
  padding: 10px 20px;
  z-index: 100;
  background: <?php if ($dark_mode == 1): ?>#121212<?php else: ?>#fff<?php endif; ?>;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

    .back-button {
      background: linear-gradient(45deg, #6a1b9a, #ab47bc); color: white;
      padding: 8px 16px; border-radius: 30px; font-weight: 600; font-size: 0.95rem;
      box-shadow: 0 3px 10px rgba(0,0,0,0.15); cursor: pointer;
    }
    .menu-icon {
      background: rgba(255, 255, 255, 0.8); padding: 8px 12px;
      border-radius: 12px; cursor: pointer; font-weight: 600;
    }
    .settings-dropdown {
      position: absolute; right: 20px; top: 55px; background: white;
      border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: none; padding: 10px 15px; font-weight: 500; z-index: 99;
    }
    .settings-dropdown button {
      background: none; border: none; padding: 0; color: #333;
      cursor: pointer; font-size: 1rem;
    }
    .profile-header {
      position: relative;
      height: 280px;
      background: url("<?= htmlspecialchars($background_image) ?>") no-repeat center/cover;
      border-bottom-left-radius: 40px;
      border-bottom-right-radius: 40px;
    }
    .profile-img {
      position: absolute; bottom: -60px; left: 50%; transform: translateX(-50%);
      width: 120px; height: 120px; border-radius: 50%; border: 5px solid #fff;
      object-fit: cover; box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }
    .profile-container {
      padding: 100px 20px 40px;
      max-width: 800px; margin: auto;
    }
    .name { text-align: center; font-size: 1.8rem; font-weight: 600; }
    .bio { text-align: center; font-size: 1rem; color: #666; margin-top: 5px; }
    .stats-follow {
      display: flex; justify-content: center; gap: 40px; margin-top: 20px; flex-wrap: wrap;
    }
    .stats-follow div {
      text-align: center;
    }
    .follow-btn {
      margin-top: 10px;
      background: #6a1b9a; color: white; padding: 8px 16px;
      border-radius: 20px; border: none; cursor: pointer; font-weight: 600;
    }
    .highlight {
      flex: 0 0 auto; width: 70px; height: 70px; background: #eee;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 0.8rem; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    
 .posts {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;
    }
    
/* stack media first, action‑bar second */
.post{
  background:#ccc;
  border-radius:12px;
  overflow:hidden;
  display:flex;
  flex-direction:column;   /* ⬅ makes the buttons sit underneath */
  cursor:pointer;
}

/* keeps the picture/video the same 150 px high */
.post-media{ height:150px; }

    .post img, .post video {
      width: 100%; height: 100%; object-fit: cover;
    }
   
    
    .caption {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: rgba(0,0,0,0.5); color: white; font-size: 0.75rem;
      padding: 4px 8px; text-align: center;
    }
    .fullscreen-popup {
      display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.8); justify-content: center; align-items: center; z-index: 100;
    }
    .fullscreen-popup img, .fullscreen-popup video {
      max-width: 90%; max-height: 90%; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.5);
    }
    
/* --- comment box layout -------------------------------------- */
.add-comment{
  display:flex;           /* stay on one line              */
  align-items:center;
  gap:6px;                /* small gap between input & btn */
  width:100%;             /* stretch full width of the post*/
  margin-top:8px;
}

.comment-input{
  flex:1;                 /* take every bit of free space  */
}

.comment-submit{
  margin-left:auto;       /* auto-margin pushes it right   */
  background:none;        /* keep “link” look (btn-link)   */
  border:none;
  padding:0;
  font-weight:600;        /* optional – bolder “Post”      */
  color:#6a1b9a;          /* matches your theme            */
}
/* ---------------- comments layout ---------------- */
.comment{
  display:flex;
  align-items:flex-start;
  gap:8px;                 /* space between avatar & text   */
  margin-top:8px;
}

.comment-author{           /* wraps avatar &/or name */
  text-decoration:none;
  color:inherit;
font-size:0.2rem;   /* was inherit (≈ 0.9–1rem) */
  line-height:1;      /* keeps it tight to the avatar */
}
.comments .c-avatar{
  width:22px;           /* ← tweak to taste (was 28‑35px in your markup) */
  height:22px;
  border-radius:50%;
  object-fit:cover;
  margin-right:6px;     /* keeps a bit of breathing room next to text   */
}
.c-body{                   /* keep your existing .c-body rules if present */
  font-size:0.9rem;
}
.comments {
  max-height: 200px;        /* Limit height */
  overflow-y: auto;         /* Add scrollbar */
  margin-top: 10px;
  padding-right: 5px;
}
.share-sidebar {
  position: fixed;
  top: 0;
  right: -300px;
  width: 280px;
  height: 100%;
  background-color: #fff;
  border-left: 2px solid #eee;
  box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
  padding: 20px;
  transition: right 0.3s ease;
  z-index: 200;
  overflow-y: auto;
}
.share-sidebar.open {
  right: 0;
}
.share-sidebar h5 {
  font-weight: 600;
  margin-bottom: 15px;
}
.share-user {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
}
.share-user img {
  width: 35px;
  height: 35px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 10px;
}
.share-user button {
  margin-left: auto;
  padding: 4px 10px;
  border: none;
  border-radius: 12px;
  background-color: #6a1b9a;
  color: white;
  font-size: 0.8rem;
  cursor: pointer;
}
/* make the whole trash‑can button clickable, not just the <i> */
.delete-comment i{ pointer-events:none; }

.comment .delete-comment{
  flex-shrink:0;          /* don’t let flexbox squeeze it away    */
  align-self:flex-start;  /* keep it aligned with the first line  */
}
.share-btn i {
  transition: transform .2s;
}
.share-btn:hover i {
  transform: scale(1.15);
}
.post-actions i {
  cursor: pointer;
  transition: transform 0.2s;
}
.post-actions i:hover {
  transform: scale(1.2);
}
.post-actions{
  display:flex;
  gap:12px;
  justify-content:center;
  padding:6px 0;     /* breathing‑room above / below icons */
  background:transparent;
}
/* ───────── uniform tiles ───────── */
.posts         {               /* responsive 4‑up grid that shrinks nicely   */
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
  gap:10px;
}
.grid-item     {display:flex;flex-direction:column}

/* perfect square for every media tile */
.post{
  position:relative;
  width:100%;
  padding-top:100%;       /* 1:1 aspect‑ratio hack */
  overflow:hidden;
  border-radius:12px;
  background:#ccc;
  cursor:pointer;
}
.post img,
.post video{
  position:absolute;      /* fill the square */
  inset:0;
  width:100%;height:100%;
  object-fit:cover;
}
/* 1️⃣  Never allow sideways scrolling */
body,
.posts,
.comments {          /* covers the post grid *and* the comments column */
  overflow-x:hidden;  /* stop any horizontal scrollbar */
}

/* 2️⃣  Let long words / URLs wrap instead of forcing a sideways scroll */
.comments,
.comment .c-body {
  word-wrap:break-word;       /* legacy name */
  overflow-wrap:anywhere;     /* modern name */
}

/* 3️⃣  Give the comments panel a bit more breathing‑room on tall screens */
.comments{
  max-height:30vh;    /* ≈ 30 % of viewport height – tweak to taste */
  overflow-y:auto;    /* keep the vertical scroll you already had   */
  padding-right:5px;  /* keeps text clear of the scrollbar          */
}

 </style>
</head>
<body>

<div class="top-bar">
  <div class="back-button" onclick="location.href='home.php'">&#8592; Home</div>
  <div class="menu-icon" onclick="toggleSettings()">&#8942;</div>
  <div class="settings-dropdown" id="settingsDropdown">
    <button id="blockBtn" onclick="toggleBlock()">
  <?= ($isBlocked = $con->query("SELECT 1 FROM blocks WHERE blocker_id = $logged_in_user_id AND blocked_id = $profile_user_id")->num_rows > 0) ? 'Unblock' : 'Block' ?>
</button>

  </div>
</div>

<div class="profile-header">
  <img src="<?= htmlspecialchars($profile_image) ?>" class="profile-img" />
</div>


<div class="profile-container">
  <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
  <div class="bio"><?= htmlspecialchars($user['Bio']) ?></div>

  <div class="stats-follow">
    <div><div style="font-weight: 600; font-size: 1.2rem;"><?= count($posts) ?></div><div>Posts</div></div>
    <div>
  <a href="followers_list.php?user_id=<?= $profile_user_id ?>" style="text-decoration: none; color: inherit;">
    <div style="font-weight: 600; font-size: 1.2rem;"><?= $followers_count ?></div>
    <div>Followers</div>
  </a>
</div>
<div>
  <a href="following_list.php?user_id=<?= $profile_user_id ?>" style="text-decoration: none; color: inherit;">
    <div style="font-weight: 600; font-size: 1.2rem;"><?= $following_count ?></div>
    <div>Following</div>
  </a>
</div>



    <div><button class="follow-btn" id="followBtn" onclick="toggleFollow()">
  <?= $isFollowing ? 'Unfollow' : 'Follow' ?>
</button>
</div>

  </div>

  

  <div class="posts">
<?php
$gridSlots = 4;
$chunks    = array_chunk($posts, $gridSlots);

foreach ($chunks as $chunk) {
    foreach ($chunk as $post) {
        $mediaPath = !empty($post['post_img']) ? $post['post_img'] : $post['post_video'];
        $isVideo   = !empty($post['post_video']);
        $isLiked   = !empty($likedPosts[$post['post_id']]);
        $isSaved   = !empty($savedPosts[$post['post_id']]);
?>
    <!-- one grid cell -->
    <div>
      <div class="post" data-post-id="<?= $post['post_id'] ?>"
           onclick="showFullImageFromPath('<?= htmlspecialchars($mediaPath) ?>')">
        <?php if ($isVideo): ?>
            <video src="<?= htmlspecialchars($mediaPath) ?>" muted></video>
        <?php else: ?>
            <img src="<?= htmlspecialchars($mediaPath) ?>" alt="Post">
        <?php endif; ?>

        <?php if (!empty($post['post_text'])): ?>
            <div class="caption"><?= htmlspecialchars($post['post_text']) ?></div>
        <?php endif; ?>
      </div>

      <div class="post-actions">
        <span class="like-btn" data-post-id="<?= $post['post_id'] ?>">
          <i class="<?= $isLiked ? 'fas' : 'far' ?> fa-heart"
             style="<?= $isLiked ? 'color:red' : '' ?>"></i>
        </span>
        <span class="comment-btn" data-post-id="<?= $post['post_id'] ?>">
          <i class="far fa-comment"></i>
        </span>
        <span class="share-btn" data-post-id="<?= $post['post_id'] ?>">
          <i class="bi bi-share-fill"></i>
        </span>
        <span class="save-btn" data-post-id="<?= $post['post_id'] ?>">
          <i class="<?= $isSaved ? 'fas' : 'far' ?> fa-bookmark"
             style="<?= $isSaved ? 'color:green' : '' ?>"></i>
        </span>
      </div>

      <div id="comments-<?= $post['post_id'] ?>" class="comments d-none"></div>
      <div class="add-comment d-none">
        <input type="text"  class="comment-input form-control"
               placeholder="Add a comment…" data-post-id="<?= $post['post_id'] ?>">
        <button type="button" class="comment-submit btn btn-link p-0"
                data-post-id="<?= $post['post_id'] ?>">Post</button>
      </div>
    </div>
<?php
    } // ← inner foreach closed

    /* pad the row so every row has 4 items */
    $empty = $gridSlots - count($chunk);
    for ($i = 0; $i < $empty; $i++) {
        echo '<div class="post" style="visibility:hidden;"></div>';
    }
} // ← outer foreach closed
?>
</div>
</div>
<div class="fullscreen-popup" id="fullscreenPopup" onclick="closeFullImage()">
  <img id="fullscreenImage" style="display: none;">
  <video id="fullscreenVideo" controls style="display: none;"></video>
</div>

<script>
  function toggleSettings() {
    const dropdown = document.getElementById('settingsDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
  }

  document.addEventListener('click', function (e) {
    const dropdown = document.getElementById('settingsDropdown');
    const icon = document.querySelector('.menu-icon');
    if (!icon.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
  });

 function toggleFollow() {
  const btn = document.getElementById('followBtn');
  const targetId = <?= $profile_user_id ?>;
  const action = btn.textContent.trim() === 'Unfollow' ? 'unfollow' : 'follow';

  fetch('follow_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `target_user_id=${targetId}&action=${action}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      if (action === 'follow') {
        btn.textContent = 'Unfollow';
        btn.style.background = '#6a1b9a';
      } else {
        btn.textContent = 'Follow';
        btn.style.background = '#6a1b9a';
      }
    } else {
      alert(data.message);
    }
  })
  .catch(err => {
    alert("Request failed.");
    console.error(err);
  });
}



  function showFullImageFromPath(path) {
    const popup = document.getElementById('fullscreenPopup');
    const img = document.getElementById('fullscreenImage');
    const video = document.getElementById('fullscreenVideo');
    img.style.display = 'none'; video.style.display = 'none';

    const isVideo = path.match(/\.(mp4|webm|ogg)$/i);
    if (isVideo) {
      video.src = path; video.style.display = 'block'; video.play();
    } else {
      img.src = path; img.style.display = 'block';
    }
    popup.style.display = 'flex';
  }

  function closeFullImage() {
    const popup = document.getElementById('fullscreenPopup');
    const video = document.getElementById('fullscreenVideo');
    popup.style.display = 'none'; video.pause(); video.currentTime = 0;
  }
</script>
<!-- you already closed the first <script> above -->
<script>
/* ---------- block / unblock ---------- */
function toggleBlock() {
  const btn      = document.getElementById('blockBtn');
  const targetId = <?= $profile_user_id ?>;
  const action   = btn.textContent.trim().toLowerCase(); // "block" | "unblock"

  fetch('block_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `target_user_id=${targetId}&action=${action}`
  })
  .then(res => res.json())
  .then(data => {
      if (data.status === 'success') {
          btn.textContent = action === 'block' ? 'Unblock' : 'Block';
          /* optional: refresh so follow‑button disappears, etc. */
          // location.reload();
      } else {
          alert(data.message);
      }
  })
  .catch(() => alert('Block request failed'));
}
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {

  function toggleAction(button, action) {
    const postId = button.dataset.postId;
    fetch('interact_post.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `post_id=${postId}&action=${action}`
    })
    .then(res => res.json())
    .then(data => {
      if (action === 'like') {
        const icon = button.querySelector('i');
        if (data.status === 'liked') {
          icon.classList.remove('far');
          icon.classList.add('fas');
          icon.style.color = 'red';
        } else {
          icon.classList.remove('fas');
          icon.classList.add('far');
          icon.style.color = '';
        }
      } else if (action === 'save') {
        const icon = button.querySelector('i');
        if (data.status === 'saved') {
          icon.classList.remove('far');
          icon.classList.add('fas');
          icon.style.color = 'green';
        } else {
          icon.classList.remove('fas');
          icon.classList.add('far');
          icon.style.color = '';
        }
      }
    })
    .catch(err => {
      console.error('Error:', err);
      alert('Something went wrong!');
    });
  }

  document.querySelectorAll('.like-btn').forEach(btn =>
    btn.addEventListener('click', () => toggleAction(btn, 'like'))
  );

  document.querySelectorAll('.save-btn').forEach(btn =>
    btn.addEventListener('click', () => toggleAction(btn, 'save'))
  );
});
</script>

<script>
  // Open and close sidebar on share button click
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".share-btn").forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        document.getElementById("shareSidebar").classList.add("open");
      });
    });

    // Close sidebar when clicking outside
    document.addEventListener("click", function (e) {
      const sidebar = document.getElementById("shareSidebar");
      const isInsideSidebar = sidebar.contains(e.target);
      const isShareButton = e.target.closest(".share-btn");

      if (!isInsideSidebar && !isShareButton && sidebar.classList.contains("open")) {
        sidebar.classList.remove("open");
      }
    });
  });
</script>
<!-- SHARE SIDEBAR START -->
<div class="share-sidebar" id="shareSidebar">
  <h5>Share with</h5>
<input type="text" id="shareSearch" placeholder="Search users..." style="width: 100%; padding: 6px 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 6px;">
<div id="searchResults"></div>

  <?php
    require_once 'connect.php';
    $user_id = $_SESSION['user_id'];
    $following_query = $con->prepare("SELECT u.user_id, u.full_name, u.profile_image FROM follows f JOIN users u ON f.following_id = u.user_id WHERE f.follower_id = ?");
    $following_query->bind_param("i", $user_id);
    $following_query->execute();
    $following_result = $following_query->get_result();
    while ($follow_user = $following_result->fetch_assoc()):
      $profileImg = !empty($follow_user['profile_image']) ? $follow_user['profile_image'] : 'uploads/default-profile.png';
  ?>
    <div class="share-user">
      <img src="<?php echo htmlspecialchars($profileImg); ?>" alt="">
      <span><?php echo htmlspecialchars($follow_user['full_name']); ?></span>
      <button onclick="alert('Post shared with <?php echo htmlspecialchars($follow_user['full_name']); ?>')">Send</button>
    </div>
  <?php endwhile; ?>
<button id="repostBtn" style="width: 100%; padding: 10px; margin-top: 15px; border: none; border-radius: 6px; background-color: #6a1b9a; color: #fff; font-weight: 600; cursor: pointer;">
  Repost
</button>

</div>
<!-- SHARE SIDEBAR END -->
<script>document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('shareSearch');
  const resultsDiv = document.getElementById('searchResults');

  searchInput.addEventListener('input', () => {
    const query = searchInput.value.trim();
    if (query.length === 0) {
      resultsDiv.innerHTML = '';
      return;
    }

    fetch('search_users.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `query=${encodeURIComponent(query)}`
    })
    .then(res => res.text())
    .then(html => {
      resultsDiv.innerHTML = html;
    })
    .catch(() => {
      resultsDiv.innerHTML = '<p class="text-danger">Search failed</p>';
    });
  });
});
</script>
<script>document.getElementById('repostBtn').addEventListener('click', () => {
  const openPost = document.querySelector('.post[data-post-id]'); 
  const postId = openPost ? openPost.dataset.postId : null;

  if (!postId) {
    alert('No post selected.');
    return;
  }

  // 👉 Prompt for optional caption
  const caption = prompt('Add a comment to your repost (optional):', '');

  fetch('repost_backend.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `post_id=${postId}&caption=${encodeURIComponent(caption || '')}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      alert('Post reposted!');
    } else {
      alert('Failed to repost.');
    }
  })
  .catch(() => alert('Error reposting.'));
});

</script>

<!-- 1️⃣  TOGGLE comment panel  +  FIRST‑TIME LOAD ------------------------- -->
<script>
document.querySelectorAll('.comment-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const postId      = btn.dataset.postId;
    const box         = document.getElementById(`comments-${postId}`);
    const addSection  = box.nextElementSibling;           // input + Post btn
    const opening     = box.classList.contains('d-none'); // were we closed?

    box.classList.toggle('d-none');
    addSection.classList.toggle('d-none');

    /* first open → fetch existing comments once */
    if (opening && !box.dataset.loaded) {
      fetch('load_comments.php', {
        method : 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body   : `post_id=${postId}`
      })
      .then(r  => r.text())
      .then(ht => {
        box.innerHTML      = ht || "<p class='text-muted'>No comments yet.</p>";
        box.dataset.loaded = '1';
      })
      .catch(() => {
        box.innerHTML      = "<p class='text-danger'>Failed to load comments.</p>";
        box.dataset.loaded = '1';
      });
    }
  });
});
</script>

<!-- 2️⃣  SUBMIT a new comment -------------------------------------------- -->
<script>
document.querySelectorAll('.comment-submit').forEach(btn => {
  btn.addEventListener('click', () => {
    const postId = btn.dataset.postId;
    const input  = document.querySelector(`.comment-input[data-post-id="${postId}"]`);
    const text   = input.value.trim();
    if (!text) return;

    fetch('comment_post.php', {
      method : 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body   : `post_id=${postId}&comment=${encodeURIComponent(text)}`
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        const box = document.getElementById(`comments-${postId}`);
        box.insertAdjacentHTML('beforeend', data.html ?? `<div>${text}</div>`);
        box.classList.remove('d-none');
        input.value = '';
      } else {
        alert(data.msg || 'Could not add comment');
      }
    })
    .catch(() => alert('Network error'));
  });
});
</script>

<!-- 3️⃣  DELETE a comment (event‑delegated) ------------------------------- -->
<script>
document.addEventListener('click', e => {
  const btn = e.target.closest('.delete-comment');
  if (!btn) return;

  const id = btn.dataset.commentId;
  if (!confirm('Delete this comment?')) return;

  fetch('delete_comment.php', {
    method : 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body   : 'comment_id=' + encodeURIComponent(id)
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === 'success') {
      btn.closest('.comment').remove();      // UI update
    } else {
      alert(data.msg || 'Could not delete');
    }
  })
  .catch(() => alert('Network error'));
});
</script>
</body>
</html>