
<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection
require 'connect.php';

$user_id = $_SESSION['user_id'];

// Fetch user details
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

/* ------------------------------------------------------------------ */
/*  1. POSTS THE USER HAS ALREADY LIKED                                */
/* ------------------------------------------------------------------ */
$likedPosts = [];
$likedStmt  = $con->prepare(
    "SELECT post_id FROM likes WHERE user_id = ?"
);
$likedStmt->bind_param("i", $user_id);
$likedStmt->execute();
$likedRes = $likedStmt->get_result();
while ($l = $likedRes->fetch_assoc()) {
    $likedPosts[(int)$l['post_id']] = true;
}

/* ------------------------------------------------------------------ */
/*  2. POSTS THE USER HAS ALREADY SAVED                                */
/* ------------------------------------------------------------------ */
$savedPosts = [];
$savedStmt  = $con->prepare(
    "SELECT post_id FROM saves WHERE user_id = ?"
);
$savedStmt->bind_param("i", $user_id);
$savedStmt->execute();
$savedRes = $savedStmt->get_result();
while ($s = $savedRes->fetch_assoc()) {
    $savedPosts[(int)$s['post_id']] = true;
}

// Set profile and background image paths with fallback
$profile_image = !empty($user['profile_image']) ? $user['profile_image'] : 'uploads/default-profile.png';
$background_image = !empty($user['background_image']) ? $user['background_image'] : 'uploads/default-background.jpg';

// Fetch user's posts
$posts = [];
 $post_query = "
    SELECT
        id           AS post_id,
        post_img,
        post_text,
        post_video,
        created_at
    FROM   post
    WHERE  user_id = ?
    ORDER  BY created_at DESC";

