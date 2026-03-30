<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require 'connect.php';

$user_id = $_SESSION['user_id'];

/* Dark mode */
$dark_mode = 0;
$dm_stmt = $con->prepare("SELECT dark_mode FROM users WHERE user_id = ?");
$dm_stmt->bind_param("i", $user_id);
$dm_stmt->execute();
$dm_row = $dm_stmt->get_result()->fetch_assoc();
$dm_stmt->close();
if ($dm_row) $dark_mode = (int)$dm_row['dark_mode'];

/* User details */
$stmt = $con->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) die("User not found.");

/* Liked posts */
$likedPosts = [];
$likedStmt = $con->prepare("SELECT post_id FROM likes WHERE user_id = ?");
$likedStmt->bind_param("i", $user_id);
$likedStmt->execute();
$likedRes = $likedStmt->get_result();
while ($l = $likedRes->fetch_assoc()) $likedPosts[(int)$l['post_id']] = true;

/* Saved posts */
$savedPosts = [];
$savedStmt = $con->prepare("SELECT post_id FROM saves WHERE user_id = ?");
$savedStmt->bind_param("i", $user_id);
$savedStmt->execute();
$savedRes = $savedStmt->get_result();
while ($s = $savedRes->fetch_assoc()) $savedPosts[(int)$s['post_id']] = true;

$profile_image    = !empty($user['profile_image'])    ? $user['profile_image']    : 'uploads/default-profile.png';
$background_image = !empty($user['background_image']) ? $user['background_image'] : 'uploads/default-background.jpg';

