<?php
// public_profile.php
session_start();
require 'connect.php';
include 'getdark_mode.php';
require_once 'privacy_check.php';

/* ── 1. Get user_id from URL first ─────────────────────────── */
if (!isset($_GET['user_id'])) {
    echo "User not selected.";
    exit();
}

$profile_user_id   = (int)$_GET['user_id'];
$logged_in_user_id = (int)($_SESSION['user_id'] ?? 0);
$viewer_id         = $logged_in_user_id;

/* ── 2. Fetch the profile user BEFORE the privacy check ────── */
$stmt = $con->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "User not found.";
    exit();
}

// $profile_user is what locked_profile.php expects
$profile_user = $user;

/* ── 3. Fetch counts (needed even for locked profile view) ─── */
$followers_stmt = $con->prepare("SELECT COUNT(*) AS c FROM follows WHERE following_id = ?");
$followers_stmt->bind_param("i", $profile_user_id);
$followers_stmt->execute();
$followers_count = (int)$followers_stmt->get_result()->fetch_assoc()['c'];
$followers_stmt->close();

$following_stmt = $con->prepare("SELECT COUNT(*) AS c FROM follows WHERE follower_id = ?");
$following_stmt->bind_param("i", $profile_user_id);
$following_stmt->execute();
$following_count = (int)$following_stmt->get_result()->fetch_assoc()['c'];
$following_stmt->close();

// Post count for locked profile
$pc_stmt = $con->prepare("SELECT COUNT(*) AS c FROM post WHERE user_id = ?");
$pc_stmt->bind_param("i", $profile_user_id);
$pc_stmt->execute();
$posts_count = (int)$pc_stmt->get_result()->fetch_assoc()['c'];
$pc_stmt->close();

/* ── 4. BLOCK CHECK — has this user blocked the viewer? ────── */
if ($logged_in_user_id) {
    $blk = $con->prepare("SELECT 1 FROM blocks WHERE blocker_id=? AND blocked_id=? LIMIT 1");
    $blk->bind_param("ii", $profile_user_id, $logged_in_user_id);
    $blk->execute();
    if ($blk->get_result()->num_rows > 0) {
        echo "<h2 style='text-align:center;margin-top:120px;font-family:Poppins,sans-serif'>
                This user has blocked you.
              </h2>";
        exit();
    }
    $blk->close();
}

/* ── 5. PRIVACY CHECK — now $profile_user exists ────────────── */
$canView = canViewProfile($con, $viewer_id, $profile_user_id);
if (!$canView) {
    include 'locked_profile.php';
    exit();
}

/* ── 6. Passed all checks — load full profile ───────────────── */
$profile_image    = !empty($user['profile_image'])    ? $user['profile_image']    : 'uploads/default-profile.png';
$background_image = !empty($user['background_image']) ? $user['background_image'] : 'uploads/default-background.jpg';

/* Is viewer following this profile? */
$isFollowing = false;
if ($logged_in_user_id && $logged_in_user_id !== $profile_user_id) {
    $fc = $con->prepare("SELECT 1 FROM follows WHERE follower_id=? AND following_id=? LIMIT 1");
    $fc->bind_param("ii", $logged_in_user_id, $profile_user_id);
    $fc->execute();
    $isFollowing = $fc->get_result()->num_rows > 0;
    $fc->close();
}

/* Is viewer blocked by this profile (for block button label) */
$isBlocked = false;
if ($logged_in_user_id) {
    $bc = $con->prepare("SELECT 1 FROM blocks WHERE blocker_id=? AND blocked_id=? LIMIT 1");
    $bc->bind_param("ii", $logged_in_user_id, $profile_user_id);
    $bc->execute();
    $isBlocked = $bc->get_result()->num_rows > 0;
    $bc->close();
}

/* Liked + saved posts of viewer */
$likedPosts = [];
$savedPosts = [];
if ($logged_in_user_id) {
    $ls = $con->prepare("SELECT post_id FROM likes WHERE user_id = ?");
    $ls->bind_param("i", $logged_in_user_id);
    $ls->execute();
    foreach ($ls->get_result()->fetch_all(MYSQLI_ASSOC) as $l)
        $likedPosts[(int)$l['post_id']] = true;
    $ls->close();

    $ss = $con->prepare("SELECT post_id FROM saves WHERE user_id = ?");
    $ss->bind_param("i", $logged_in_user_id);
    $ss->execute();
    foreach ($ss->get_result()->fetch_all(MYSQLI_ASSOC) as $s)
        $savedPosts[(int)$s['post_id']] = true;
    $ss->close();
}

