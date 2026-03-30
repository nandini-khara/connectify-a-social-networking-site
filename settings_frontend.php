<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require 'connect.php';

$user_id = $_SESSION['user_id'];

/* Dark mode + privacy + last seen */
$dark_mode       = 0;
$is_private      = 0;
$hide_last_seen  = 0;
$dm_stmt = $con->prepare("SELECT dark_mode, is_private, hide_last_seen FROM users WHERE user_id = ?");
$dm_stmt->bind_param("i", $user_id);
$dm_stmt->execute();
$dm_row = $dm_stmt->get_result()->fetch_assoc();
$dm_stmt->close();
if ($dm_row) {
    $dark_mode      = (int)$dm_row['dark_mode'];
    $is_private     = (int)$dm_row['is_private'];
    $hide_last_seen = (int)$dm_row['hide_last_seen'];
}

$user_stmt = $con->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
if (!$user) die("User not found");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connectify Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;font-family:'Inter',sans-serif;margin:0;padding:0;}
    body{background-color:<?= $dark_mode?'#111':'#f9f9f9'?>;padding:2rem;color:<?= $dark_mode?'#eee':'#333'?>;}
    .settings-container{max-width:900px;margin:0 auto;background-color:<?= $dark_mode?'#1e1e1e':'#fff'?>;padding:2rem;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,<?= $dark_mode?'0.4':'0.05'?>);border:1px solid <?= $dark_mode?'#2e2e2e':'transparent'?>;}
    h2{margin-bottom:1.5rem;color:<?= $dark_mode?'#bb86fc':'#6a1b9a'?>;border-bottom:2px solid <?= $dark_mode?'#2e2e2e':'#eee'?>;padding-bottom:.5rem;}
    .section{margin-bottom:2rem;}
    .section h3{color:<?= $dark_mode?'#ddd':'#333'?>;margin-bottom:1rem;}
    .field{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid <?= $dark_mode?'#2e2e2e':'#eee'?>;}
    .field label{color:<?= $dark_mode?'#bbb':'#555'?>;font-weight:500;}
    .field span{color:<?= $dark_mode?'#eee':'#222'?>;}
    .toggle{display:flex;align-items:center;gap:.5rem;}
    .action-btn{background-color:#6a1b9a;color:white;border:none;padding:.5rem 1rem;border-radius:8px;cursor:pointer;margin-top:.5rem;transition:background .2s;}
    .action-btn:hover{background-color:#5e1690;}
    .danger-btn{background-color:#e53935;color:white;}
    .danger-btn:hover{background-color:#c62828;}
    .info-box{background:<?= $dark_mode?'#2a2a2a':'#f1f1f1'?>;padding:.75rem;border-radius:8px;margin-top:.5rem;font-size:.9rem;color:<?= $dark_mode?'#aaa':'#444'?>;border:1px solid <?= $dark_mode?'#3a3a3a':'transparent'?>;}
    .link-btn{text-decoration:none;background-color:#6a1b9a;color:white;padding:.5rem 1rem;border-radius:8px;font-weight:500;transition:background .2s;}
    .link-btn:hover{background-color:#5e1690;color:white;}

    /* Toggle switch */
    .switch{position:relative;display:inline-block;width:46px;height:24px;}
    .switch input{opacity:0;width:0;height:0;}
    .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:<?= $dark_mode?'#555':'#ccc'?>;transition:.4s;border-radius:34px;}
    .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background-color:white;transition:.4s;border-radius:50%;}
    .switch input:checked + .slider{background-color:#6a1b9a;}
    .switch input:checked + .slider:before{transform:translateX(22px);}

    /* Privacy badge shown next to label */
    .privacy-badge{display:inline-block;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:8px;vertical-align:middle;}
    .privacy-badge.public {background:rgba(45,200,100,.15);color:#2dc864;border:1px solid rgba(45,200,100,.4);}
    .privacy-badge.private{background:rgba(155,93,229,.15);color:#9b5de5;border:1px solid rgba(155,93,229,.4);}

    .field-sub{font-size:.78rem;color:<?= $dark_mode?'#777':'#999'?>;margin-top:3px;}

    /* Spinner shown while saving */
    .saving-indicator{font-size:.78rem;color:#9b5de5;display:none;margin-left:8px;}
  </style>
</head>
<body>
<div class="settings-container">
  <h2>Settings</h2>

  <!-- Profile Settings -->
  <div class="section">
    <h3>👤 Profile Settings</h3>
    <div class="field">
      <label>Edit Profile</label>
      <a href="editprofile_frontend.php" class="link-btn">Edit</a>
    </div>
  </div>

  <!-- User Activity -->
  <div class="section">
    <h3>📜 User Activity</h3>
    <div class="field">
      <label>Saved Posts</label>
      <button class="action-btn" onclick="window.location.href='saved_posts.php'">View</button>
    </div>
    <div class="field">
      <label>Your Comments</label>
      <button class="action-btn" onclick="window.location.href='my_comments.php'">View</button>
    </div>
    <div class="field">
      <label>Liked Posts</label>
      <button class="action-btn" onclick="window.location.href='liked_posts.php'">View</button>
    </div>
  </div>

  <!-- Privacy & Security -->
  <div class="section">
    <h3>🔐 Privacy & Security</h3>
    <div class="field">
      <label>Block/Unblock Users</label>
      <button class="action-btn" onclick="window.location.href='blocked_users.php'">Manage</button>
    </div>
    <div class="field">
      <label>Change Password</label>
      <a href="changepassword.php" class="link-btn">Change</a>
    </div>
  </div>

  <!-- Theme Preferences -->
  <div class="section">
    <h3>🎨 Theme Preferences</h3>
    <div class="field toggle">
      <label for="themeToggle">Dark Mode</label>
      <label class="switch">
        <input type="checkbox" id="themeToggle"
               <?= $dark_mode ? 'checked' : '' ?>>
        <span class="slider"></span>
      </label>
    </div>
  </div>

  <!-- ════════════ ACCOUNT PRIVACY SECTION ════════════ -->
  <div class="section">
    <h3>🔒 Account Privacy</h3>

    <div class="field toggle" style="flex-wrap:wrap;gap:6px;">
      <div>
        <label for="privacyToggle">
          Private Account
          <span class="privacy-badge <?= $is_private ? 'private' : 'public' ?>"
                id="privacyBadge">
            <?= $is_private ? '🔒 Private' : '🌐 Public' ?>
          </span>
          <span class="saving-indicator" id="privacySaving">saving…</span>
        </label>
        <div class="field-sub" id="privacyDesc">
          <?php if ($is_private): ?>
            Only your mutual followers can see your profile and posts.
          <?php else: ?>
            Everyone can see your profile and posts.
          <?php endif; ?>
        </div>
      </div>
      <label class="switch">
        <input type="checkbox" id="privacyToggle"
               <?= $is_private ? 'checked' : '' ?>>
        <span class="slider"></span>
      </label>
    </div>

    <div class="info-box" style="margin-top:10px;">
      <strong>🌐 Public:</strong> Anyone on Connectify can view your profile, posts, followers and following list.<br><br>
      <strong>🔒 Private:</strong> Only people who follow you AND you follow back (mutual followers) can see your profile and posts. Everyone else sees a locked profile page.
    </div>
  </div>

  <!-- Last Seen -->
  <div class="section">
    <h3>👁️ Last Seen &amp; Online Status</h3>
    <div class="field toggle" style="flex-wrap:wrap;gap:6px;">
      <div>
        <label for="lastSeenToggle">
          Hide Last Seen
          <span class="saving-indicator" id="lastSeenSaving">saving…</span>
        </label>
        <div class="field-sub" id="lastSeenDesc">
          <?php if ($hide_last_seen): ?>
            Nobody can see when you were last online.
          <?php else: ?>
            Mutual followers can see your last seen time.
          <?php endif; ?>
        </div>
      </div>
      <label class="switch">
        <input type="checkbox" id="lastSeenToggle"
               <?= $hide_last_seen ? 'checked' : '' ?>>
        <span class="slider"></span>
      </label>
    </div>
    <div class="info-box" style="margin-top:10px;">
      When enabled, nobody can see your last seen or online status — and you won't be able to see others' either.
    </div>
  </div>

  <!-- Account Control -->
  <div class="section">
    <h3>⚙️ Account Control</h3>
    <div class="field">
      <label>Delete Account</label>
      <button class="action-btn danger-btn"
              onclick="window.location.href='delete_frontend.php'">Delete</button>
    </div>
    <div class="field">
      <label>Logout</label>
      <button class="action-btn" onclick="window.location.href='logout_fe.php'">Logout</button>
    </div>
    <div class="info-box">Deleting your account is permanent and cannot be undone.</div>
  </div>

</div>

<script>
  /* ── Dark mode toggle ── */
  document.getElementById('themeToggle').addEventListener('change', function () {
    const isDark = this.checked ? 1 : 0;
    fetch('darkmode_backend.php', {
      method : 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body   : 'dark_mode=' + isDark
    }).then(() => location.reload());
  });

  /* ── Last seen toggle ── */
  document.getElementById('lastSeenToggle').addEventListener('change', function () {
    const hide       = this.checked ? 1 : 0;
    const desc       = document.getElementById('lastSeenDesc');
    const saving     = document.getElementById('lastSeenSaving');
    saving.style.display = 'inline';
    fetch('privacy_backend.php', {
      method : 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body   : 'hide_last_seen=' + hide
    })
    .then(r => r.json())
    .then(data => {
      saving.style.display = 'none';
      if (data.status === 'success') {
        desc.textContent = hide
          ? 'Nobody can see when you were last online.'
          : 'Mutual followers can see your last seen time.';
      } else {
        alert('Could not save. Please try again.');
        this.checked = !hide;
      }
    })
    .catch(() => { saving.style.display='none'; alert('Network error.'); this.checked=!hide; });
  });
  document.getElementById('privacyToggle').addEventListener('change', function () {
    const isPrivate   = this.checked ? 1 : 0;
    const badge       = document.getElementById('privacyBadge');
    const desc        = document.getElementById('privacyDesc');
    const savingSpan  = document.getElementById('privacySaving');

    savingSpan.style.display = 'inline';

    fetch('privacy_backend.php', {
      method : 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body   : 'is_private=' + isPrivate
    })
    .then(r => r.json())
    .then(data => {
      savingSpan.style.display = 'none';
      if (data.status === 'success') {
        if (isPrivate) {
          badge.textContent = '🔒 Private';
          badge.className   = 'privacy-badge private';
          desc.textContent  = 'Only your mutual followers can see your profile and posts.';
        } else {
          badge.textContent = '🌐 Public';
          badge.className   = 'privacy-badge public';
          desc.textContent  = 'Everyone can see your profile and posts.';
        }
      } else {
        // Revert toggle if save failed
        alert('Could not save privacy setting. Please try again.');
        document.getElementById('privacyToggle').checked = !this.checked;
      }
    })
    .catch(() => {
      savingSpan.style.display = 'none';
      alert('Network error. Please try again.');
      document.getElementById('privacyToggle').checked = !isPrivate;
    });
  });
</script>
</body>
</html>