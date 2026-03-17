<?php

session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Database connection
require 'connect.php';
include 'getdark_mode.php';

$user_id = $_SESSION['user_id'];

// ── Dark mode check (for inline <style> override below) ──────────────
$dm_stmt = $con->prepare("SELECT dark_mode FROM users WHERE user_id = ?");
$dm_stmt->bind_param("i", $user_id);
$dm_stmt->execute();
$dm_row  = $dm_stmt->get_result()->fetch_assoc();
$is_dark = ($dm_row && $dm_row['dark_mode'] == 1);

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
  ORDER BY p.created_at DESC
LIMIT 10 OFFSET ?
";
$postStmt = $con->prepare($postSql);
$offset = 0;
$postStmt->bind_param("iii", $user_id, $user_id, $offset);
$postStmt->execute();
$postResult = $postStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connectify Feed</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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
    .dropdown a:hover { background: #eee; }
    .show-dropdown { display: flex !important; }
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
    }
    .post p {
      max-height: none;
      overflow-y: visible;
      margin-bottom: 1rem;
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
    .post-header, .post-actions { flex-shrink: 0; }
    .follow-btn {
      background: #6a1b9a;
      color: white;
      border: none;
      padding: 0.4rem 0.75rem;
      border-radius: 8px;
      cursor: pointer;
    }
    #filePreview { margin-top: 10px; max-width: 100%; }
    #filePreview video, #filePreview img {
      max-width: 100%;
      max-height: 300px;
      display: block;
      margin-top: 10px;
    }
    .add-comment {
      display: flex;
      align-items: center;
      gap: 6px;
      width: 100%;
      margin-top: 8px;
    }
    .comment-input { flex: 1; }
    .comment-submit {
      margin-left: auto;
      background: none;
      border: none;
      padding: 0;
      font-weight: 600;
      color: #6a1b9a;
    }
    .comment {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin-top: 8px;
    }
    .comment .c-avatar {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
    }
    .comment-author { text-decoration: none; color: inherit; }
    .c-body { font-size: 0.9rem; }
    .comments {
      max-height: 200px;
      overflow-y: auto;
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
      box-shadow: -2px 0 10px rgba(0,0,0,0.1);
      padding: 20px;
      transition: right 0.3s ease;
      z-index: 200;
      overflow-y: auto;
    }
    .share-sidebar.open { right: 0; }
    .share-sidebar h5 { font-weight: 600; margin-bottom: 15px; }
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
    .delete-comment i { pointer-events: none; }
    .comment .delete-comment { flex-shrink: 0; align-self: flex-start; }
    .share-btn i { transition: transform .2s; }
    .share-btn:hover i { transform: scale(1.15); }
    .right-sidebar {
      flex: 1;
      padding: 1rem;
      background: #f9f9f9;
      border-left: 1px solid #ddd;
      overflow-y: auto;
    }
    .sidebar-card {
      background: #fff;
      border-radius: 12px;
      padding: 1rem;
      margin-bottom: 1rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .sidebar-card h5 { font-weight: 600; margin-bottom: 0.75rem; }
    .stories {
      background: white;
      padding: 0.75rem;
      border-radius: 10px;
      margin-bottom: 1rem;
    }
    .stories h3 { margin-top: 0; }
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
    .highlight-item { font-size: 0.9rem; margin-bottom: 8px; }
    .event {
      display: flex;
      justify-content: space-between;
      font-size: 0.85rem;
      margin-bottom: 6px;
    }
    .event-date { font-weight: 600; color: #6a1b9a; }
    .user-suggestions-compact h4 { margin: 0 0 0.5rem 0; }
    .follow-btn {
      margin-top: 10px;
      background: #6a1b9a;
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      border: none;
      cursor: pointer;
      font-weight: 600;
    }
    @media (max-width: 992px) { .right-sidebar { display: none; } }
    .post-date {
      margin-top: 6px;
      font-size: 0.8rem;
      color: #777;
      align-self: flex-end;
    }
</style>

<?php if ($is_dark): ?>
<style>
/* ═══════════════════════════════════════════════
   HOME PAGE DARK MODE OVERRIDE
   Loaded after page styles so it always wins
   ═══════════════════════════════════════════════ */

body                     { background: #111 !important; color: #fff !important; }
main                     { background: #111 !important; }
.feed                    { background: #111 !important; }

/* Posts */
.post                    { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; color: #fff !important; }
.post p, .post strong    { color: #fff !important; }
.post-date               { color: #999 !important; }
.post-actions i          { color: #ccc !important; }

/* Comments */
.comment-input           { background: #2a2a2a !important; border: 1px solid #3a3a3a !important; color: #fff !important; }
.comment-input::placeholder { color: #888 !important; }
.comment-submit          { color: #bb86fc !important; background: none !important; }
.comments                { background: transparent !important; color: #fff !important; }
.c-body, .c-body *       { color: #fff !important; }

/* Right sidebar */
.right-sidebar           { background: #111 !important; border-left: 1px solid #2e2e2e !important; }
.stories                 { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; }
.stories h3              { color: #fff !important; }
.story                   { background: #2a2a2a !important; color: #fff !important; }
.create-story            { background: #1e1e1e !important; border: 2px dashed #7b2cbf !important; color: #bb86fc !important; }

/* Sidebar cards */
.sidebar-card            { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; color: #fff !important; }
.sidebar-card h5         { color: #fff !important; }
.highlight-item          { color: #ddd !important; }
.event span              { color: #ddd !important; }
.event-date              { color: #bb86fc !important; }
.btn-outline-primary     { color: #bb86fc !important; border-color: #bb86fc !important; background: transparent !important; }

/* Friend suggestions */
.user-suggestions-compact        { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; border-radius: 12px !important; padding: 12px !important; }
.user-suggestions-compact h4     { color: #fff !important; }
.user-suggestions-compact strong { color: #fff !important; }
.user-suggestions-compact small  { color: #999 !important; }
.text-muted              { color: #888 !important; }

/* Share sidebar */
.share-sidebar           { background: #1e1e1e !important; border-left: 1px solid #2e2e2e !important; color: #fff !important; }
.share-sidebar h5        { color: #fff !important; }
.share-user span         { color: #fff !important; }
#shareSearch             { background: #2a2a2a !important; border: 1px solid #3a3a3a !important; color: #fff !important; }
#shareSearch::placeholder { color: #888 !important; }

/* Search bar */
.searchbar input         { background: #2a2a2a !important; color: #fff !important; }
.searchbar input::placeholder { color: #888 !important; }

/* Search results dropdown */
#mainSearchResults       { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; color: #fff !important; }

/* Profile dropdown */
.dropdown                { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; }
.dropdown a              { color: #fff !important; }
.dropdown a:hover        { background: #2a2a2a !important; }

/* Modal */
.modal-content           { background: #1e1e1e !important; color: #fff !important; border: 1px solid #2e2e2e !important; }
.modal-header,
.modal-footer            { background: #1e1e1e !important; border-color: #2e2e2e !important; }
.modal-title             { color: #fff !important; }
.close                   { color: #fff !important; text-shadow: none !important; }
.modal-body textarea     { background: #2a2a2a !important; color: #fff !important; border: 1px solid #3a3a3a !important; }
</style>
<?php endif; ?>

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
    <span id="openChat"><i class="fas fa-comment-dots"></i></span>
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
          $isLiked = !empty($likedPosts[$row['post_id']]);
          $isSaved = !empty($savedPosts[$row['post_id']]);
          $likeCls  = $isLiked ? 'fas' : 'far';
          $likeSty  = $isLiked ? 'color:red;'   : '';
          $saveCls  = $isSaved ? 'fas' : 'far';
          $saveSty  = $isSaved ? 'color:green;' : '';
          $profileLink  = ($row['user_id'] == $user_id) ? 'myprofile_frontend.php' : 'public_profile.php?user_id='.$row['user_id'];
          $userName     = htmlspecialchars($row['user_name']);
          $profileImage = !empty($row['profile_image']) ? $row['profile_image'] : 'default_profile.png';
          $postText     = nl2br(htmlspecialchars($row['post_text']));
          $postImg      = $row['post_img'];
          $postVideo    = $row['post_video'];
          $postDate     = date("d M Y, h:i A", strtotime($row['created_at']));
?>
    <div class="post" data-post-id="<?= $row['post_id'] ?>">
      <div class="post-header">
        <div style="display:flex; align-items:center; gap:10px;">
          <a href="<?= $profileLink ?>" style="text-decoration:none; color:inherit; display:flex; align-items:center; gap:10px;">
            <img src="<?= $profileImage ?>" alt="profile" style="width:40px; height:40px; border-radius:50%;">
            <strong>@<?= $userName ?></strong>
          </a>
        </div>
        <?php if ($row['user_id'] != $user_id):
              $isFollowing = !empty($followingMap[$row['user_id']]); ?>
          <button class="follow-btn"
                  data-user-id="<?= $row['user_id'] ?>"
                  data-following="<?= $isFollowing ? '1' : '0' ?>">
            <?= $isFollowing ? 'Unfollow' : 'Follow' ?>
          </button>
        <?php endif; ?>
      </div>

      <p><?= $postText ?></p>

      <?php if (!empty($postImg)): ?>
        <img src="<?= $postImg ?>" alt="post image" style="max-width:60%; margin-top:10px; border-radius:8px;">
      <?php endif; ?>

      <?php if (!empty($postVideo)): ?>
        <video controls style="max-width:80%; margin-top:10px; border-radius:8px;">
          <source src="<?= $postVideo ?>" type="video/mp4">
        </video>
      <?php endif; ?>

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

      <div id="comments-<?= $row['post_id'] ?>" class="comments d-none"></div>
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
        <button class="btn btn-sm btn-outline-primary mt-2">+ Add Reminder</button>
      </div>
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
          <img src="<?= $img ?>" style="width:32px; height:32px; border-radius:50%;">
          <div style="flex:1;">
            <strong>@<?= htmlspecialchars($s['user_name']) ?></strong><br>
            <small><?= $s['mutual_count'] ?> mutual</small>
          </div>
          <button class="follow-btn" data-user-id="<?= $s['user_id'] ?>" data-following="0">Follow</button>
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

<!-- SHARE SIDEBAR -->
<div class="share-sidebar" id="shareSidebar">
  <h5>Share with</h5>
  <input type="text" id="shareSearch" placeholder="Search users..."
         style="width:100%; padding:6px 10px; margin-bottom:10px; border:1px solid #ccc; border-radius:6px;">
  <div id="searchResults"></div>
  <?php
    $following_sql = "
      SELECT u.user_id, u.full_name, u.profile_image
        FROM follows f
        JOIN users u ON f.following_id = u.user_id
       WHERE f.follower_id = ?
         AND NOT EXISTS (
               SELECT 1 FROM blocks b
                WHERE (b.blocker_id = ? AND b.blocked_id = u.user_id)
                   OR (b.blocker_id = u.user_id AND b.blocked_id = ?)
             )
    ";
    $following_query = $con->prepare($following_sql);
    $following_query->bind_param("iii", $user_id, $user_id, $user_id);
    $following_query->execute();
    $following_result = $following_query->get_result();
    while ($follow_user = $following_result->fetch_assoc()):
      $profileImg = !empty($follow_user['profile_image']) ? $follow_user['profile_image'] : 'uploads/default-profile.png';
  ?>
    <div class="share-user">
      <img src="<?= htmlspecialchars($profileImg) ?>" alt="">
      <span><?= htmlspecialchars($follow_user['full_name']) ?></span>
      <button class="share-send-btn"
              data-user-id="<?= $follow_user['user_id'] ?>"
              data-name="<?= htmlspecialchars($follow_user['full_name']) ?>">Send</button>
    </div>
  <?php endwhile; ?>
  <button id="repostBtn" style="width:100%; padding:10px; margin-top:15px; border:none; border-radius:6px; background-color:#6a1b9a; color:#fff; font-weight:600; cursor:pointer;">
    Repost
  </button>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Profile dropdown
  const profileIcon  = document.getElementById('profileIcon');
  const dropdownMenu = document.getElementById('dropdownMenu');
  profileIcon.addEventListener('click', e => { e.stopPropagation(); dropdownMenu.classList.toggle('show-dropdown'); });
  window.addEventListener('click', e => {
    if (!dropdownMenu.contains(e.target) && !profileIcon.contains(e.target))
      dropdownMenu.classList.remove('show-dropdown');
  });

  // File preview in modal
  const mediaInput = document.getElementById('mediaInput');
  const filePreview = document.getElementById('filePreview');
  mediaInput.addEventListener('change', function () {
    filePreview.innerHTML = '';
    const file = this.files[0];
    if (!file) return;
    filePreview.appendChild(document.createElement('p'));
    const reader = new FileReader();
    reader.onload = e => {
      const url = e.target.result;
      if (file.type.startsWith('image/')) {
        const img = document.createElement('img'); img.src = url; filePreview.appendChild(img);
      } else if (file.type.startsWith('video/')) {
        const v = document.createElement('video'); v.src = url; v.controls = true; filePreview.appendChild(v);
      }
    };
    reader.readAsDataURL(file);
  });
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Like / Save toggle
  function toggleAction(button, action) {
    const postId = button.dataset.postId;
    fetch('interact_post.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `post_id=${postId}&action=${action}`
    })
    .then(r => r.json())
    .then(data => {
      const icon = button.querySelector('i');
      if (action === 'like') {
        icon.classList.toggle('fas', data.status === 'liked');
        icon.classList.toggle('far', data.status !== 'liked');
        icon.style.color = data.status === 'liked' ? 'red' : '';
      } else if (action === 'save') {
        icon.classList.toggle('fas', data.status === 'saved');
        icon.classList.toggle('far', data.status !== 'saved');
        icon.style.color = data.status === 'saved' ? 'green' : '';
      }
    })
    .catch(() => alert('Something went wrong!'));
  }
  document.querySelectorAll('.like-btn').forEach(btn => btn.addEventListener('click', () => toggleAction(btn, 'like')));
  document.querySelectorAll('.save-btn').forEach(btn => btn.addEventListener('click', () => toggleAction(btn, 'save')));

  // Comments toggle
  document.querySelectorAll('.comment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const post = btn.closest('.post');
      const postId = post.dataset.postId;
      const commentsBox = post.querySelector('.comments');
      const addBox = post.querySelector('.add-comment');
      commentsBox.classList.toggle('d-none');
      addBox.classList.toggle('d-none');
      if (!addBox.classList.contains('d-none')) addBox.querySelector('.comment-input').focus();
      if (!commentsBox.dataset.loaded) {
        fetch('load_comments.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`post_id=${postId}` })
          .then(r => r.text())
          .then(html => { commentsBox.innerHTML = html; commentsBox.dataset.loaded = 'true'; })
          .catch(() => { commentsBox.innerHTML = '<p class="text-danger">Failed to load</p>'; });
      }
    });
  });

  // Submit comment
  document.querySelectorAll('.comment-submit').forEach(btn => {
    btn.addEventListener('click', () => {
      const postId = btn.dataset.postId;
      const input  = document.querySelector(`.comment-input[data-post-id="${postId}"]`);
      const text   = input.value.trim();
      if (!text) return;
      fetch('comment_post.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`post_id=${postId}&comment=${encodeURIComponent(text)}` })
        .then(r => r.json())
        .then(data => {
          if (data.status === 'success') {
            const box = document.getElementById(`comments-${postId}`);
            box.classList.remove('d-none');
            box.insertAdjacentHTML('beforeend', data.html);
            input.value = '';
          } else { alert(data.msg); }
        })
        .catch(() => alert('Network error'));
    });
  });

  // Share sidebar open
  document.querySelectorAll('.share-btn').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); document.getElementById('shareSidebar').classList.add('open'); });
  });
  document.addEventListener('click', e => {
    const sidebar = document.getElementById('shareSidebar');
    if (!sidebar.contains(e.target) && !e.target.closest('.share-btn') && sidebar.classList.contains('open'))
      sidebar.classList.remove('open');
  });

  // Share search
  document.getElementById('shareSearch').addEventListener('input', function () {
    const q = this.value.trim();
    const div = document.getElementById('searchResults');
    if (!q) { div.innerHTML = ''; return; }
    fetch('search_users.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`query=${encodeURIComponent(q)}` })
      .then(r => r.text()).then(html => { div.innerHTML = html; })
      .catch(() => { div.innerHTML = '<p class="text-danger">Search failed</p>'; });
  });

  // Main search bar
  const searchInput = document.querySelector('.searchbar input');
  const resultsBox  = document.getElementById('mainSearchResults');
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim();
    if (!q) { resultsBox.style.display = 'none'; resultsBox.innerHTML = ''; return; }
    fetch('search_profiles.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`query=${encodeURIComponent(q)}` })
      .then(r => r.text()).then(html => { resultsBox.innerHTML = html; resultsBox.style.display = 'block'; })
      .catch(() => { resultsBox.innerHTML = '<p class="text-danger">Search failed</p>'; resultsBox.style.display = 'block'; });
  });
  window.addEventListener('click', e => {
    if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) resultsBox.style.display = 'none';
  });
});
</script>

<script>
// Delete comment
document.addEventListener('click', e => {
  const btn = e.target.closest('.delete-comment');
  if (!btn) return;
  if (!confirm('Delete this comment?')) return;
  fetch('delete_comment.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'comment_id='+encodeURIComponent(btn.dataset.commentId) })
    .then(r => r.json())
    .then(data => { if (data.status === 'success') btn.closest('.comment').remove(); else alert(data.msg || 'Could not delete'); })
    .catch(() => alert('Network error'));
});

// Notification count
function loadNotificationCount() {
  fetch('get_notification_count.php').then(r => r.json()).then(data => {
    const badge = document.getElementById('notifCount');
    if (data.count > 0) { badge.textContent = data.count; badge.style.display = 'inline-block'; }
    else { badge.style.display = 'none'; }
  }).catch(console.error);
}
document.addEventListener('DOMContentLoaded', () => { loadNotificationCount(); setInterval(loadNotificationCount, 30000); });
document.getElementById('notifBell').addEventListener('click', () => { window.location.href = 'notifications_frontend.php'; });

// Follow / Unfollow
document.addEventListener('click', e => {
  const btn = e.target.closest('.follow-btn');
  if (!btn) return;
  const targetId    = btn.dataset.userId;
  const isFollowing = btn.dataset.following === '1';
  fetch('follow_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`target_user_id=${targetId}&action=${isFollowing ? 'unfollow' : 'follow'}` })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'followed' || data.status === 'unfollowed') {
        const newState = data.status === 'followed' ? '1' : '0';
        document.querySelectorAll(`.follow-btn[data-user-id="${targetId}"]`).forEach(b => {
          b.dataset.following = newState;
          b.textContent = newState === '1' ? 'Unfollow' : 'Follow';
        });
      }
    })
    .catch(() => alert('Network error'));
});

// Repost
let selectedPostId = null;
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.share-btn').forEach(btn => {
    btn.addEventListener('click', function (e) { e.stopPropagation(); selectedPostId = this.dataset.postId; document.getElementById('shareSidebar').classList.add('open'); });
  });
});
document.getElementById('repostBtn').addEventListener('click', function () {
  if (!selectedPostId) { alert('No post selected.'); return; }
  const caption = prompt('Add a comment to your repost (optional):', '');
  fetch('repost_backend.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`post_id=${selectedPostId}&caption=${encodeURIComponent(caption || '')}` })
    .then(r => r.json())
    .then(data => { alert(data.status === 'success' ? 'Post reposted!' : 'Failed to repost.'); })
    .catch(() => alert('Error reposting.'));
});
</script>

<?php include 'chat_panel.php'; ?>
<script src="share_send.js"></script>

</body>
</html>