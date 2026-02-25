
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

/* ------------------------------------------------------------------ */
/*  USERS I ALREADY FOLLOW                                             */
/* ------------------------------------------------------------------ */
$followingMap = [];

$followStmt = $con->prepare(
  "SELECT following_id FROM follows WHERE follower_id = ?"
);
$followStmt->bind_param("i", $user_id);
$followStmt->execute();
$followRes = $followStmt->get_result();

while ($f = $followRes->fetch_assoc()) {
  $followingMap[(int)$f['following_id']] = true;
}
/* ------------------------------------------------------------------ */
/*  FRIEND SUGGESTIONS (Friends of Friends)                            */
/* ------------------------------------------------------------------ */
$suggestSql = "
SELECT DISTINCT u.user_id, u.user_name, u.profile_image,
       COUNT(*) AS mutual_count
FROM follows f1
JOIN follows f2 ON f1.following_id = f2.follower_id
JOIN users u ON u.user_id = f2.following_id
WHERE f1.follower_id = ?
  AND u.user_id != ?
  AND u.user_id NOT IN (
      SELECT following_id FROM follows WHERE follower_id = ?
  )
GROUP BY u.user_id
ORDER BY mutual_count DESC
LIMIT 5
";

$suggestStmt = $con->prepare($suggestSql);
$suggestStmt->bind_param("iii", $user_id, $user_id, $user_id);
$suggestStmt->execute();
$suggestions = $suggestStmt->get_result();




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
/* ------------------------------------------------------------------ */
/*  3. FETCH FEED POSTS + AUTHOR DATA                                  */
/* ------------------------------------------------------------------ */
$postSql = "
  SELECT
      p.id          AS post_id,
      p.user_id     AS user_id,
      p.post_text,
      p.post_img,
      p.post_video,
      p.created_at,

      u.user_name,
      u.profile_image
  FROM   post  AS p
  JOIN   users AS u ON p.user_id = u.user_id
  WHERE  NOT EXISTS (
           SELECT 1 FROM blocks b
            WHERE (b.blocker_id = ? AND b.blocked_id = p.user_id)
               OR (b.blocker_id = p.user_id AND b.blocked_id = ?)
         )
  ORDER  BY p.created_at DESC
";
$postStmt = $con->prepare($postSql);
$postStmt->bind_param("ii", $user_id, $user_id);   //  ← NEW
$postStmt->execute();
$postResult = $postStmt->get_result();
 ?>
<?php include 'getdark_mode.php'; ?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connectify Feed</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"/>
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">



<style>
    * {
      box-sizing: border-box;
      font-family: 'Inter', sans-serif;
    }

    body {
      margin: 0;
      background: #f5f5f5;
      display: flex;
      flex-direction: column;
      height: 100vh;
    }

    header {
      background: #6a1b9a;
      color: white;
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: relative;
    }

    .searchbar {
      position: relative;
    }

    .searchbar input {
      padding: 0.5rem 0.5rem 0.5rem 2rem;
      border-radius: 8px;
      border: none;
      width: 200px;
    }

    .searchbar i {
      position: absolute;
      left: 8px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
    }

    .icons {
      display: flex;
      gap: 1rem;
      font-size: 1.2rem;
      align-items: center;
      position: relative;
      cursor: pointer;
    }

    .dropdown {
      position: absolute;
      top: 60px;
      right: 20px;
      background: white;
      color: black;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      display: none;
      flex-direction: column;
      min-width: 150px;
      z-index: 10;
    }

    .dropdown a {
      padding: 0.75rem 1rem;
      text-decoration: none;
      color: #333;
    }

    .dropdown a:hover {
      background: #eee;
    }

    .show-dropdown {
      display: flex !important;
    }

    main {
      display: flex;
      flex: 1;
      overflow: hidden;
    }

    .feed {
      flex: 2;
      padding: 1rem;
      overflow-y: auto;
    }

  .post {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  display: flex;
  flex-direction: column;
  /* Remove min-height or max-height */
}
.post p {
  max-height: none; /* Let it grow */
  overflow-y: visible; /* Don't crop */
  margin-bottom: 1rem;
}
.post img,
.post video {
  width: 50%;
  height: 300px; /* or any fixed height you prefer */
  object-fit: cover; /* ensures it fills the space without distortion */
  border-radius: 8px;
  margin-top: 10px;
}
    .post-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .post-actions {
      display: flex;
      gap: 1.5rem;
      margin-top: 0.5rem;
      font-size: 1.3rem;
      cursor: pointer;
    }