$post_stmt = $con->prepare($post_query);
$post_stmt->bind_param("i", $user_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();
// Fetch number of followers
$followers_stmt = $con->prepare("SELECT COUNT(*) AS total_followers FROM follows WHERE following_id = ?");
$followers_stmt->bind_param("i", $user_id);
$followers_stmt->execute();
$followers_result = $followers_stmt->get_result();
$followers_count = $followers_result->fetch_assoc()['total_followers'] ?? 0;

// Fetch number of following
$following_stmt = $con->prepare("SELECT COUNT(*) AS total_following FROM follows WHERE follower_id = ?");
$following_stmt->bind_param("i", $user_id);
$following_stmt->execute();
$following_result = $following_stmt->get_result();
$following_count = $following_result->fetch_assoc()['total_following'] ?? 0;


while ($row = $post_result->fetch_assoc()) {
    $posts[] = $row;
}
?>
<?php
/* -------------------------------------------------------------- */
/*  FOLLOWING LIST – we’ll need it in both drawers                */
/* -------------------------------------------------------------- */
$followingUsers = [];   // reusable array

$followQ = $con->prepare(
  "SELECT u.user_id, u.full_name, u.profile_image
     FROM follows f
     JOIN users   u ON u.user_id = f.following_id
    WHERE f.follower_id = ?"
);
$followQ->bind_param("i", $user_id);
$followQ->execute();
$followRes = $followQ->get_result();

while ($u = $followRes->fetch_assoc()) {
  $followingUsers[] = $u;            // stash for later
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'getdark_mode.php'; ?>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile | Connectify</title>


<link rel="preload" as="image" href="<?php echo htmlspecialchars($profile_image); ?>">
<link rel="preload" as="image" href="<?php echo htmlspecialchars($background_image); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet"
     href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
 <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      <?php if ($dark_mode == 1): ?>
        background: #121212;
        color: #eee;
      <?php else: ?>
        background: linear-gradient(120deg, #f0ecff, #ffffff);
        color: #333;
      <?php endif; ?>
      overflow-x: hidden;
    }

    .top-bar {
      position: absolute;
      top: 20px;
      width: 100%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 20px;
      z-index: 10;
    }

    .back-button {
      background: linear-gradient(45deg, #6a1b9a, #ab47bc);
      color: white;
      padding: 8px 16px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 0.95rem;
      box-shadow: 0 3px 10px rgba(0,0,0,0.15);
      cursor: pointer;
      transition: background 0.3s;
    }

    .back-button:hover {
      background: linear-gradient(45deg, #5e1690, #9e40af);
    }

    .menu-icon {
      background: rgba(255, 255, 255, 0.8);
      padding: 8px 12px;
      border-radius: 12px;
      cursor: pointer;
      font-weight: 600;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    .settings {
      position: absolute;
      right: 20px;
      top: 55px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: none;
      padding: 10px 15px;
      font-weight: 500;
      cursor: pointer;
    }

    .profile-header {
      position: relative;
      width: 100%;
      height: 280px;
      background: url("<?php echo htmlspecialchars($background_image); ?>") no-repeat center/cover;
      border-bottom-left-radius: 40px;
      border-bottom-right-radius: 40px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      cursor: pointer;
    }

    .profile-img {
      position: absolute;
      bottom: -60px;
      left: 50%;
      transform: translateX(-50%);
      width: 120px;
      height: 120px;
      border-radius: 50%;
      border: 5px solid #fff;
      object-fit: cover;
      box-shadow: 0 5px 15px rgba(0,0,0,0.15);
      cursor: pointer;
    }

    .edit-overlay {
      position: absolute;
      bottom: -55px;
      left: 50%;
      transform: translateX(35px);
      background: #6a1b9a !important;
      color: white !important;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border: 3px solid #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      transition: background 0.3s;
    }

    .edit-overlay:hover {
      background: #5e1690 !important;
    }

    .profile-container {
      padding: 100px 20px 40px;
      max-width: 800px;
      margin: auto;
    }

    .name {
      text-align: center;
      font-size: 1.8rem;
      font-weight: 600;
    }

    .bio {
      text-align: center;
      font-size: 1rem;
      color: #666;
      margin-top: 5px;
    }

   

    .controls {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-bottom: 20px;
    }

    .controls button {
      padding: 8px 15px;
      border-radius: 20px;
      border: none;
      font-weight: 600;
      cursor: pointer;
    }

    .controls .post-btn {
      background: #6a1b9a;
      color: white;
    }

    .controls .share-btn {
  background: #6a1b9a;
  color: #fff;
}

    .tabs {
      display: flex;
      justify-content: center;
      gap: 30px;
      border-bottom: 2px solid #eee;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }

    .tab {
      cursor: pointer;
      font-weight: 600;
      position: relative;
    }

    .tab.active::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 0;
      right: 0;
      height: 3px;
      background: #6a1b9a;
      border-radius: 2px;
    }

    .posts, .tagged {
      display: none;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
    }

    .posts.active, .tagged.active {
      display: grid;
    }

    .post {
      background: #ccc;
      height: 150px;
      border-radius: 12px;
      overflow: hidden;
      position: relative;
    }

    .post img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .empty {
      visibility: hidden;
      height: 150px;
      border-radius: 12px;
    }

    .caption {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0,0,0,0.5);
      color: white;
      font-size: 0.75rem;
      padding: 4px 8px;
      text-align: center;
    }

    .fullscreen-popup {
      display: none;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.8);
      justify-content: center;
      align-items: center;
      z-index: 100;
    }

    .fullscreen-popup img {
      max-width: 90%;
      max-height: 90%;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.5);
    }

    .post-actions {
      margin-top: 6px;
      display: flex;
      justify-content: center;
      gap: 12px;
    }

    ::-webkit-scrollbar {
      display: none;
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
/* scrollable comment list --------------------------------------- */
/* scrollable comment list --------------------------------------- */
.comments{
  height:150px;        /* ✱ hard height → always scrolls             */
  overflow-y:auto;
  padding-right:4px;   /* keeps text off the scrollbar               */
}

/* optional – tidy WebKit scrollbar */
.comments::-webkit-scrollbar{width:6px;}
.comments::-webkit-scrollbar-track{background:#f1f1f1;border-radius:4px;}
.comments::-webkit-scrollbar-thumb{background:#aaa;border-radius:4px;}
.comments::-webkit-scrollbar-thumb:hover{background:#888;}


/* optional – tidy WebKit scrollbar */
.comments::-webkit-scrollbar{width:6px;}
.comments::-webkit-scrollbar-track{background:#f1f1f1;border-radius:4px;}
.comments::-webkit-scrollbar-thumb{background:#aaa;border-radius:4px;}
.comments::-webkit-scrollbar-thumb:hover{background:#888;}
/* smaller avatars inside the comments panel --------------------- */
.comments .c-avatar{
  width:22px;           /* ← tweak to taste (was 28‑35px in your markup) */
  height:22px;
  border-radius:50%;
  object-fit:cover;
  margin-right:6px;     /* keeps a bit of breathing room next to text   */
}
/* ───── three‑dot menu on each post ───── */
/* menu button sits on the picture */
.post-menu{
    position:absolute;          /* overlay */
    top:6px;                    /* distance from top‑right edge  */
    right:6px;
    z-index:10;                 /* above the picture */
}
.menu-btn{
    background:none;
    border:none;
    font-size:20px;             /* size of the glyph */
    line-height:1;
    cursor:pointer;
    color:#fff;                 /* visible on the photo */
    text-shadow:0 0 3px #000;   /* subtle outline on bright pics */
}
.menu-dropdown        { position:absolute;right:0;top:115%;min-width:110px;
                        background:#fff;border:1px solid #ddd;border-radius:6px;
                        box-shadow:0 2px 8px rgba(0,0,0,.15);display:none;z-index:50; }
.menu-dropdown a      { display:block;padding:6px 12px;font-size:.9rem;color:#333;
                        text-decoration:none; }
.menu-dropdown a:hover{ background:#f5f5f5; }
.delete-comment i { pointer-events:none; }  /* makes the whole button clickable */
.share-btn i {
  transition: transform .2s;
}
.share-btn:hover i {
  transform: scale(1.15);
}
  </style>
</head>

<body>


<div class="top-bar">
  <div class="back-button" onclick="location.href='home.php'">&#8592; Home</div>
  <div class="menu-icon" onclick="toggleSettings()">&#8942;</div>
  <div class="settings" id="settingsMenu" onclick="window.location.href='settings_frontend.php'">Settings</div>
</div>

<div class="profile-header" onclick="showFullImage('background')">
  <img src="<?php echo htmlspecialchars($profile_image); ?>" class="profile-img" onclick="showFullImage('profile'); event.stopPropagation();" />
  <div class="edit-overlay" onclick="window.location.href='editprofile_frontend.php'; event.stopPropagation();">+</div>
</div>

<div class="fullscreen-popup" id="fullscreenPopup" onclick="closeFullImage()">
  <img id="fullscreenImage" alt="Full Image" style="display: none;" />
  <video id="fullscreenVideo" controls style="max-width: 90%; max-height: 90%; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); display: none;"></video>
</div>

<div class="profile-container">
  <div class="name"><?php echo htmlspecialchars($user['full_name']); ?></div>
  <div class="bio"><?php echo htmlspecialchars($user['Bio']); ?></div>

  <div style="display: flex; justify-content: center; gap: 40px; margin-top: 20px;">
    <div style="text-align: center;">
      <div style="font-weight: 600; font-size: 1.2rem;"><?php echo count($posts); ?></div>
      <div style="font-size: 0.9rem; color: #777;">Posts</div>
    </div>
    <div style="text-align: center;">
      <div style="text-align: center; cursor: pointer;" onclick="window.location.href='followers_list.php?user_id=<?php echo $user_id; ?>'">
  <div style="font-weight: 600; font-size: 1.2rem;"><?php echo $followers_count; ?></div>
  <div style="font-size: 0.9rem; color: #777;">Followers</div>
</div>

    </div>
    <div style="text-align: center;">
      <div style="text-align: center; cursor: pointer;" onclick="window.location.href='following_list.php?user_id=<?php echo $user_id; ?>'">
  <div style="font-weight: 600; font-size: 1.2rem;"><?php echo $following_count; ?></div>
  <div style="font-size: 0.9rem; color: #777;">Following</div>
</div>

    </div>
  </div>

 

  <div class="controls">
    <button class="post-btn" onclick="window.location.href='newpost.php';">+ New Post</button>
    <button id="profileShareBtn" class="post-btn">Share Profile</button>
  </div>
<div class="tabs">
  <div class="tab active">My Posts</div>
</div>


  <div class="posts active" id="postsTab">
    <?php
    $totalGridSlots = 4;
    $postChunks = array_chunk($posts, $totalGridSlots);
    foreach ($postChunks as $chunk) {
      $postCount = count($chunk);
      foreach ($chunk as $post) {
        ?>
        <div>
         <div class="post"
     data-post-id="<?= $post['post_id'] ?>"
     onclick="<?php
       if (!empty($post['post_img'])) {
         echo "showFullImageFromPath('" . htmlspecialchars($post['post_img']) . "')";
       } elseif (!empty($post['post_video'])) {
         echo "showFullImageFromPath('" . htmlspecialchars($post['post_video']) . "')";
       }
     ?>">
 <!-- ⋯  TOP‑RIGHT BUTTON (menu)-->
<div class="post-menu">
     <button class="menu-btn" data-post-id="<?= $post['post_id'] ?>">⋯</button>
 <div class="menu-dropdown">
              <a href="#" class="delete-post" data-post-id="<?= $post['post_id'] ?>">
                  <i class="far fa-trash-alt"></i> Delete
              </a>
          </div>
      </div>


          <?php if (!empty($post['post_img'])): ?>
            <img src="<?php echo htmlspecialchars($post['post_img']); ?>" alt="Post" />
          <?php elseif (!empty($post['post_video'])): ?>
            <video src="<?php echo htmlspecialchars($post['post_video']); ?>" controls style="width: 100%; height: 100%; object-fit: cover;"></video>
          <?php endif; ?>
          <?php if (!empty($post['post_text'])): ?>
            <div class="caption"><?php echo htmlspecialchars($post['post_text']); ?></div>
          <?php endif; ?>
        </div>
        <div class="post-actions">
    <?php
      // has this specific post been liked / saved?
      $isLiked = !empty($likedPosts[$post['post_id']]);
      $isSaved = !empty($savedPosts[$post['post_id']]);
    ?>
 
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

<!-- hidden comment area identical to the feed -->
<div id="comments-<?= $post['post_id'] ?>" class="comments d-none"></div>

<div class="add-comment d-none">
    <input type="text" class="comment-input form-control"
           placeholder="Add a comment…" data-post-id="<?= $post['post_id'] ?>">
    <button class="comment-submit btn btn-sm btn-primary"
            data-post-id="<?= $post['post_id'] ?>">Post</button>
</div>

      </div>
      <?php
    }
      $emptyCount = $totalGridSlots - $postCount;
      for ($i = 0; $i < $emptyCount; $i++) {
        echo '<div class="empty"></div>';
      }
    }
    if (count($posts) === 0) {
      for ($i = 0; $i < 4; $i++) {
        echo '<div class="empty"></div>';
      }
    }
    ?>
  </div>

  
</div>
<!-- ░░ PROFILE drawer ░░ -->
 <div class="share-sidebar" id="profileShareSidebar">
   <h5>Share profile with</h5>

   <input type="text" id="profileShareSearch" placeholder="Search users…"
          style="width:100%;padding:6px 10px;margin-bottom:10px;
                 border:1px solid #ccc;border-radius:6px;">

   <div id="profileSearchResults"></div>

   <?php /* same PHP loop of people you follow */ ?>
   <?php foreach ($followingUsers as $u):
      $img = !empty($u['profile_image'])
             ? $u['profile_image']
             : 'uploads/default-profile.png';
?>
  <div class="share-user">
    <img src="<?= htmlspecialchars($img) ?>" alt="">
    <span><?= htmlspecialchars($u['full_name']) ?></span>

    <!-- the button can call any endpoint you like -->
    <button
      class="send‑share"
      data-target-id="<?= $u['user_id'] ?>"
      >
      Send
    </button>
  </div>
<?php endforeach; ?>

 </div>

 <!-- ░░ POST drawer ░░ -->
 <div class="share-sidebar" id="postShareSidebar">
   <h5>Share post with</h5>

   <input type="text" id="postShareSearch" placeholder="Search users…"
          style="width:100%;padding:6px 10px;margin-bottom:10px;
                 border:1px solid #ccc;border-radius:6px;">

   <div id="postSearchResults"></div>

   <?php /* same PHP loop of people you follow */ ?>
 <?php foreach ($followingUsers as $u):
      $img = !empty($u['profile_image'])
             ? $u['profile_image']
             : 'uploads/default-profile.png';
?>
  <div class="share-user">
    <img src="<?= htmlspecialchars($img) ?>" alt="">
    <span><?= htmlspecialchars($u['full_name']) ?></span>

    <!-- the button can call any endpoint you like -->
    <button
      class="send‑share"
      data-target-id="<?= $u['user_id'] ?>"
      >
      Send
    </button>
  </div>
<?php endforeach; ?>


  <!-- ⬇️ stay right at the bottom -->
  <button id="repostBtn"
           style="width:100%;padding:10px;margin-top:15px;border:none;
                  border-radius:6px;background:#6a1b9a;color:#fff;
                  font-weight:600;cursor:pointer;">
     Repost
  </button>
 </div>
<script>
  function toggleSettings() {
    const menu = document.getElementById('settingsMenu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
  }

  document.addEventListener('click', function (event) {
    const menu = document.getElementById('settingsMenu');
    const icon = document.querySelector('.menu-icon');
    if (!menu.contains(event.target) && !icon.contains(event.target)) {
      menu.style.display = 'none';
    }
document.addEventListener("click", function (e) {
  const sidebar = document.getElementById("shareSidebar");

  // If the sidebar is open and click is outside both the sidebar and the share button
  if (sidebar.classList.contains("open") &&
      !sidebar.contains(e.target) &&
      !shareBtn.contains(e.target)) {
    sidebar.classList.remove("open");
  }
});

  });

  function showFullImage(imageType) {
    const popup = document.getElementById('fullscreenPopup');
    const img = document.getElementById('fullscreenImage');
    const path = imageType === 'background' ? "<?php echo htmlspecialchars($background_image); ?>" : "<?php echo htmlspecialchars($profile_image); ?>";
    img.onload = function () {
      popup.style.display = 'flex';
    };
    img.src = path;
    img.style.display = 'block';
  }

  function showFullImageFromPath(path) {
    const popup = document.getElementById('fullscreenPopup');
    const img = document.getElementById('fullscreenImage');
    const video = document.getElementById('fullscreenVideo');
    img.style.display = 'none';
    video.style.display = 'none';

    const isVideo = path.match(/\.(mp4|webm|ogg)$/i);
    if (isVideo) {
      video.src = path;
      video.style.display = 'block';
      video.play();
    } else {
      img.src = path;
      img.style.display = 'block';
    }
    popup.style.display = 'flex';
  }

  function closeFullImage() {
    const popup = document.getElementById('fullscreenPopup');
    const video = document.getElementById('fullscreenVideo');
    popup.style.display = 'none';
    video.pause();
    video.currentTime = 0;
  }

  // 💡 Fix Share Button Logic
  document.addEventListener("DOMContentLoaded", function () {
    const shareBtn = document.querySelector(".share-btn");
    const sidebar = document.getElementById("shareSidebar");

    shareBtn.addEventListener("click", function () {
      sidebar.classList.toggle("open");
    });
  });
</script>
<script>/*  profile_page.js
 *  ──────────────────────────────────────────────────────────────────
 *  Holds ALL client‑side helpers for myprofile_frontend.php
 *  (Nothing here is page‑specific, so you can reuse it elsewhere.)
 */

/* -------------------------------------------------------------- */
/* 1.  “Share with …” sidebar: live user search                   */
/* -------------------------------------------------------------- */
/* helper to wire up live‑search inside ANY drawer --------------- */
function attachLiveUserSearch(inputId, resultsId) {
  const input   = document.getElementById(inputId);
  const results = document.getElementById(resultsId);
  if (!input) return;                        // drawer not on this page

  input.addEventListener('input', () => {
    const q = input.value.trim();
    if (!q) { results.innerHTML=''; return; }

    fetch('search_users.php', {
      method : 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body   : `query=${encodeURIComponent(q)}`
    })
      .then(r => r.text())
      .then(html => results.innerHTML = html)
      .catch(() => results.innerHTML =
        '<p class="text-danger">Search failed</p>');
  });
}

document.addEventListener('DOMContentLoaded', () => {
  attachLiveUserSearch('profileShareSearch','profileSearchResults');
  attachLiveUserSearch('postShareSearch','postSearchResults');
});


/* -------------------------------------------------------------- */
/* 2.  Re‑post button                                             */
/* -------------------------------------------------------------- */
(function initRepostBtn () {
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.querySelector('#postShareSidebar #repostBtn');
    if (!btn) return;                // button not on this page

    btn.addEventListener('click', () => {
      const openPost = document.querySelector('.post[data-post-id]');
      const postId   = openPost ? openPost.dataset.postId : null;
      if (!postId) { alert('No post selected.'); return; }

      const caption = prompt('Add a comment to your repost (optional):', '') || '';

      fetch('repost_backend.php', {
        method : 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body   : `post_id=${postId}&caption=${encodeURIComponent(caption)}`
      })
        .then(r => r.json())
        .then(data => alert(data.status === 'success' ? 'Post reposted!' : 'Failed to repost.'))
        .catch(() => alert('Error reposting.'));
    });
  });
})();

/* -------------------------------------------------------------- */
/* 3.  Main top‑bar profile search                                */
/* -------------------------------------------------------------- */
(function initMainSearch () {
  document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('.searchbar input');
    const resultsBox  = document.getElementById('mainSearchResults');

    if (!searchInput) return;        // no search bar on this page

    searchInput.addEventListener('input', () => {
      const query = searchInput.value.trim();
      if (query.length === 0) {
        resultsBox.style.display = 'none';
        resultsBox.innerHTML     = '';
        return;
      }

      fetch('search_profiles.php', {
        method : 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body   : `query=${encodeURIComponent(query)}`
      })
        .then(r => r.text())
        .then(html => {
          resultsBox.innerHTML     = html;
          resultsBox.style.display = 'block';
        })
        .catch(() => {
          resultsBox.innerHTML     = '<p class="text-danger">Search failed</p>';
          resultsBox.style.display = 'block';
        });
    });

    // hide dropdown when clicking outside
    window.addEventListener('click', e => {
      if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
        resultsBox.style.display = 'none';
      }
    });
  });
})();

/* -------------------------------------------------------------- */
/* 4.  Toggle the sidebar                                         */
/*     – one listener for the profile‑share button                */
/*     – separate listener (already in post_interactions.js) for  */
/*       each individual post share button                        */
/* -------------------------------------------------------------- */
(function initProfileShareToggle () {
  document.addEventListener('DOMContentLoaded', () => {
    const profileBtn = document.getElementById('profileShareBtn'); // ← new ID
    const sidebar    = document.getElementById('shareSidebar');
    if (!profileBtn || !sidebar) return;

    profileBtn.addEventListener('click', e => {
      e.stopPropagation();               // don’t fall through to window click
      sidebar.classList.toggle('open');
    });

    // click outside → close
    document.addEventListener('click', e => {
      if (!sidebar.contains(e.target) && !profileBtn.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  });
})();
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {

  /* LIKE ------------------------------------------------------------------ */
  document.querySelectorAll(".like-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const postId = btn.dataset.postId;
      fetch("interact_post.php", {
        method : "POST",
        headers: { "Content-Type":"application/x-www-form-urlencoded" },
        body   : `action=like&post_id=${encodeURIComponent(postId)}`
      })
      .then(r => r.json())
      .then(data => {
        const icon = btn.querySelector("i");
        if (data.status === "liked")  { icon.classList.replace("far","fas"); icon.style.color = "red"; }
        if (data.status === "unliked"){ icon.classList.replace("fas","far"); icon.style.color = "";    }
      });
    });
  });

  /* SAVE ------------------------------------------------------------------ */
  document.querySelectorAll(".save-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const postId = btn.dataset.postId;
      fetch("interact_post.php", {
        method : "POST",
        headers: { "Content-Type":"application/x-www-form-urlencoded" },
        body   : `action=save&post_id=${encodeURIComponent(postId)}`
      })
      .then(r => r.json())
      .then(data => {
        const icon = btn.querySelector("i");
        if (data.status === "saved")   { icon.classList.replace("far","fas"); icon.style.color = "green"; }
        if (data.status === "unsaved") { icon.classList.replace("fas","far"); icon.style.color = "";      }
      });
    });
  });

  /* SHARE (opens sidebar) -------------------------------------------------- */
  document.querySelectorAll(".share-btn").forEach(btn => {
    btn.addEventListener("click", e => {
      e.stopPropagation();
      const sidebar = document.getElementById("shareSidebar");
      sidebar.dataset.activePost = btn.dataset.postId;
      sidebar.classList.add("open");

      const search = document.getElementById("shareSearch");
      if (search) {
        search.value = "";
        document.getElementById("searchResults").innerHTML = "";
        search.focus();
      }
    });
  });

  /* COMMENT toggle --------------------------------------------------------- */
  /* COMMENT toggle + first‑time load ------------------------------ */
document.querySelectorAll('.comment-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const postId     = btn.dataset.postId;
    const box        = document.getElementById(`comments-${postId}`);
    const addSection = box.nextElementSibling;      // the input + Post btn

    const opening    = box.classList.contains('d-none'); // were we closed?

    box.classList.toggle('d-none');
    addSection.classList.toggle('d-none');

    /* first‑time open → fetch all past comments */
    if (opening && !box.dataset.loaded){
      fetch('load_comments.php',{
        method :'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body   : `post_id=${postId}`
      })
      .then(r=>r.text())
      .then(html=>{
        box.innerHTML      = html || "<p class='text-muted'>No comments yet.</p>";
        box.dataset.loaded = '1';                    // mark as done
      })
      .catch(()=>{
        box.innerHTML      = "<p class='text-danger'>Failed to load comments.</p>";
        box.dataset.loaded = '1';
      });
    }
  });
});

  /* COMMENT submit --------------------------------------------------------- */
  document.querySelectorAll(".comment-submit").forEach(btn => {
    btn.addEventListener("click", () => {
      const postId = btn.dataset.postId;
      const input  = document.querySelector(`.comment-input[data-post-id="${postId}"]`);
      const text   = input.value.trim();
      if (!text) return;

      fetch("comment_post.php", {
        method : "POST",
        headers: { "Content-Type":"application/x-www-form-urlencoded" },
        body   : `post_id=${postId}&comment=${encodeURIComponent(text)}`
      })
      .then(r => r.json())
      .then(data => {
        if (data.status === "success") {
          const box = document.getElementById(`comments-${postId}`);
          box.insertAdjacentHTML("beforeend", data.html ?? `<div>${text}</div>`);
          box.classList.remove("d-none");
          input.value = "";
        } else {
          alert(data.message || "Failed to comment");
        }
      });
    });
  });

});
</script>

