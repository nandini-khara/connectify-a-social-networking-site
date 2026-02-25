<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $con->prepare(
    "SELECT n.*, u.user_name, u.profile_image
     FROM notifications n
     JOIN users u ON n.actor_id = u.user_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

// Format message with link
function formatNotificationMessage($row) {
    $username = '@' . htmlspecialchars($row['user_name']);
    $profileLink = 'public_profile.php?user_id=' . $row['actor_id'];
    $safeMsg = htmlspecialchars($row['message']);

    if (stripos($safeMsg, $row['user_name']) !== false) {
        $safeMsg = preg_replace('/' . preg_quote($row['user_name'], '/') . '/i', '', $safeMsg, 1);
    }

    return "<a href=\"$profileLink\" style=\"text-decoration:none; color:inherit; font-weight:bold;\">$username</a> $safeMsg";
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "$diff seconds ago";
    if ($diff < 3600) return floor($diff / 60) . " minutes ago";
    if ($diff < 86400) return floor($diff / 3600) . " hours ago";
    return date("F j", $time);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Notifications</title>
  <style>
    body {
      margin: 0;
      background: #f4f4f4;
      font-family: sans-serif;
      padding: 30px;
    }
    .card {
      max-width: 500px;
      margin: auto;
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .item {
      padding: 12px 0;
      border-bottom: 1px solid #eee;
    }
    .item:last-child {
      border-bottom: none;
    }
    .avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 10px;
    }
    .message {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    .time {
      font-size: 0.8rem;
      color: #777;
      margin-top: 4px;
    }
    a {
      text-decoration: none;
      color: inherit;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Notifications</h1>
    <ul class="list">
      <?php if ($notifications->num_rows === 0): ?>
        <li class="item">No notifications yet.</li>
      <?php else: ?>
        <?php while ($row = $notifications->fetch_assoc()): ?>
          <li class="item">
            <a href="notification_redirect.php?nid=<?= $row['id'] ?>" class="message">
              <img src="<?= htmlspecialchars($row['profile_image'] ?? 'uploads/default-profile.png') ?>" class="avatar" alt="">
              <div>
                <?= formatNotificationMessage($row) ?>
                <div class="time"><?= timeAgo($row['created_at']) ?></div>
              </div>
            </a>
          </li>
        <?php endwhile; ?>
      <?php endif; ?>
    </ul>
  </div>
</body>
</html>