.post img, .post video {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
  margin-top: 10px;
}
.post-header, .post-actions {
  flex-shrink: 0;
}
    .follow-btn {
      background: #6a1b9a;
      color: white;
      border: none;
      padding: 0.4rem 0.75rem;
      border-radius: 8px;
      cursor: pointer;
    }

    #filePreview {
      margin-top: 10px;
      max-width: 100%;
    }

    #filePreview video, #filePreview img {
      max-width: 100%;
      max-height: 300px;
      display: block;
      margin-top: 10px;
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
.comment .c-avatar{
  width:28px;
  height:28px;
  border-radius:50%;
  object-fit:cover;
  flex-shrink:0;
}
.comment-author{           /* wraps avatar &/or name */
  text-decoration:none;
  color:inherit;
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
.right-sidebar {
  flex: 1;
  padding: 1rem;
  background: #f9f9f9;
  border-left: 1px solid #ddd;
  overflow-y: auto;
}

/* Card style */
.sidebar-card {
  background: #fff;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.sidebar-card h5 {
  font-weight: 600;
  margin-bottom: 0.75rem;
}
 .stories {
      background: white;
      padding: 0.75rem;
      border-radius: 10px;
      margin-bottom: 1rem;
    }

    .stories h3 {
      margin-top: 0;
    }

    .story-container {
      display: flex;
      gap: 0.75rem;
      overflow-x: auto;
      padding: 0.5rem 0;
    }

    .story {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: #ccc;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      flex-shrink: 0;
      cursor: pointer;
    }

    .create-story {
      background: #e0e0e0;
      color: #6a1b9a;
      font-size: 1.5rem;
      border: 2px dashed #6a1b9a;
    }
/* Highlight item */
.highlight-item {
  font-size: 0.9rem;
  margin-bottom: 8px;
}

/* Event item */
.event {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  margin-bottom: 6px;
}

.event-date {
  font-weight: 600;
  color: #6a1b9a;
}
 .user-suggestions-compact h4 {
      margin: 0 0 0.5rem 0;
    }
.follow-btn {
      margin-top: 10px;
      background: #6a1b9a; color: white; padding: 8px 16px;
      border-radius: 20px; border: none; cursor: pointer; font-weight: 600;
    }
   
/* Hide sidebar on small screens */
@media (max-width: 992px) {
  .right-sidebar {
    display: none;
  }
}
.post-date {
  margin-top: 6px;
  font-size: 0.8rem;
  color: #777;
  align-self: flex-end;   /* 🔥 pushes it to bottom-right */
}


</style>



</head>


<body>
<header>
  <div class="logo">Connectify</div>
  <div class="searchbar">
    <i class="fas fa-search"></i>
    <input type="text" placeholder="Search...">
  </div>
  <div class="icons">
    <span data-toggle="modal" data-target="#newPostModal" title="New Post"><i class="fas fa-plus-circle"></i></span>
    <span style="position: relative;">
  <i class="fas fa-bell" id="notifBell"></i>
  <span id="notifCount"
        style="position:absolute; top:-6px; right:-10px;
               background:red; color:white;
               font-size:10px; font-weight:bold;
               padding:2px 6px; border-radius:50%;
               display:none;">
  </span>
</span>

    <span><i class="fas fa-comment-dots"></i></span>
    <span id="profileIcon"><i class="fas fa-user-circle"></i></span>
    <div class="dropdown" id="dropdownMenu">
      <a href="myprofile_frontend.php">My Profile</a>
      <a href="settings_frontend.php">Settings</a>
      <a href="logout_fe.php">Logout</a>
    </div>
  </div>


</header>

<div id="mainSearchResults" style="
  position: absolute; 
  top: 60px; 
  left: 50%; 
  transform: translateX(-50%);
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 8px;
  max-height: 300px;
  overflow-y: auto;
  width: 300px;
  z-index: 1000;
  display: none;
"></div>

<main>
  <section class="feed">

<?php
 
  if ($postResult->num_rows > 0) {
      while ($row = $postResult->fetch_assoc()) {
/* ---------- state per post ---------- */
      $isLiked = !empty($likedPosts[$row['post_id']]);
      $isSaved = !empty($savedPosts[$row['post_id']]);

      $likeCls  = $isLiked ? 'fas' : 'far';
      $likeSty  = $isLiked ? 'color:red;'    : '';

      $saveCls  = $isSaved ? 'fas' : 'far';
      $saveSty  = $isSaved ? 'color:green;'  : '';

      $profileLink = ($row['user_id'] == $user_id)
                   ? 'myprofile_frontend.php'
                   : 'public_profile.php?user_id='.$row['user_id'];
          $userName = htmlspecialchars($row['user_name']);
          $profileImage = !empty($row['profile_image']) ? $row['profile_image'] : 'default_profile.png';
          $postText = nl2br(htmlspecialchars($row['post_text']));
          $postImg = $row['post_img'];
          $postVideo = $row['post_video'];
          $postDate = date("d M Y, h:i A", strtotime($row['created_at']));
?>
    <div class="post" data-post-id="<?= $row['post_id'] ?>">

     <div class="post-header">
       <div style="display: flex; align-items: center; gap: 10px;">
  <?php
  $profileLink = ($row['user_id'] == $user_id) ? 'myprofile_frontend.php' : 'public_profile.php?user_id=' . $row['user_id'];
?>
<a href="<?= $profileLink ?>" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 10px;">

    <img src="<?= $profileImage ?>" alt="profile" style="width: 40px; height: 40px; border-radius: 50%;">
    <strong>@<?= $userName ?></strong>
  </a>
</div>
<?php if ($row['user_id'] != $user_id): 
  $isFollowing = !empty($followingMap[$row['user_id']]);
?>

<button class="follow-btn"
        data-user-id="<?= $row['user_id'] ?>"
        data-following="<?= $isFollowing ? '1' : '0' ?>">
  <?= $isFollowing ? 'Unfollow' : 'Follow' ?>
</button>

<?php endif; ?>

        
      </div>
      <p><?= $postText ?></p>
      <?php if (!empty($postImg)) { ?>
        <img src="<?= $postImg ?>" alt="post image" style="max-width: 60%; margin-top: 10px; border-radius: 8px;">
      <?php } ?>
      <?php if (!empty($postVideo)) { ?>
        <video controls style="max-width: 80%; margin-top: 10px; border-radius: 8px;">
          <source src="<?= $postVideo ?>" type="video/mp4">
          Your browser does not support the video tag.
        </video>
      <?php } ?>
      <div class="post-actions">
   <span class="like-btn" data-post-id="<?= $row['post_id'] ?>">
        <i class="<?= $likeCls ?> fa-heart" style="<?= $likeSty ?>"></i>
      </span>

      <span class="comment-btn"><i class="far fa-comment"></i></span>

     <span class="share-btn" data-post-id="<?= $row['post_id'] ?>">
  <i class="bi bi-share-fill"></i>
</span>


      <span class="save-btn" data-post-id="<?= $row['post_id'] ?>">
        <i class="<?= $saveCls ?> fa-bookmark" style="<?= $saveSty ?>"></i>
      </span>
</div>
<!-- existing actions bar stays where it is -->

<div id="comments-<?= $row['post_id'] ?>" class="comments d-none">
  <!-- Comments will be loaded here via AJAX -->
</div>
<div class="add-comment d-none">
  <input type="text" class="comment-input form-control"
         placeholder="Add a comment…" data-post-id="<?= $row['post_id'] ?>">
  <button class="comment-submit btn btn-link p-0"
          data-post-id="<?= $row['post_id'] ?>">Post</button>
</div>
<div class="post-date"><?= $postDate ?></div>

    </div>
<?php
      }
  } else {
      echo "<p>No posts to show.</p>";
  }
?>
</section>

<aside class="right-sidebar">
<div class="stories">
      <h3>Stories</h3>
      <div class="story-container">
        <div class="story create-story" title="Create Story">➕</div>
        <div class="story">You</div>
        <div class="story">Jane</div>
        <div class="story">Alex</div>
        <div class="story">Sam</div>
        <div class="story">Riya</div>
      </div>
    <div class="sidebar-card">
      <h5>Highlights</h5>
      <div class="highlight-item">🔥 Most liked post today</div>
      <div class="highlight-item">👤 New follower activity</div>
      <div class="highlight-item">💬 Trending discussion</div>
    </div>

    <div class="sidebar-card">
      <h5>Event Reminders</h5>
      <div class="event">
        <span>Hackathon</span>
        <span class="event-date">20 Sep</span>
      </div>
      <div class="event">
        <span>Project Deadline</span>
        <span class="event-date">25 Sep</span>
      </div>
      <button class="btn btn-sm btn-outline-primary mt-2">
        + Add Reminder
      </button>
    </div>











<div class="user-suggestions-compact">
  <h4>Friend Suggestions</h4>

  <?php if ($suggestions->num_rows === 0): ?>
    <p class="text-muted">No suggestions right now</p>
  <?php endif; ?>

  <?php while ($s = $suggestions->fetch_assoc()):
    $img = !empty($s['profile_image']) ? $s['profile_image'] : 'default_profile.png';
  ?>
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
      <img src="<?= $img ?>" style="width:32px;height:32px;border-radius:50%;">

      <div style="flex:1;">
        <strong>@<?= htmlspecialchars($s['user_name']) ?></strong><br>
        <small><?= $s['mutual_count'] ?> mutual</small>
      </div>

      <button class="follow-btn"
              data-user-id="<?= $s['user_id'] ?>"
              data-following="0">
        Follow
      </button>
    </div>
  <?php endwhile; ?>
</div>

  </aside>
 
</main>
<!-- New Post Modal -->
<div class="modal fade" id="newPostModal" tabindex="-1" role="dialog" aria-labelledby="newPostModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="newpost_backend.php" method="POST" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newPostModalLabel">Create New Post</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <textarea name="post_text" class="form-control mb-3" placeholder="What's on your mind?" rows="3"></textarea>
        <input type="file" name="post_img" class="form-control-file mb-3" accept="image/*,video/*" id="mediaInput"/>

        <div id="filePreview"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Post</button>
      </div>
    </form>
  </div>
</div>
<script>
  const profileIcon = document.getElementById('profileIcon');
  const dropdownMenu = document.getElementById('dropdownMenu');

  profileIcon.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdownMenu.classList.toggle('show-dropdown');
  });

  window.addEventListener('click', function(e) {
    if (!dropdownMenu.contains(e.target) && !profileIcon.contains(e.target)) {
      dropdownMenu.classList.remove('show-dropdown');
    }
  });

  // File preview
  const mediaInput = document.getElementById('mediaInput');
  const filePreview = document.getElementById('filePreview');

  mediaInput.addEventListener('change', function () {
    filePreview.innerHTML = '';
    const file = this.files[0];
    if (!file) return;

    const fileName = document.createElement('p');
    filePreview.appendChild(fileName);

    const reader = new FileReader();
    reader.onload = function (e) {
      const fileURL = e.target.result;
      if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = fileURL;
        filePreview.appendChild(img);
      } else if (file.type.startsWith('video/')) {
        const video = document.createElement('video');
        video.src = fileURL;
        video.controls = true;
        filePreview.appendChild(video);
      }
    };
    reader.readAsDataURL(file);
    });
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
document.addEventListener('DOMContentLoaded', () => {

  document.querySelectorAll('.comment-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const post = btn.closest('.post');
    const postId = post.dataset.postId;
    const commentsBox = post.querySelector('.comments');
    const addBox = post.querySelector('.add-comment');

    // Toggle visibility
    commentsBox.classList.toggle('d-none');
    addBox.classList.toggle('d-none');

    if (!addBox.classList.contains('d-none')) {
      addBox.querySelector('.comment-input').focus();
    }

    // Load comments only once
    if (!commentsBox.dataset.loaded) {
      fetch('load_comments.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `post_id=${postId}`
      })
      .then(res => res.text())
      .then(html => {
        commentsBox.innerHTML = html;
        commentsBox.dataset.loaded = 'true';
      })
      .catch(() => {
        commentsBox.innerHTML = '<p class="text-danger">Failed to load comments</p>';
      });
    }
  });
});
  /* 4.2  Submit a comment (unchanged) */
  document.querySelectorAll('.comment-submit').forEach(btn => {
    btn.addEventListener('click', () => {
      const postId = btn.dataset.postId;
      const input  = document.querySelector(
              `.comment-input[data-post-id="${postId}"]`);
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
          box.classList.remove('d-none');                 // stay visible
          box.insertAdjacentHTML('beforeend', data.html); // add new comment
          input.value = '';
        } else {
          alert(data.msg);
        }
      })
      .catch(() => alert('Network error'));
    });
  });

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
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SHARE SIDEBAR START -->
<div class="share-sidebar" id="shareSidebar">
  <h5>Share with</h5>
