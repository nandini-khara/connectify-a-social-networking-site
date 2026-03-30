<?php
/**
 * notifications_frontend.php
 * Rich notification feed with:
 *  - Clickable actor avatar + name → public profile
 *  - Action description
 *  - Small post thumbnail (for post-related notifications)
 *  - Clicking notification → marks read → opens post (and highlights comment if type=comment)
 *  - Unread blue dot
 *  - Follow notifications (no post thumbnail)
 */
session_start();
require 'connect.php';
include 'getdark_mode.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ── Fetch user's mute preference ── */
$muteQ = $con->prepare("SELECT mute_msg_notifs FROM users WHERE user_id=? LIMIT 1");
$muteQ->bind_param('i', $user_id);
$muteQ->execute();
$muteRow   = $muteQ->get_result()->fetch_assoc();
$muteQ->close();
$muteMsgNotifs = (int)($muteRow['mute_msg_notifs'] ?? 0);

/* ── Fetch notifications with actor + post thumbnail ── */
$stmt = $con->prepare("
    SELECT
        n.id            AS notif_id,
        n.type,
        n.message,
        n.post_id,
        n.comment_id,
        n.is_read,
        n.created_at,
        u.user_id       AS actor_id,
        u.user_name     AS actor_username,
        u.full_name     AS actor_fullname,
        u.profile_image AS actor_avatar,
        p.post_img,
        p.post_video,
        p.post_text
    FROM notifications n
    JOIN users u ON u.user_id = n.actor_id
    LEFT JOIN post p ON p.id = n.post_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 100
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Mark all as read ── */
$mr = $con->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
$mr->bind_param("i", $user_id);
$mr->execute();
$mr->close();

/* ── Helpers ── */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return $diff . 's ago';
    if ($diff < 3600)   return floor($diff/60)   . 'm ago';
    if ($diff < 86400)  return floor($diff/3600)  . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

function notifIcon(string $type): string {
    return match($type) {
        'like'    => '❤️',
        'comment' => '💬',
        'follow'  => '👤',
        'save'    => '🔖',
        'share'   => '↗️',
        'mention' => '📣',
        'message' => '✉️',
        default   => '🔔',
    };
}

function notifVerb(string $type): string {
    return match($type) {
        'like'    => 'liked your post',
        'comment' => 'commented on your post',
        'follow'  => 'started following you',
        'save'    => 'saved your post',
        'share'   => 'shared your post',
        'mention' => 'mentioned you',
        'message' => 'sent you a message',
        default   => 'interacted with you',
    };
}

/* ── Build destination URL for each notification ── */
function notifUrl(array $n): string {
    if ($n['type'] === 'follow') {
        return 'notification_redirect.php?nid=' . $n['notif_id'];
    }
    // Message notification → open chat panel on home page with that person
    if ($n['type'] === 'message') {
        $chatUrl = 'home.php?chat_open=1&chat_uid=' . $n['actor_id']
                 . '&chat_name=' . urlencode($n['actor_fullname'] ?: $n['actor_username'])
                 . '&chat_img='  . urlencode($n['actor_avatar']   ?? '');
        return 'notification_redirect.php?nid=' . $n['notif_id'] . '&dest=' . urlencode($chatUrl);
    }
    if ($n['post_id']) {
        $url = 'view_post.php?post_id=' . $n['post_id']
             . '&actor_id=' . $n['actor_id']
             . '&action='   . urlencode($n['type']);
        if ($n['type'] === 'comment' && $n['comment_id']) {
            $url .= '&highlight_comment=' . $n['comment_id'];
        }
        return 'notification_redirect.php?nid=' . $n['notif_id'] . '&dest=' . urlencode($url);
    }
    return 'notification_redirect.php?nid=' . $n['notif_id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications | Connectify</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}

    body{
      min-height:100vh;
      background:<?= $dark_mode?'#111':'linear-gradient(135deg,#f0ecff 0%,#e8d5ff 50%,#f5f0ff 100%)'?>;
      color:<?= $dark_mode?'#eee':'#222'?>;
      padding:0 0 60px;
    }

    /* ── Top bar ── */
    .top-bar{
      position:sticky;top:0;z-index:100;
      display:flex;align-items:center;gap:12px;
      padding:14px 20px;
      background:<?= $dark_mode?'#1a1a1a':'rgba(255,255,255,.85)'?>;
      backdrop-filter:blur(12px);
      border-bottom:1px solid <?= $dark_mode?'#2a2a2a':'rgba(155,93,229,.15)'?>;
      box-shadow:0 2px 12px rgba(0,0,0,<?= $dark_mode?'.3':'.06'?>);
    }
    .top-bar .back-btn{
      background:linear-gradient(45deg,#6a1b9a,#ab47bc);
      color:#fff;border:none;padding:7px 16px;
      border-radius:20px;font-weight:600;font-size:.85rem;
      cursor:pointer;white-space:nowrap;
    }
    .top-bar h1{flex:1;font-size:1.2rem;font-weight:700;color:<?= $dark_mode?'#bb86fc':'#6a1b9a'?>;}
    .unread-count{
      background:linear-gradient(135deg,#9b5de5,#f15bb5);
      color:#fff;border-radius:20px;padding:3px 10px;
      font-size:.75rem;font-weight:700;
    }

    /* ── Notification list ── */
    .notif-list{max-width:600px;margin:0 auto;padding:16px 12px;}

    .notif-card{
      display:flex;align-items:center;gap:12px;
      padding:12px 14px;
      border-radius:16px;
      margin-bottom:8px;
      text-decoration:none;
      color:inherit;
      transition:background .18s,transform .12s;
      background:<?= $dark_mode?'#1e1e1e':'#fff'?>;
      box-shadow:0 2px 8px rgba(0,0,0,<?= $dark_mode?'.2':'.06'?>);
      border:1px solid <?= $dark_mode?'#2a2a2a':'rgba(155,93,229,.08)'?>;
      position:relative;
    }
    .notif-card:hover{
      transform:translateY(-1px);
      background:<?= $dark_mode?'#252525':'#faf5ff'?>;
      border-color:rgba(155,93,229,.3);
    }
    .notif-card.unread{
      background:<?= $dark_mode?'#1e1828':'#f5f0ff'?>;
      border-color:rgba(155,93,229,.25);
    }

    /* Unread dot */
    .unread-dot{
      position:absolute;top:12px;right:12px;
      width:8px;height:8px;border-radius:50%;
      background:linear-gradient(135deg,#9b5de5,#f15bb5);
      flex-shrink:0;
    }

    /* Actor avatar */
    .actor-wrap{position:relative;flex-shrink:0;}
    .actor-avatar{
      width:46px;height:46px;border-radius:50%;
      object-fit:cover;
      border:2px solid <?= $dark_mode?'rgba(155,93,229,.4)':'rgba(155,93,229,.3)'?>;
    }
    .notif-icon-badge{
      position:absolute;bottom:-2px;right:-2px;
      width:20px;height:20px;border-radius:50%;
      background:<?= $dark_mode?'#1e1e1e':'#fff'?>;
      display:flex;align-items:center;justify-content:center;
      font-size:11px;
      box-shadow:0 1px 4px rgba(0,0,0,.2);
    }

    /* Middle text */
    .notif-body{flex:1;min-width:0;}
    .notif-text{font-size:.87rem;line-height:1.4;}
    .notif-text .actor-name{
      font-weight:700;
      color:<?= $dark_mode?'#bb86fc':'#6a1b9a'?>;
    }
    .notif-time{
      font-size:.72rem;
      color:<?= $dark_mode?'#777':'#999'?>;
      margin-top:3px;
    }

    /* Post thumbnail */
    .post-thumb{
      width:52px;height:52px;border-radius:10px;
      object-fit:cover;flex-shrink:0;
      border:1px solid <?= $dark_mode?'#333':'rgba(0,0,0,.08)'?>;
    }
    .post-thumb-text{
      width:52px;height:52px;border-radius:10px;
      background:<?= $dark_mode?'#2a2a2a':'#f0ecff'?>;
      display:flex;align-items:center;justify-content:center;
      font-size:.65rem;color:<?= $dark_mode?'#888':'#9b5de5'?>;
      text-align:center;padding:4px;flex-shrink:0;
      border:1px solid <?= $dark_mode?'#333':'rgba(155,93,229,.2)'?>;
      overflow:hidden;
    }

    /* Date divider */
    .date-divider{
      text-align:center;font-size:.72rem;font-weight:600;
      color:<?= $dark_mode?'#555':'#bbb'?>;
      letter-spacing:.5px;text-transform:uppercase;
      margin:16px 0 8px;
    }

    /* Empty state */
    .empty-state{
      text-align:center;padding:60px 20px;
      color:<?= $dark_mode?'#555':'#bbb'?>;
    }
    .empty-state .empty-icon{font-size:3rem;margin-bottom:12px;}
    .empty-state p{font-size:.9rem;}
  </style>
</head>
<body>

<div class="top-bar">
  <button class="back-btn" onclick="history.back()">← Back</button>
  <h1>Notifications</h1>
  <div style="display:flex;align-items:center;gap:8px;">
    <?php
    $unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));
    if ($unreadCount > 0): ?>
      <span class="unread-count"><?= $unreadCount ?> new</span>
    <?php endif; ?>
    <!-- Mute message notifications toggle -->
    <button id="muteBtn" onclick="toggleMute()"
            title="<?= $muteMsgNotifs ? 'Message notifications are muted' : 'Mute message notifications' ?>"
            style="background:<?= $muteMsgNotifs?'rgba(255,100,100,.15)':'rgba(155,93,229,.12)'?>;
                   border:1px solid <?= $muteMsgNotifs?'rgba(255,100,100,.3)':'rgba(155,93,229,.3)'?>;
                   color:<?= $muteMsgNotifs?'#ff6b6b':'#9b5de5'?>;
                   border-radius:20px;padding:5px 12px;
                   font-size:.75rem;font-weight:600;cursor:pointer;
                   white-space:nowrap;">
      <?= $muteMsgNotifs ? '🔕 Unmute msgs' : '🔔 Mute msgs' ?>
    </button>
    <!-- Clear all notifications -->
    <?php if (!empty($notifications)): ?>
    <button id="clearAllBtn" onclick="clearAll()"
            title="Clear all notifications"
            style="background:rgba(255,59,59,.1);
                   border:1px solid rgba(255,59,59,.25);
                   color:#ff5c5c;
                   border-radius:20px;padding:5px 12px;
                   font-size:.75rem;font-weight:600;cursor:pointer;
                   white-space:nowrap;">
      🗑️ Clear all
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="notif-list">
  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <div class="empty-icon">🔔</div>
      <p>No notifications yet.<br>When someone likes, comments or follows you, it'll show up here.</p>
    </div>

  <?php else:
    $lastDate = null;
    foreach ($notifications as $n):
      /* Date grouping */
      $notifDate = date('Y-m-d', strtotime($n['created_at']));
      $today     = date('Y-m-d');
      $yesterday = date('Y-m-d', strtotime('-1 day'));

      if ($notifDate !== $lastDate):
        $lastDate = $notifDate;
        if ($notifDate === $today)     $label = 'Today';
        elseif ($notifDate === $yesterday) $label = 'Yesterday';
        else $label = date('M j, Y', strtotime($n['created_at']));
  ?>
      <div class="date-divider"><?= $label ?></div>
  <?php endif; ?>

      <!-- Notification card -->
      <a href="<?= notifUrl($n) ?>" class="notif-card <?= !$n['is_read'] ? 'unread' : '' ?>">

        <?php if (!$n['is_read']): ?>
          <div class="unread-dot"></div>
        <?php endif; ?>

        <!-- Actor avatar with type icon badge -->
        <div class="actor-wrap">
          <img class="actor-avatar"
               src="<?= htmlspecialchars($n['actor_avatar'] ?: 'uploads/default-profile.png') ?>"
               alt="<?= htmlspecialchars($n['actor_username']) ?>">
          <div class="notif-icon-badge"><?= notifIcon($n['type']) ?></div>
        </div>

        <!-- Text -->
        <div class="notif-body">
          <div class="notif-text">
            <span class="actor-name">@<?= htmlspecialchars($n['actor_username']) ?></span>
            <?= ' ' . notifVerb($n['type']) ?>
          </div>
          <?php if ($n['type'] === 'comment' && $n['message']): ?>
            <?php
              $commentPreview = preg_replace('/^.*?:\s*/i', '', $n['message']);
              $commentPreview = mb_substr(htmlspecialchars($commentPreview), 0, 60);
              if (strlen($n['message']) > 60) $commentPreview .= '…';
            ?>
            <div style="font-size:.78rem;color:<?= $dark_mode?'#888':'#888'?>;margin-top:2px;font-style:italic;">
              "<?= $commentPreview ?>"
            </div>
          <?php elseif ($n['type'] === 'message' && $n['message']): ?>
            <?php
              /* Show message preview — strip the "Sender: " prefix */
              $msgPreview = preg_replace('/^[^:]+:\s*/u', '', $n['message']);
              $msgPreview = mb_substr(htmlspecialchars($msgPreview), 0, 50);
              if (mb_strlen($n['message']) > 50) $msgPreview .= '…';
            ?>
            <div style="font-size:.78rem;color:<?= $dark_mode?'#888':'#888'?>;margin-top:2px;">
              <?= $msgPreview ?>
            </div>
          <?php endif; ?>
          <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
        </div>

        <!-- Post thumbnail (only for post-related notifications) -->
        <?php if ($n['post_id'] && $n['type'] !== 'follow'): ?>
          <?php if (!empty($n['post_img'])): ?>
            <img class="post-thumb"
                 src="<?= htmlspecialchars($n['post_img']) ?>"
                 alt="post">
          <?php elseif (!empty($n['post_video'])): ?>
            <div class="post-thumb-text">
              <span>▶ Video</span>
            </div>
          <?php elseif (!empty($n['post_text'])): ?>
            <div class="post-thumb-text">
              <?= htmlspecialchars(mb_substr($n['post_text'], 0, 30)) ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      </a>

  <?php endforeach; endif; ?>
</div>

<script>
function clearAll() {
  if (!confirm('Clear all notifications? This cannot be undone.')) return;
  const btn = document.getElementById('clearAllBtn');
  btn.textContent = '…';
  btn.disabled    = true;

  fetch('clear_notifications.php', { method: 'POST' })
  .then(r => r.json())
  .then(d => {
    if (d.status === 'success') {
      // Remove all notification cards from the DOM
      document.querySelectorAll('.notif-card, .date-divider').forEach(el => el.remove());
      // Show empty state
      const list = document.querySelector('.notif-list');
      list.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon">🔔</div>
          <p>No notifications yet.<br>When someone likes, comments or follows you, it'll show up here.</p>
        </div>`;
      btn.remove(); // remove the clear button itself
    } else {
      alert('Could not clear notifications. Please try again.');
      btn.textContent = '🗑️ Clear all';
      btn.disabled    = false;
    }
  })
  .catch(() => {
    alert('Network error.');
    btn.textContent = '🗑️ Clear all';
    btn.disabled    = false;
  });
}

let mutedState = <?= $muteMsgNotifs ?>;

function toggleMute() {
  const newMute = mutedState ? 0 : 1;
  const btn     = document.getElementById('muteBtn');
  btn.textContent = '…';
  btn.disabled    = true;

  fetch('toggle_msg_notifs.php', {
    method : 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body   : 'mute=' + newMute
  })
  .then(r => r.json())
  .then(d => {
    if (d.status === 'success') {
      mutedState = newMute;
      if (newMute) {
        btn.textContent  = '🔕 Unmute msgs';
        btn.style.background = 'rgba(255,100,100,.15)';
        btn.style.borderColor= 'rgba(255,100,100,.3)';
        btn.style.color      = '#ff6b6b';
        btn.title = 'Message notifications are muted';
      } else {
        btn.textContent  = '🔔 Mute msgs';
        btn.style.background = 'rgba(155,93,229,.12)';
        btn.style.borderColor= 'rgba(155,93,229,.3)';
        btn.style.color      = '#9b5de5';
        btn.title = 'Mute message notifications';
      }
    } else {
      alert('Could not update setting. Please try again.');
    }
    btn.disabled = false;
  })
  .catch(() => { alert('Network error.'); btn.disabled = false; });
}
</script>

</body>
</html>