/* User's posts */
$posts = [];
$post_stmt = $con->prepare("SELECT id AS post_id, post_img, post_text, post_video, created_at FROM post WHERE user_id = ? ORDER BY created_at DESC");
$post_stmt->bind_param("i", $user_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();
while ($row = $post_result->fetch_assoc()) $posts[] = $row;

/* Counts */
$followers_stmt = $con->prepare("SELECT COUNT(*) AS c FROM follows WHERE following_id = ?");
$followers_stmt->bind_param("i", $user_id);
$followers_stmt->execute();
$followers_count = $followers_stmt->get_result()->fetch_assoc()['c'] ?? 0;

$following_stmt = $con->prepare("SELECT COUNT(*) AS c FROM follows WHERE follower_id = ?");
$following_stmt->bind_param("i", $user_id);
$following_stmt->execute();
$following_count = $following_stmt->get_result()->fetch_assoc()['c'] ?? 0;

/* Following list for share drawers */
$followingUsers = [];
$followQ = $con->prepare("SELECT u.user_id, u.full_name, u.user_name, u.profile_image FROM follows f JOIN users u ON u.user_id = f.following_id WHERE f.follower_id = ?");
$followQ->bind_param("i", $user_id);
$followQ->execute();
$followRes = $followQ->get_result();
while ($u = $followRes->fetch_assoc()) $followingUsers[] = $u;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile | Connectify</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
    body{background:<?= $dark_mode?'#121212':'linear-gradient(120deg,#f0ecff,#ffffff)'?>;color:<?= $dark_mode?'#eee':'#333'?>;overflow-x:hidden;}
    .top-bar{position:absolute;top:20px;width:100%;display:flex;justify-content:space-between;align-items:center;padding:0 20px;z-index:10;}
    .back-button{background:linear-gradient(45deg,#6a1b9a,#ab47bc);color:white;padding:8px 16px;border-radius:30px;font-weight:600;font-size:.95rem;box-shadow:0 3px 10px rgba(0,0,0,.15);cursor:pointer;transition:background .3s;}
    .back-button:hover{background:linear-gradient(45deg,#5e1690,#9e40af);}
    .menu-icon{background:<?= $dark_mode?'rgba(255,255,255,.1)':'rgba(255,255,255,.8)'?>;padding:8px 12px;border-radius:12px;cursor:pointer;font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.1);color:<?= $dark_mode?'#eee':'#333'?>;}
    .settings{position:absolute;right:20px;top:55px;background:<?= $dark_mode?'#1e1e1e':'white'?>;color:<?= $dark_mode?'#eee':'#333'?>;border:1px solid <?= $dark_mode?'#2e2e2e':'#ddd'?>;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);display:none;padding:10px 15px;font-weight:500;cursor:pointer;}
    .profile-header{position:relative;width:100%;height:280px;background:url("<?= htmlspecialchars($background_image)?>") no-repeat center/cover;border-bottom-left-radius:40px;border-bottom-right-radius:40px;box-shadow:0 8px 20px rgba(0,0,0,.1);cursor:pointer;}
    .profile-img{position:absolute;bottom:-60px;left:50%;transform:translateX(-50%);width:120px;height:120px;border-radius:50%;border:5px solid #fff;object-fit:cover;box-shadow:0 5px 15px rgba(0,0,0,.15);cursor:pointer;}
    .edit-overlay{position:absolute;bottom:-55px;left:50%;transform:translateX(35px);background:#6a1b9a;color:white;width:28px;height:28px;border-radius:50%;font-size:1.3rem;display:flex;align-items:center;justify-content:center;cursor:pointer;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.2);transition:background .3s;}
    .edit-overlay:hover{background:#5e1690;}
    .profile-container{padding:100px 20px 40px;max-width:800px;margin:auto;}
    .name{text-align:center;font-size:1.8rem;font-weight:600;}
    .bio{text-align:center;font-size:1rem;color:<?= $dark_mode?'#aaa':'#666'?>;margin-top:5px;}
    .controls{display:flex;justify-content:center;gap:15px;margin-bottom:20px;}
    .controls button{padding:8px 15px;border-radius:20px;border:none;font-weight:600;cursor:pointer;background:#6a1b9a;color:white;}
    .stat-label{color:<?= $dark_mode?'#aaa':'#777'?>;font-size:.9rem;}
    .stat-value{font-weight:600;font-size:1.2rem;}
    .tabs{display:flex;justify-content:center;gap:30px;border-bottom:2px solid <?= $dark_mode?'#333':'#eee'?>;padding-bottom:10px;margin-bottom:20px;}
    .tab{cursor:pointer;font-weight:600;position:relative;color:<?= $dark_mode?'#eee':'#333'?>;}
    .tab.active::after{content:'';position:absolute;bottom:-10px;left:0;right:0;height:3px;background:#6a1b9a;border-radius:2px;}
    .posts{display:none;grid-template-columns:repeat(4,1fr);gap:10px;}
    .posts.active{display:grid;}
    .post{background:<?= $dark_mode?'#1e1e1e':'#ccc'?>;height:150px;border-radius:12px;overflow:hidden;position:relative;}
    .post img{width:100%;height:100%;object-fit:cover;}
    .empty{visibility:hidden;height:150px;border-radius:12px;}
    .caption{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.5);color:white;font-size:.75rem;padding:4px 8px;text-align:center;}
    .fullscreen-popup{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.8);justify-content:center;align-items:center;z-index:100;}
    .fullscreen-popup img{max-width:90%;max-height:90%;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,.5);}
    .post-actions{margin-top:6px;display:flex;justify-content:center;gap:12px;}
    .post-actions i{color:<?= $dark_mode?'#ccc':'#333'?>;}
    .post-actions .like-btn .fas.fa-heart{color:red!important;}
    .post-actions .save-btn .fas.fa-bookmark{color:green!important;}
    ::-webkit-scrollbar{display:none;}

    /* Share sidebar */
    .share-sidebar{position:fixed;top:0;right:-320px;width:300px;height:100%;background:<?= $dark_mode?'#1e1e1e':'#fff'?>;border-left:2px solid <?= $dark_mode?'#2e2e2e':'#eee'?>;box-shadow:-2px 0 10px rgba(0,0,0,.1);padding:20px;transition:right .3s ease;z-index:9000;overflow-y:auto;color:<?= $dark_mode?'#eee':'#333'?>;}
    .share-sidebar.open{right:0;}
    .share-sidebar h5{font-weight:600;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;}
    .share-sidebar h5 button{background:none;border:none;font-size:18px;cursor:pointer;color:<?= $dark_mode?'#eee':'#333'?>;}
    .share-search{width:100%;padding:6px 10px;margin-bottom:10px;border:1px solid <?= $dark_mode?'#3a3a3a':'#ccc'?>;border-radius:6px;background:<?= $dark_mode?'#2a2a2a':'#fff'?>;color:<?= $dark_mode?'#fff':'#333'?>;}
    .share-user-row{display:flex;align-items:center;gap:10px;padding:8px 4px;border-bottom:1px solid <?= $dark_mode?'rgba(255,255,255,.07)':'#f0f0f0'?>;min-width:0;}
    .share-user-row img{width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;}
    .share-user-row .su-info{flex:1;min-width:0;overflow:hidden;}
    .share-user-row .su-name{font-size:.85rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;}
    .share-user-row .su-sub{font-size:.72rem;color:<?= $dark_mode?'#888':'#999'?>;display:block;}
    .send-share-btn{background:linear-gradient(135deg,#6a1b9a,#ab47bc);border:none;color:#fff;border-radius:8px;padding:5px 14px;font-size:.78rem;font-weight:600;cursor:pointer;flex-shrink:0;white-space:nowrap;transition:opacity .15s;margin-left:auto;}
    .send-share-btn:hover{opacity:.85;}
    .send-share-btn.sent{background:#39d353;pointer-events:none;}
    .share-empty{font-size:.82rem;color:<?= $dark_mode?'#666':'#aaa'?>;padding:12px 0;text-align:center;}

    /* Comments */
    .comments{height:150px;overflow-y:auto;padding-right:4px;color:<?= $dark_mode?'#eee':'#333'?>;}
    .comments::-webkit-scrollbar{width:6px;display:block;}
    .comments::-webkit-scrollbar-track{background:<?= $dark_mode?'#2a2a2a':'#f1f1f1'?>;border-radius:4px;}
    .comments::-webkit-scrollbar-thumb{background:<?= $dark_mode?'#555':'#aaa'?>;border-radius:4px;}
    .comments .c-avatar{width:22px;height:22px;border-radius:50%;object-fit:cover;margin-right:6px;}
    .c-body,.c-body *{color:<?= $dark_mode?'#eee':'#333'?>!important;}
    .comment-input{background:<?= $dark_mode?'#2a2a2a':''?>!important;border:1px solid <?= $dark_mode?'#3a3a3a':'#ccc'?>!important;color:<?= $dark_mode?'#fff':'#333'?>!important;}
    .comment-input::placeholder{color:<?= $dark_mode?'#888':'#aaa'?>!important;}
    .comment-submit{color:<?= $dark_mode?'#bb86fc':'#6a1b9a'?>!important;background:none!important;border:none;}

    /* Three-dot post menu */
    .post-menu{position:absolute;top:6px;right:6px;z-index:10;}
    .menu-btn{background:none;border:none;font-size:20px;line-height:1;cursor:pointer;color:#fff;text-shadow:0 0 3px #000;}
    .menu-dropdown{position:absolute;right:0;top:115%;min-width:110px;background:<?= $dark_mode?'#1e1e1e':'#fff'?>;border:1px solid <?= $dark_mode?'#3a3a3a':'#ddd'?>;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.15);display:none;z-index:50;}
    .menu-dropdown a{display:block;padding:6px 12px;font-size:.9rem;color:<?= $dark_mode?'#eee':'#333'?>;text-decoration:none;}
    .menu-dropdown a:hover{background:<?= $dark_mode?'#2a2a2a':'#f5f5f5'?>;}
    .delete-comment i{pointer-events:none;}
  </style>
</head>
<body>

<div class="top-bar">
  <div class="back-button" onclick="location.href='home.php'">&#8592; Home</div>
  <div class="menu-icon" onclick="toggleSettings()">&#8942;</div>
  <div class="settings" id="settingsMenu" onclick="window.location.href='settings_frontend.php'">Settings</div>
</div>

<div class="profile-header" onclick="showFullImage('background')">
  <img src="<?= htmlspecialchars($profile_image)?>" class="profile-img"
       onclick="showFullImage('profile');event.stopPropagation();">
  <div class="edit-overlay"
       onclick="window.location.href='editprofile_frontend.php';event.stopPropagation();">+</div>
</div>

<div class="fullscreen-popup" id="fullscreenPopup" onclick="closeFullImage()">
  <img id="fullscreenImage" alt="" style="display:none;">
  <video id="fullscreenVideo" controls style="max-width:90%;max-height:90%;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,.5);display:none;"></video>
</div>

<div class="profile-container">
  <div class="name"><?= htmlspecialchars($user['full_name'])?></div>
  <div class="bio"><?= htmlspecialchars($user['Bio'])?></div>

  <div style="display:flex;justify-content:center;gap:40px;margin-top:20px;">
    <div style="text-align:center;">
      <div class="stat-value"><?= count($posts)?></div>
      <div class="stat-label">Posts</div>
    </div>
    <div style="text-align:center;cursor:pointer;" onclick="window.location.href='followers_list.php?user_id=<?= $user_id?>'">
      <div class="stat-value"><?= $followers_count?></div>
      <div class="stat-label">Followers</div>
    </div>
    <div style="text-align:center;cursor:pointer;" onclick="window.location.href='following_list.php?user_id=<?= $user_id?>'">
      <div class="stat-value"><?= $following_count?></div>
      <div class="stat-label">Following</div>
    </div>
  </div>

  <div class="controls" style="margin-top:20px;">
    <button onclick="window.location.href='newpost.php'">+ New Post</button>
    <button id="profileShareBtn">Share Profile</button>
  </div>

  <div class="tabs"><div class="tab active">My Posts</div></div>

  <div class="posts active" id="postsTab">
    <?php
    $chunks = array_chunk($posts, 4);
    foreach ($chunks as $chunk) {
        foreach ($chunk as $post):
            $isLiked = !empty($likedPosts[$post['post_id']]);
            $isSaved = !empty($savedPosts[$post['post_id']]);
    ?>
      <div>
        <div class="post" data-post-id="<?= $post['post_id']?>"
             onclick="<?php
               if (!empty($post['post_img']))   echo "showFullImageFromPath('".htmlspecialchars($post['post_img'])."')";
               elseif (!empty($post['post_video'])) echo "showFullImageFromPath('".htmlspecialchars($post['post_video'])."')";
             ?>">
          <div class="post-menu">
            <button class="menu-btn" data-post-id="<?= $post['post_id']?>">⋯</button>
            <div class="menu-dropdown">
              <a href="#" class="delete-post" data-post-id="<?= $post['post_id']?>">
                <i class="far fa-trash-alt"></i> Delete
              </a>
            </div>
          </div>
          <?php if (!empty($post['post_img'])): ?>
            <img src="<?= htmlspecialchars($post['post_img'])?>" alt="Post">
          <?php elseif (!empty($post['post_video'])): ?>
            <video src="<?= htmlspecialchars($post['post_video'])?>" style="width:100%;height:100%;object-fit:cover;"></video>
          <?php endif; ?>
          <?php if (!empty($post['post_text'])): ?>
            <div class="caption"><?= htmlspecialchars($post['post_text'])?></div>
          <?php endif; ?>
        </div>

        <div class="post-actions">
          <span class="like-btn" data-post-id="<?= $post['post_id']?>">
            <i class="<?= $isLiked?'fas':'far'?> fa-heart" style="<?= $isLiked?'color:red':''?>"></i>
          </span>
          <span class="comment-btn" data-post-id="<?= $post['post_id']?>">
            <i class="far fa-comment"></i>
          </span>
          <span class="share-post-btn" data-post-id="<?= $post['post_id']?>" style="cursor:pointer;">
            <i class="bi bi-share-fill"></i>
          </span>
          <span class="save-btn" data-post-id="<?= $post['post_id']?>">
            <i class="<?= $isSaved?'fas':'far'?> fa-bookmark" style="<?= $isSaved?'color:green':''?>"></i>
          </span>
        </div>

        <div id="comments-<?= $post['post_id']?>" class="comments d-none"></div>
        <div class="add-comment d-none">
          <input type="text" class="comment-input form-control"
                 placeholder="Add a comment…" data-post-id="<?= $post['post_id']?>">
          <button class="comment-submit btn btn-sm" data-post-id="<?= $post['post_id']?>">Post</button>
        </div>
      </div>
    <?php
        endforeach;
        $empty = 4 - count($chunk);
        for ($i = 0; $i < $empty; $i++) echo '<div class="empty"></div>';
    }
    if (count($posts) === 0) {
        for ($i = 0; $i < 4; $i++) echo '<div class="empty"></div>';
    }
    ?>
  </div>
</div>

<!-- ════ SHARE PROFILE SIDEBAR ════ -->
<div class="share-sidebar" id="profileShareSidebar">
  <h5>Share Profile <button onclick="closeSidebar('profileShareSidebar')">✕</button></h5>
  <input type="text" class="share-search" id="profileShareSearch" placeholder="Search people…">
  <div id="profileShareResults"></div>
  <div id="profileShareList">
    <?php foreach ($followingUsers as $fu):
      $img = !empty($fu['profile_image']) ? htmlspecialchars($fu['profile_image']) : 'uploads/default-profile.png';
      $name = htmlspecialchars($fu['full_name'] ?: $fu['user_name']);
    ?>
      <div class="share-user-row">
        <img src="<?= $img?>" alt="">
        <div class="su-info"><span class="su-name"><?= $name?></span></div>
        <button class="send-share-btn"
                data-target-id="<?= $fu['user_id']?>"
                data-share-type="profile"
                data-share-value="<?= $user_id?>">Send</button>
      </div>
    <?php endforeach;?>
    <?php if (empty($followingUsers)): ?>
      <div class="share-empty">You are not following anyone yet.</div>
    <?php endif;?>
  </div>
</div>

<!-- ════ SHARE POST SIDEBAR ════ -->
<div class="share-sidebar" id="postShareSidebar">
  <h5>Share Post <button onclick="closeSidebar('postShareSidebar')">✕</button></h5>
  <input type="text" class="share-search" id="postShareSearch" placeholder="Search people…">
  <div id="postShareResults"></div>
  <div id="postShareList">
    <?php foreach ($followingUsers as $fu):
      $img = !empty($fu['profile_image']) ? htmlspecialchars($fu['profile_image']) : 'uploads/default-profile.png';
      $name = htmlspecialchars($fu['full_name'] ?: $fu['user_name']);
    ?>
      <div class="share-user-row">
        <img src="<?= $img?>" alt="">
        <div class="su-info"><span class="su-name"><?= $name?></span></div>
        <button class="send-share-btn"
                data-target-id="<?= $fu['user_id']?>"
                data-share-type="post"
                data-share-value="">Send</button>
      </div>
    <?php endforeach;?>
    <?php if (empty($followingUsers)): ?>
      <div class="share-empty">You are not following anyone yet.</div>
    <?php endif;?>
  </div>
  <button id="repostBtn" style="width:100%;padding:10px;margin-top:15px;border:none;border-radius:6px;background:#6a1b9a;color:#fff;font-weight:600;cursor:pointer;">
    Repost
  </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ── Settings ── */
function toggleSettings(){const m=document.getElementById('settingsMenu');m.style.display=m.style.display==='block'?'none':'block';}
document.addEventListener('click',e=>{const m=document.getElementById('settingsMenu'),ic=document.querySelector('.menu-icon');if(!m.contains(e.target)&&!ic.contains(e.target))m.style.display='none';});

/* ── Fullscreen ── */
function showFullImage(t){
  const p=document.getElementById('fullscreenPopup'),i=document.getElementById('fullscreenImage');
  const path=t==='background'?"<?= htmlspecialchars($background_image)?>":"<?= htmlspecialchars($profile_image)?>";
  i.src=path;i.style.display='block';
  document.getElementById('fullscreenVideo').style.display='none';
  p.style.display='flex';
}
function showFullImageFromPath(path){
  const p=document.getElementById('fullscreenPopup'),i=document.getElementById('fullscreenImage'),v=document.getElementById('fullscreenVideo');
  i.style.display='none';v.style.display='none';
  if(/\.(mp4|webm|ogg)$/i.test(path)){v.src=path;v.style.display='block';v.play();}
  else{i.src=path;i.style.display='block';}
  p.style.display='flex';
}
function closeFullImage(){const p=document.getElementById('fullscreenPopup'),v=document.getElementById('fullscreenVideo');p.style.display='none';v.pause();v.currentTime=0;}

/* ── Sidebar helpers ── */
function openSidebar(id){
  document.querySelectorAll('.share-sidebar').forEach(s=>s.classList.remove('open'));
  document.getElementById(id).classList.add('open');
}
function closeSidebar(id){document.getElementById(id).classList.remove('open');}
document.addEventListener('click',e=>{
  if(!e.target.closest('.share-sidebar')&&!e.target.closest('#profileShareBtn')&&!e.target.closest('.share-post-btn'))
    document.querySelectorAll('.share-sidebar').forEach(s=>s.classList.remove('open'));
});

/* ══════════════════════════════════════════════
   SHARE SEND — works for both profile and post
══════════════════════════════════════════════ */
function sendShare(targetUserId, shareType, shareValue, btn) {
  /* shareType = 'profile' → sends profile link as a chat message
     shareType = 'post'    → sends post via send_message.php with shared_post_id */
  btn.textContent = '…';
  btn.disabled    = true;

  let body;
  if (shareType === 'profile') {
    // Send a text message with the profile URL
    const profileUrl = window.location.origin + '/project/public_profile.php?user_id=' + shareValue;
    body = `receiver_id=${encodeURIComponent(targetUserId)}&message=${encodeURIComponent('Check out this profile: ' + profileUrl)}`;
    fetch('send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(r=>r.json())
    .then(d=>{
      if(d.status==='sent'||d.status==='success'){
        btn.textContent='Sent ✓'; btn.classList.add('sent');
      } else {
        btn.textContent='Send'; btn.disabled=false;
        alert(d.msg||'Could not send');
      }
    }).catch(()=>{btn.textContent='Send';btn.disabled=false;alert('Network error');});

  } else {
    // Share post — uses send_message.php with shared_post_id
    const postId = document.getElementById('postShareSidebar').dataset.activePost;
    if (!postId) { btn.textContent='Send'; btn.disabled=false; alert('No post selected'); return; }
    body = `receiver_id=${encodeURIComponent(targetUserId)}&shared_post_id=${encodeURIComponent(postId)}&message=`;
    fetch('send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(r=>r.json())
    .then(d=>{
      if(d.status==='sent'||d.status==='success'){
        btn.textContent='Sent ✓'; btn.classList.add('sent');
      } else {
        btn.textContent='Send'; btn.disabled=false;
        alert(d.msg||'Could not send');
      }
    }).catch(()=>{btn.textContent='Send';btn.disabled=false;alert('Network error');});
  }
}

/* Wire all static send buttons */
document.querySelectorAll('.send-share-btn').forEach(btn=>{
  btn.addEventListener('click', e=>{
    e.stopPropagation();
    sendShare(btn.dataset.targetId, btn.dataset.shareType, btn.dataset.shareValue, btn);
  });
});

/* Wire search-result send buttons dynamically */
function wireSearchSendBtns(container, shareType){
  container.querySelectorAll('.send-share-btn').forEach(btn=>{
    btn.dataset.shareType  = shareType;
    btn.dataset.shareValue = shareType==='profile' ? '<?= $user_id?>' : '';
    btn.addEventListener('click', e=>{
      e.stopPropagation();
      sendShare(btn.dataset.targetId, btn.dataset.shareType, btn.dataset.shareValue, btn);
    });
  });
}

/* ── Profile share button ── */
document.getElementById('profileShareBtn').addEventListener('click', e=>{
  e.stopPropagation();
  // Reset all sent states when reopening
  document.querySelectorAll('#profileShareSidebar .send-share-btn').forEach(b=>{b.textContent='Send';b.disabled=false;b.classList.remove('sent');});
  openSidebar('profileShareSidebar');
});

/* ── Post share buttons ── */
document.querySelectorAll('.share-post-btn').forEach(btn=>{
  btn.addEventListener('click', e=>{
    e.stopPropagation();
    const postId = btn.dataset.postId;
    document.getElementById('postShareSidebar').dataset.activePost = postId;
    // Update all send buttons in this sidebar with current post's ID context
    document.querySelectorAll('#postShareSidebar .send-share-btn').forEach(b=>{
      b.dataset.shareType  = 'post';
      b.dataset.shareValue = postId;
      b.textContent='Send'; b.disabled=false; b.classList.remove('sent');
    });
    openSidebar('postShareSidebar');
  });
});

/* ── Live search in profile share sidebar ── */
document.getElementById('profileShareSearch').addEventListener('input', function(){
  const q=this.value.trim();
  const res=document.getElementById('profileShareResults');
  document.getElementById('profileShareList').style.display = q ? 'none' : '';
  if(!q){res.innerHTML='';return;}
  fetch('search_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`query=${encodeURIComponent(q)}`})
  .then(r=>r.text()).then(html=>{
    res.innerHTML=html;
    // Inject send buttons into search results
    res.querySelectorAll('[data-user-id]').forEach(el=>{
      if(!el.querySelector('.send-share-btn')){
        const b=document.createElement('button');
        b.className='send-share-btn';b.textContent='Send';
        b.dataset.targetId=el.dataset.userId;
        b.dataset.shareType='profile';
        b.dataset.shareValue='<?= $user_id?>';
        b.addEventListener('click',e2=>{e2.stopPropagation();sendShare(b.dataset.targetId,'profile','<?= $user_id?>',b);});
        el.appendChild(b);
      }
    });
  }).catch(()=>{res.innerHTML='<p class="text-danger">Search failed</p>';});
});

/* ── Live search in post share sidebar ── */
document.getElementById('postShareSearch').addEventListener('input', function(){
  const q=this.value.trim();
  const res=document.getElementById('postShareResults');
  document.getElementById('postShareList').style.display = q ? 'none' : '';
  if(!q){res.innerHTML='';return;}
  fetch('search_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`query=${encodeURIComponent(q)}`})
  .then(r=>r.text()).then(html=>{
    res.innerHTML=html;
    const postId=document.getElementById('postShareSidebar').dataset.activePost||'';
    res.querySelectorAll('[data-user-id]').forEach(el=>{
      if(!el.querySelector('.send-share-btn')){
        const b=document.createElement('button');
        b.className='send-share-btn';b.textContent='Send';
        b.dataset.targetId=el.dataset.userId;
        b.dataset.shareType='post';
        b.dataset.shareValue=postId;
        b.addEventListener('click',e2=>{e2.stopPropagation();sendShare(b.dataset.targetId,'post',postId,b);});
        el.appendChild(b);
      }
    });
  }).catch(()=>{res.innerHTML='<p class="text-danger">Search failed</p>';});
});

/* ── Repost ── */
document.getElementById('repostBtn').addEventListener('click',()=>{
  const postId=document.getElementById('postShareSidebar').dataset.activePost;
  if(!postId){alert('No post selected.');return;}
  const caption=prompt('Add a caption (optional):','')||'';
  fetch('repost_backend.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`post_id=${postId}&caption=${encodeURIComponent(caption)}`})
  .then(r=>r.json()).then(d=>alert(d.status==='success'?'Post reposted!':'Failed to repost.'))
  .catch(()=>alert('Error reposting.'));
});

/* ══════════════════════
   LIKE / SAVE / COMMENTS
══════════════════════ */
document.querySelectorAll('.like-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    fetch('interact_post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=like&post_id=${btn.dataset.postId}`})
    .then(r=>r.json()).then(d=>{
      const i=btn.querySelector('i');
      if(d.status==='liked'){i.classList.replace('far','fas');i.style.color='red';}
      if(d.status==='unliked'){i.classList.replace('fas','far');i.style.color='';}
    });
  });
});

document.querySelectorAll('.save-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    fetch('interact_post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=save&post_id=${btn.dataset.postId}`})
    .then(r=>r.json()).then(d=>{
      const i=btn.querySelector('i');
      if(d.status==='saved'){i.classList.replace('far','fas');i.style.color='green';}
      if(d.status==='unsaved'){i.classList.replace('fas','far');i.style.color='';}
    });
  });
});

document.querySelectorAll('.comment-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const postId=btn.dataset.postId;
    const box=document.getElementById(`comments-${postId}`);
    const add=box.nextElementSibling;
    const opening=box.classList.contains('d-none');
    box.classList.toggle('d-none');
    add.classList.toggle('d-none');
    if(opening&&!box.dataset.loaded){
      fetch('load_comments.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`post_id=${postId}`})
      .then(r=>r.text()).then(html=>{
        box.innerHTML=html||"<p class='text-muted'>No comments yet.</p>";
        box.dataset.loaded='1';
      }).catch(()=>{box.innerHTML="<p class='text-danger'>Failed to load.</p>";box.dataset.loaded='1';});
    }
  });
});

document.querySelectorAll('.comment-submit').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const postId=btn.dataset.postId;
    const input=document.querySelector(`.comment-input[data-post-id="${postId}"]`);
    const text=input.value.trim();
    if(!text)return;
    btn.disabled=true;
    fetch('comment_post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`post_id=${postId}&comment=${encodeURIComponent(text)}`})
    .then(r=>r.json()).then(d=>{
      if(d.status==='success'){
        const box=document.getElementById(`comments-${postId}`);
        box.classList.remove('d-none');
        box.dataset.loaded='1';
        box.insertAdjacentHTML('beforeend',d.html);
        box.scrollTop=box.scrollHeight;
        input.value='';
      } else alert(d.msg||'Failed to comment');
    }).finally(()=>{btn.disabled=false;});
  });
});

/* Delete comment */
document.addEventListener('click',e=>{
  const btn=e.target.closest('.delete-comment');
  if(!btn)return;
  const cid=parseInt(btn.dataset.commentId,10);
  if(!cid){alert('Cannot identify comment.');return;}
  if(!confirm('Delete this comment?'))return;
  fetch('delete_comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'comment_id='+cid})
  .then(r=>r.json()).then(d=>{
    if(d.status==='success')btn.closest('.comment').remove();
    else alert(d.msg||'Could not delete');
  }).catch(()=>alert('Network error'));
});

/* Three-dot post menu */
document.querySelectorAll('.menu-btn').forEach(btn=>{
  btn.addEventListener('click',e=>{
    e.stopPropagation();
    const menu=btn.nextElementSibling;
    menu.style.display=menu.style.display==='block'?'none':'block';
  });
});
window.addEventListener('click',()=>document.querySelectorAll('.menu-dropdown').forEach(m=>m.style.display='none'));

/* Delete post */
document.querySelectorAll('.delete-post').forEach(link=>{
  link.addEventListener('click',e=>{
    e.preventDefault();e.stopPropagation();
    const postId=link.dataset.postId;
    if(!confirm('Delete this post?'))return;
    fetch('delete_post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`post_id=${postId}`})
    .then(r=>r.json()).then(d=>{
      if(d.status==='success')document.querySelector(`.post[data-post-id="${postId}"]`).parentElement.remove();
      else alert(d.msg||'Could not delete');
    }).catch(()=>alert('Network error'));
  });
});
</script>

<?php include 'chat_panel.php'; ?>

</body>
</html>