<input type="text" id="shareSearch" placeholder="Search users..." style="width: 100%; padding: 6px 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 6px;">
<div id="searchResults"></div>

  <?php
    require_once 'connect.php';
    $user_id = $_SESSION['user_id'];
    /* SHARE SIDEBAR — fetch accounts I follow (minus blocks) -------------- */
$following_sql = "
  SELECT u.user_id, u.full_name, u.profile_image
    FROM follows f
    JOIN users  u ON f.following_id = u.user_id
   WHERE f.follower_id = ?
     AND NOT EXISTS (
           SELECT 1 FROM blocks b
            WHERE (b.blocker_id = ? AND b.blocked_id = u.user_id)
               OR (b.blocker_id = u.user_id AND b.blocked_id = ?)
         )
";
$following_query = $con->prepare($following_sql);
$following_query->bind_param("iii", $user_id, $user_id, $user_id); // ← bind 3 ints
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
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.querySelector('.searchbar input');
  const resultsBox = document.getElementById('mainSearchResults');

  searchInput.addEventListener('input', () => {
    const query = searchInput.value.trim();
    if (query.length === 0) {
      resultsBox.style.display = 'none';
      resultsBox.innerHTML = '';
      return;
    }

    fetch('search_profiles.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `query=${encodeURIComponent(query)}`
    })
    .then(res => res.text())
    .then(html => {
      resultsBox.innerHTML = html;
      resultsBox.style.display = 'block';
    })
    .catch(() => {
      resultsBox.innerHTML = '<p class="text-danger">Search failed</p>';
      resultsBox.style.display = 'block';
    });
  });

  // Hide results if clicking outside
  window.addEventListener('click', e => {
    if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
      resultsBox.style.display = 'none';
    }
  });

});
</script>
<script>
/* delete‑comment handler for feed ------------------------------------ */
document.addEventListener('click', e => {
  const btn = e.target.closest('.delete-comment');
  if (!btn) return;                      // nothing to do

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
      btn.closest('.comment').remove();  // instant UI update
    } else {
      alert(data.msg || 'Could not delete');
    }
  })
  .catch(() => alert('Network error'));
});
</script>
<script>
function loadNotificationCount() {
  fetch('get_notification_count.php')
    .then(res => res.json())
    .then(data => {
      const count = data.count;
      const badge = document.getElementById('notifCount');
      if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-block';
      } else {
        badge.style.display = 'none';
      }
    })
    .catch(console.error);
}

document.addEventListener('DOMContentLoaded', () => {
  loadNotificationCount();
  setInterval(loadNotificationCount, 30000); // optional auto-refresh
});
</script>
<script>
document.getElementById('notifBell').addEventListener('click', () => {
  window.location.href = 'notifications_frontend.php';
});
</script>
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.follow-btn');
  if (!btn) return;

  const targetId = btn.dataset.userId;
  const isFollowing = btn.dataset.following === '1';
  const action = isFollowing ? 'unfollow' : 'follow';

  fetch('follow_action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `target_user_id=${targetId}&action=${action}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'followed' || data.status === 'unfollowed') {

      const newState = data.status === 'followed' ? '1' : '0';
      const newText  = newState === '1' ? 'Unfollow' : 'Follow';

      // 🔥 update ALL buttons of that user
      document.querySelectorAll(`.follow-btn[data-user-id="${targetId}"]`)
        .forEach(b => {
          b.dataset.following = newState;
          b.textContent = newText;
        });
    }
  })
  .catch(() => alert('Network error'));
});

</script>



</body>
</html>