/* Posts */
$posts = [];
$ps = $con->prepare("SELECT id AS post_id, post_img, post_text, post_video FROM post WHERE user_id = ? ORDER BY created_at DESC");
$ps->bind_param("i", $profile_user_id);
$ps->execute();
$posts = $ps->get_result()->fetch_all(MYSQLI_ASSOC);
$ps->close();

/* Following list for share sidebar */
$followingUsers = [];
if ($logged_in_user_id) {
    $fwQ = $con->prepare("SELECT u.user_id, u.full_name, u.profile_image FROM follows f JOIN users u ON u.user_id=f.following_id WHERE f.follower_id=?");
    $fwQ->bind_param("i", $logged_in_user_id);
    $fwQ->execute();
    $followingUsers = $fwQ->get_result()->fetch_all(MYSQLI_ASSOC);
    $fwQ->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($user['full_name']) ?> | Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
    body{<?= $dark_mode?'background:#121212;color:#eee;':'background:linear-gradient(120deg,#f0ecff,#fff);color:#333;'?>overflow-x:hidden;}
    .top-bar{position:sticky;top:0;width:100%;display:flex;justify-content:space-between;align-items:center;padding:10px 20px;z-index:100;background:<?= $dark_mode?'#121212':'#fff'?>;box-shadow:0 2px 5px rgba(0,0,0,.1);}
    .back-button{background:linear-gradient(45deg,#6a1b9a,#ab47bc);color:white;padding:8px 16px;border-radius:30px;font-weight:600;font-size:.95rem;cursor:pointer;border:none;}
    .menu-icon{background:<?= $dark_mode?'rgba(255,255,255,.1)':'rgba(255,255,255,.8)'?>;padding:8px 12px;border-radius:12px;cursor:pointer;font-weight:600;color:<?= $dark_mode?'#eee':'#333'?>;}
    .settings-dropdown{position:absolute;right:20px;top:55px;background:<?= $dark_mode?'#1e1e1e':'white'?>;border:1px solid <?= $dark_mode?'#2e2e2e':'#ddd'?>;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);display:none;padding:10px 15px;z-index:99;}
    .settings-dropdown button{background:none;border:none;padding:0;color:<?= $dark_mode?'#eee':'#333'?>;cursor:pointer;font-size:1rem;}
    .profile-header{position:relative;height:280px;background:url("<?= htmlspecialchars($background_image)?>") no-repeat center/cover;border-bottom-left-radius:40px;border-bottom-right-radius:40px;}
    .profile-img{position:absolute;bottom:-60px;left:50%;transform:translateX(-50%);width:120px;height:120px;border-radius:50%;border:5px solid #fff;object-fit:cover;box-shadow:0 5px 15px rgba(0,0,0,.15);}
    .profile-container{padding:80px 20px 40px;max-width:800px;margin:auto;}
    .name{text-align:center;font-size:1.8rem;font-weight:600;margin-top:20px;}
    .bio{text-align:center;font-size:1rem;color:<?= $dark_mode?'#aaa':'#666'?>;margin-top:5px;}
    .stats-follow{display:flex;justify-content:center;gap:40px;margin-top:20px;flex-wrap:wrap;}
    .stats-follow div{text-align:center;}
    .follow-btn{margin-top:10px;background:#6a1b9a;color:white;padding:8px 16px;border-radius:20px;border:none;cursor:pointer;font-weight:600;}
    .posts{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:20px;}
    .grid-item{display:flex;flex-direction:column;}
    .post{position:relative;width:100%;padding-top:100%;overflow:hidden;border-radius:12px;background:<?= $dark_mode?'#1e1e1e':'#ccc'?>;cursor:pointer;}
    .post img,.post video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
    .caption{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.5);color:white;font-size:.75rem;padding:4px 8px;text-align:center;}
    .post-actions{display:flex;gap:12px;justify-content:center;padding:6px 0;}
    .post-actions i{cursor:pointer;transition:transform .2s;color:<?= $dark_mode?'#ccc':'#333'?>;}
    .post-actions i:hover{transform:scale(1.2);}
    .post-actions .like-btn .fas.fa-heart{color:red!important;}
    .post-actions .save-btn .fas.fa-bookmark{color:green!important;}
    .fullscreen-popup{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.8);justify-content:center;align-items:center;z-index:9999;}
    .fullscreen-popup img,.fullscreen-popup video{max-width:90%;max-height:90%;border-radius:10px;}
    .comments{max-height:30vh;overflow-y:auto;overflow-x:hidden;padding-right:5px;word-wrap:break-word;overflow-wrap:anywhere;color:<?= $dark_mode?'#eee':'#333'?>;}
    .comments .c-avatar{width:22px;height:22px;border-radius:50%;object-fit:cover;margin-right:6px;}
    .c-body,.c-body *{font-size:.9rem;color:<?= $dark_mode?'#eee':'#333'?>!important;}
    .comment{display:flex;align-items:flex-start;gap:8px;margin-top:8px;}
    .add-comment{display:flex;align-items:center;gap:6px;width:100%;margin-top:8px;}
    .comment-input{flex:1;background:<?= $dark_mode?'#2a2a2a':''?>!important;border:1px solid <?= $dark_mode?'#3a3a3a':'#ccc'?>!important;color:<?= $dark_mode?'#fff':'#333'?>!important;}
    .comment-submit{margin-left:auto;background:none;border:none;padding:0;font-weight:600;color:#6a1b9a;cursor:pointer;}
    .delete-comment i{pointer-events:none;}
    /* Share sidebar */
    .share-sidebar{position:fixed;top:0;right:-300px;width:280px;height:100%;background:<?= $dark_mode?'#1e1e1e':'#fff'?>;border-left:2px solid <?= $dark_mode?'#2e2e2e':'#eee'?>;box-shadow:-2px 0 10px rgba(0,0,0,.1);padding:20px;transition:right .3s ease;z-index:8000;overflow-y:auto;color:<?= $dark_mode?'#eee':'#333'?>;}
    .share-sidebar.open{right:0;}
    .share-sidebar h5{font-weight:600;margin-bottom:15px;}
    .share-user{display:flex;align-items:center;margin-bottom:12px;gap:8px;}
    .share-user img{width:35px;height:35px;border-radius:50%;object-fit:cover;}
    .share-user span{flex:1;font-size:.85rem;}
    .share-user button{padding:4px 10px;border:none;border-radius:12px;background:#6a1b9a;color:white;font-size:.8rem;cursor:pointer;white-space:nowrap;}
  </style>
</head>
<body>

<div class="top-bar">
  <button class="back-button" onclick="location.href='home.php'">&#8592; Home</button>
  <div class="menu-icon" onclick="toggleSettings()">&#8942;</div>
  <div class="settings-dropdown" id="settingsDropdown">
    <button id="blockBtn" onclick="toggleBlock()">
      <?= $isBlocked ? 'Unblock' : 'Block' ?>
    </button>
  </div>
</div>

<div class="profile-header">
  <img src="<?= htmlspecialchars($profile_image)?>" class="profile-img" alt="">
</div>

<div class="fullscreen-popup" id="fullscreenPopup" onclick="closeFullImage()">
  <img id="fullscreenImage" style="display:none;" alt="">
  <video id="fullscreenVideo" controls style="display:none;"></video>
</div>

<div class="profile-container">
  <div class="name"><?= htmlspecialchars($user['full_name'])?></div>
  <div class="bio"><?= htmlspecialchars($user['Bio'])?></div>

  <div class="stats-follow">
    <div>
      <div style="font-weight:600;font-size:1.2rem;"><?= count($posts)?></div>
      <div>Posts</div>
    </div>
    <div>
      <a href="followers_list.php?user_id=<?= $profile_user_id?>" style="text-decoration:none;color:inherit;">
        <div style="font-weight:600;font-size:1.2rem;"><?= $followers_count?></div>
        <div>Followers</div>
      </a>
    </div>
    <div>
      <a href="following_list.php?user_id=<?= $profile_user_id?>" style="text-decoration:none;color:inherit;">
        <div style="font-weight:600;font-size:1.2rem;"><?= $following_count?></div>
        <div>Following</div>
      </a>
    </div>
    <?php if ($logged_in_user_id && $logged_in_user_id !== $profile_user_id): ?>
    <div>
      <button class="follow-btn" id="followBtn"
              data-following="<?= $isFollowing?'1':'0'?>"
              onclick="toggleFollow()">
        <?= $isFollowing ? 'Unfollow' : 'Follow' ?>
      </button>
    </div>
    <?php endif; ?>
  </div>

  <!-- Posts grid -->
  <div class="posts">
    <?php
    $gridSlots = 4;
    $chunks    = array_chunk($posts, $gridSlots);
    foreach ($chunks as $chunk):
        foreach ($chunk as $post):
            $mediaPath = !empty($post['post_img']) ? $post['post_img'] : ($post['post_video'] ?? '');
            $isVideo   = !empty($post['post_video']);
            $isLiked   = !empty($likedPosts[$post['post_id']]);
            $isSaved   = !empty($savedPosts[$post['post_id']]);
    ?>
      <div class="grid-item">
        <div class="post" data-post-id="<?= $post['post_id']?>"
             onclick="showFullImageFromPath('<?= htmlspecialchars($mediaPath)?>')">
          <?php if ($isVideo): ?>
            <video src="<?= htmlspecialchars($mediaPath)?>" muted></video>
          <?php elseif($mediaPath): ?>
            <img src="<?= htmlspecialchars($mediaPath)?>" alt="Post">
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
          <span class="share-btn" data-post-id="<?= $post['post_id']?>" style="cursor:pointer;">
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
          <button class="comment-submit" data-post-id="<?= $post['post_id']?>">Post</button>
        </div>
      </div>
    <?php
        endforeach;
        $empty = $gridSlots - count($chunk);
        for ($i = 0; $i < $empty; $i++)
            echo '<div style="visibility:hidden;"></div>';
    endforeach;
    if (empty($posts))
        echo '<p style="color:'.($dark_mode?'#888':'#999').';">No posts yet.</p>';
    ?>
  </div>
</div>

<!-- Share sidebar -->
<div class="share-sidebar" id="shareSidebar">
  <h5>Share with</h5>
  <input type="text" id="shareSearch" placeholder="Search users…"
         style="width:100%;padding:6px 10px;margin-bottom:10px;border:1px solid <?= $dark_mode?'#3a3a3a':'#ccc'?>;border-radius:6px;background:<?= $dark_mode?'#2a2a2a':'#fff'?>;color:<?= $dark_mode?'#fff':'#333'?>;">
  <div id="searchResults"></div>
  <?php foreach ($followingUsers as $fu):
    $fi = !empty($fu['profile_image']) ? htmlspecialchars($fu['profile_image']) : 'uploads/default-profile.png';
  ?>
    <div class="share-user">
      <img src="<?= $fi?>" alt="">
      <span><?= htmlspecialchars($fu['full_name'])?></span>
      <button class="share-send-btn" data-uid="<?= $fu['user_id']?>">Send</button>
    </div>
  <?php endforeach; ?>
  <button id="repostBtn" style="width:100%;padding:10px;margin-top:15px;border:none;border-radius:6px;background:#6a1b9a;color:#fff;font-weight:600;cursor:pointer;">Repost</button>
</div>

<script>
const PROFILE_USER_ID = <?= $profile_user_id ?>;

/* Settings dropdown */
function toggleSettings(){const d=document.getElementById('settingsDropdown');d.style.display=d.style.display==='block'?'none':'block';}
document.addEventListener('click',e=>{const d=document.getElementById('settingsDropdown'),ic=document.querySelector('.menu-icon');if(!d.contains(e.target)&&!ic.contains(e.target))d.style.display='none';});

/* Follow / Unfollow */
function toggleFollow(){
  const btn=document.getElementById('followBtn');
  const action=btn.dataset.following==='1'?'unfollow':'follow';
  fetch('follow_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`target_user_id=${PROFILE_USER_ID}&action=${action}`})
  .then(r=>r.json()).then(data=>{
    if(data.status==='followed'||data.status==='success'||action==='follow'){
      btn.textContent='Unfollow';btn.dataset.following='1';
    } else {
      btn.textContent='Follow';btn.dataset.following='0';
    }
  }).catch(()=>alert('Network error'));
}

/* Block / Unblock */
function toggleBlock(){
  const btn=document.getElementById('blockBtn');
  const action=btn.textContent.trim().toLowerCase();
  fetch('block_action.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`target_user_id=${PROFILE_USER_ID}&action=${action}`})
  .then(r=>r.json()).then(data=>{
    if(data.status==='success'){btn.textContent=action==='block'?'Unblock':'Block';}
    else alert(data.message||'Error');
  }).catch(()=>alert('Network error'));
}

/* Fullscreen */
function showFullImageFromPath(path){
  const p=document.getElementById('fullscreenPopup'),i=document.getElementById('fullscreenImage'),v=document.getElementById('fullscreenVideo');
  i.style.display='none';v.style.display='none';
  if(/\.(mp4|webm|ogg)$/i.test(path)){v.src=path;v.style.display='block';v.play();}
  else{i.src=path;i.style.display='block';}
  p.style.display='flex';
}
function closeFullImage(){const p=document.getElementById('fullscreenPopup'),v=document.getElementById('fullscreenVideo');p.style.display='none';v.pause();v.currentTime=0;}

/* Like / Save */
document.addEventListener('DOMContentLoaded',()=>{
  function toggleAction(btn,action){
    fetch('interact_post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`post_id=${btn.dataset.postId}&action=${action}`})
    .then(r=>r.json()).then(d=>{
      const i=btn.querySelector('i');
      if(action==='like'){if(d.status==='liked'){i.classList.replace('far','fas');i.style.color='red';}else{i.classList.replace('fas','far');i.style.color='';}}
      if(action==='save'){if(d.status==='saved'){i.classList.replace('far','fas');i.style.color='green';}else{i.classList.replace('fas','far');i.style.color='';}}
    });
  }
  document.querySelectorAll('.like-btn').forEach(b=>b.addEventListener('click',()=>toggleAction(b,'like')));
  document.querySelectorAll('.save-btn').forEach(b=>b.addEventListener('click',()=>toggleAction(b,'save')));
});

/* Comments */
document.querySelectorAll('.comment-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const postId=btn.dataset.postId,box=document.getElementById(`comments-${postId}`),add=box.nextElementSibling,opening=box.classList.contains('d-none');
    box.classList.toggle('d-none');add.classList.toggle('d-none');
    if(opening&&!box.dataset.loaded){
      fetch('load_comments.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`post_id=${postId}`})
      .then(r=>r.text()).then(h=>{box.innerHTML=h||"<p class='text-muted'>No comments yet.</p>";box.dataset.loaded='1';})
      .catch(()=>{box.innerHTML="<p class='text-danger'>Failed.</p>";box.dataset.loaded='1';});
    }
  });
});

document.querySelectorAll('.comment-submit').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const postId=btn.dataset.postId,input=document.querySelector(`.comment-input[data-post-id="${postId}"]`),text=input.value.trim();
    if(!text)return;
    btn.disabled=true;
    fetch('comment_post.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`post_id=${postId}&comment=${encodeURIComponent(text)}`})
    .then(r=>r.json()).then(d=>{
      if(d.status==='success'){const box=document.getElementById(`comments-${postId}`);box.classList.remove('d-none');box.dataset.loaded='1';box.insertAdjacentHTML('beforeend',d.html);box.scrollTop=box.scrollHeight;input.value='';}
      else alert(d.msg||'Failed');
    }).finally(()=>{btn.disabled=false;});
  });
});

document.addEventListener('click',e=>{
  const btn=e.target.closest('.delete-comment');if(!btn)return;
  const cid=parseInt(btn.dataset.commentId,10);if(!cid){alert('Cannot identify comment.');return;}
  if(!confirm('Delete?'))return;
  fetch('delete_comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'comment_id='+cid})
  .then(r=>r.json()).then(d=>{if(d.status==='success')btn.closest('.comment').remove();else alert(d.msg||'Error');})
  .catch(()=>alert('Network error'));
});

/* Share sidebar */
let activeSharePostId = null;
document.querySelectorAll('.share-btn').forEach(btn=>{
  btn.addEventListener('click',e=>{e.stopPropagation();activeSharePostId=btn.dataset.postId;document.getElementById('shareSidebar').classList.add('open');});
});
document.addEventListener('click',e=>{
  const s=document.getElementById('shareSidebar');
  if(!s.contains(e.target)&&!e.target.closest('.share-btn')&&s.classList.contains('open'))s.classList.remove('open');
});

document.getElementById('shareSearch').addEventListener('input',function(){
  const q=this.value.trim(),res=document.getElementById('searchResults');
  if(!q){res.innerHTML='';return;}
  fetch('search_users.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`query=${encodeURIComponent(q)}`})
  .then(r=>r.text()).then(h=>res.innerHTML=h).catch(()=>res.innerHTML='<p class="text-danger">Search failed</p>');
});

document.querySelectorAll('.share-send-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    if(!activeSharePostId){alert('No post selected');return;}
    fetch('send_message.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`receiver_id=${btn.dataset.uid}&shared_post_id=${activeSharePostId}&message=`})
    .then(r=>r.json()).then(d=>{if(d.status==='sent'){btn.textContent='Sent ✓';btn.disabled=true;}else alert('Could not send');})
    .catch(()=>alert('Network error'));
  });
});

document.getElementById('repostBtn').addEventListener('click',()=>{
  if(!activeSharePostId){alert('No post selected');return;}
  const caption=prompt('Add a caption (optional):','')||'';
  fetch('repost_backend.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`post_id=${activeSharePostId}&caption=${encodeURIComponent(caption)}`})
  .then(r=>r.json()).then(d=>alert(d.status==='success'?'Reposted!':'Failed to repost.'))
  .catch(()=>alert('Error'));
});
</script>

<?php include 'chat_panel.php'; ?>

</body>
</html>