<script>/* -------------------------------------------------------------- */
/* profile‑drawer toggle                                          */
/* -------------------------------------------------------------- */
(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const trigger = document.getElementById('profileShareBtn');
    const drawer  = document.getElementById('profileShareSidebar');
    if (!trigger || !drawer) return;

    trigger.addEventListener('click', e => {
      e.stopPropagation();
      drawer.classList.toggle('open');
    });

    // click outside → close
    document.addEventListener('click', e => {
      if (!drawer.contains(e.target) && !trigger.contains(e.target)) {
        drawer.classList.remove('open');
      }
    });
  });
})();
/* -------------------------------------------------------------- */
/* post‑drawer toggle + active‑post id                            */
/* -------------------------------------------------------------- */
(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const drawer   = document.getElementById('postShareSidebar');
    const triggers = document.querySelectorAll('.share-btn[data-post-id]');
    if (!drawer) return;

    triggers.forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        drawer.dataset.activePost = btn.dataset.postId;
        drawer.classList.add('open');

        // reset & focus the search box
        const input = document.getElementById('postShareSearch');
        const box   = document.getElementById('postSearchResults');
        if (input && box) { input.value=''; box.innerHTML=''; input.focus(); }
      });
    });

    // click outside → close
    document.addEventListener('click', e => {
      if (!drawer.contains(e.target) && !e.target.closest('.share-btn[data-post-id]')) {
        drawer.classList.remove('open');
      }
    });
  });
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {

  /* open / close the ⋯ dropdown */
  document.querySelectorAll('.menu-btn').forEach(btn=>{
      btn.addEventListener('click', e=>{
          e.stopPropagation();
          const menu = btn.nextElementSibling;
          menu.style.display = menu.style.display==='block' ? 'none' : 'block';
      });
  });

  /* close all menus if you click elsewhere */
  window.addEventListener('click', ()=>{
      document.querySelectorAll('.menu-dropdown').forEach(m=>m.style.display='none');
  });

  /* delete the post */
  document.querySelectorAll('.delete-post').forEach(link=>{
      link.addEventListener('click', e=>{
          e.preventDefault();
e.stopPropagation(); 
          const postId = link.dataset.postId;
          if(!confirm('Delete this post?')) return;

          fetch('delete_post.php', {
              method :'POST',
              headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body   : `post_id=${postId}`
          })
          .then(r=>r.json())
          .then(data=>{
              if(data.status==='success'){
                  // remove the whole grid cell that contains this .post
                  document.querySelector(`.post[data-post-id="${postId}"]`).parentElement.remove();
              }else{
                  alert(data.msg || 'Could not delete');
              }
          })
          .catch(()=>alert('Network error'));
      });
  });

});
</script>
<script>document.addEventListener('click', e => {
  if (!e.target.closest('.delete-comment')) return;

  const btn       = e.target.closest('.delete-comment');
  const commentId = btn.dataset.commentId;
  if (!confirm('Delete this comment?')) return;

  fetch('delete_comment.php', {
      method : 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body   : 'comment_id=' + encodeURIComponent(commentId)
  })
  .then(r => r.json())
  .then(data => {
      if (data.status === 'success') {
          btn.closest('.comment').remove();              // vanish instantly
      } else {
          alert(data.msg || 'Could not delete');
      }
  })
  .catch(() => alert('Network error'));
});
</script>
</body>
</html> 