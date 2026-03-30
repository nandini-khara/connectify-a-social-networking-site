<?php
/**
 * locked_profile.php
 * Shown when a visitor tries to view a private profile they don't have access to.
 * Include this file and then call exit() — do NOT redirect, so the URL stays clean.
 *
 * Expects these variables to already be set by the parent page:
 *   $profile_user   — the user array of the profile owner
 *   $dark_mode      — 0 or 1
 *   $followers_count, $following_count, $posts_count — optional counts
 *
 * Usage in public_profile.php:
 *   if (!$canView) {
 *       include 'locked_profile.php';
 *       exit();
 *   }
 */

$lp_name   = htmlspecialchars($profile_user['full_name']    ?? 'User');
$lp_uname  = htmlspecialchars($profile_user['user_name']    ?? '');
$lp_img    = htmlspecialchars($profile_user['profile_image'] ?? 'uploads/default-profile.png');
$lp_bio    = htmlspecialchars($profile_user['Bio']           ?? '');
$lp_posts  = (int)($posts_count      ?? 0);
$lp_fwrs   = (int)($followers_count  ?? 0);
$lp_fwing  = (int)($following_count  ?? 0);
$lp_dark   = (int)($dark_mode        ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $lp_name ?> | Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
    body{background:<?= $lp_dark?'#121212':'linear-gradient(120deg,#f0ecff,#fff)'?>;color:<?= $lp_dark?'#eee':'#333'?>;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:40px 20px;}

    .back-btn{align-self:flex-start;background:linear-gradient(45deg,#6a1b9a,#ab47bc);color:#fff;padding:8px 18px;border-radius:30px;font-weight:600;cursor:pointer;border:none;margin-bottom:30px;text-decoration:none;display:inline-block;}
    .back-btn:hover{opacity:.88;}

    .card{background:<?= $lp_dark?'#1e1e1e':'#fff'?>;border-radius:24px;padding:40px 32px;max-width:420px;width:100%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,<?= $lp_dark?'.5':'.1'?>);}

    .avatar{width:100px;height:100px;border-radius:50%;object-fit:cover;border:4px solid #9b5de5;margin-bottom:14px;}

    .name{font-size:1.35rem;font-weight:700;margin-bottom:4px;}
    .username{font-size:.88rem;color:<?= $lp_dark?'#888':'#999'?>;margin-bottom:8px;}
    .bio{font-size:.85rem;color:<?= $lp_dark?'#aaa':'#666'?>;margin-bottom:20px;line-height:1.5;}

    .stats{display:flex;justify-content:center;gap:32px;margin-bottom:24px;}
    .stat-val{font-size:1.2rem;font-weight:700;}
    .stat-lbl{font-size:.75rem;color:<?= $lp_dark?'#888':'#999'?>;}

    /* Lock icon box */
    .lock-box{background:<?= $lp_dark?'rgba(155,93,229,.1)':'rgba(155,93,229,.06)'?>;border:1px solid rgba(155,93,229,.25);border-radius:16px;padding:24px 20px;margin-bottom:20px;}
    .lock-icon{font-size:2.4rem;margin-bottom:10px;color:#9b5de5;}
    .lock-title{font-size:1rem;font-weight:700;margin-bottom:6px;color:<?= $lp_dark?'#ddd':'#333'?>;}
    .lock-sub{font-size:.82rem;color:<?= $lp_dark?'#888':'#777'?>;line-height:1.5;}

    .follow-btn{background:linear-gradient(135deg,#6a1b9a,#ab47bc);color:#fff;border:none;border-radius:20px;padding:10px 32px;font-size:.9rem;font-weight:700;cursor:pointer;transition:opacity .2s;width:100%;}
    .follow-btn:hover{opacity:.88;}
    .follow-btn.following{background:<?= $lp_dark?'#2a2a2a':'#f0f0f0'?>;color:<?= $lp_dark?'#eee':'#333'?>;border:1px solid <?= $lp_dark?'#444':'#ddd'?>;}
  </style>
</head>
<body>

  <a href="javascript:history.back()" class="back-btn">← Back</a>

  <div class="card">
    <img src="<?= $lp_img?>" class="avatar" alt="<?= $lp_name?>">
    <div class="name"><?= $lp_name?></div>
    <div class="username">@<?= $lp_uname?></div>
    <?php if ($lp_bio): ?><div class="bio"><?= $lp_bio?></div><?php endif; ?>

    <div class="stats">
      <div><div class="stat-val"><?= $lp_posts?></div><div class="stat-lbl">Posts</div></div>
      <div><div class="stat-val"><?= $lp_fwrs?></div><div class="stat-lbl">Followers</div></div>
      <div><div class="stat-val"><?= $lp_fwing?></div><div class="stat-lbl">Following</div></div>
    </div>

    <div class="lock-box">
      <div class="lock-icon"><i class="fas fa-lock"></i></div>
      <div class="lock-title">This account is private</div>
      <div class="lock-sub">
        Follow this account and wait for them to follow you back
        to see their photos and videos.
      </div>
    </div>

    <!-- Follow button — works even on locked profiles -->
    <?php
    // Check if viewer already follows this person
    $already_follows = false;
    if (isset($con) && isset($_SESSION['user_id'])) {
        $viewer = (int)$_SESSION['user_id'];
        $owner  = (int)($profile_user['user_id'] ?? 0);
        if ($owner && $viewer !== $owner) {
            $fchk = $con->prepare("SELECT 1 FROM follows WHERE follower_id=? AND following_id=? LIMIT 1");
            $fchk->bind_param('ii', $viewer, $owner);
            $fchk->execute();
            $already_follows = $fchk->get_result()->num_rows > 0;
            $fchk->close();
        }
    }
    ?>
    <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] !== (int)($profile_user['user_id']??0)): ?>
      <button class="follow-btn <?= $already_follows?'following':''?>"
              id="lpFollowBtn"
              data-user-id="<?= (int)($profile_user['user_id']??0)?>"
              data-following="<?= $already_follows?'1':'0'?>">
        <?= $already_follows ? 'Following' : 'Follow' ?>
      </button>
    <?php endif; ?>
  </div>

  <script>
    const followBtn = document.getElementById('lpFollowBtn');
    if (followBtn) {
      followBtn.addEventListener('click', () => {
        const userId     = followBtn.dataset.userId;
        const isFollowing= followBtn.dataset.following === '1';
        const action     = isFollowing ? 'unfollow' : 'follow';

        fetch('follow_action.php', {
          method : 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body   : `target_user_id=${userId}&action=${action}`
        })
        .then(r => r.json())
        .then(data => {
          if (data.status === 'followed') {
            followBtn.textContent       = 'Following';
            followBtn.dataset.following = '1';
            followBtn.classList.add('following');
          } else if (data.status === 'unfollowed') {
            followBtn.textContent       = 'Follow';
            followBtn.dataset.following = '0';
            followBtn.classList.remove('following');
          }
        })
        .catch(() => alert('Network error'));
      });
    }
  </script>

</body>
</html>