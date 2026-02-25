<?php
session_start();

function formatNotificationMessage($row) {
  $username = '@' . htmlspecialchars($row['user_name']);
  $profileLink = 'public_profile.php?user_id=' . $row['actor_id'];
  $safeMsg = htmlspecialchars($row['message']);

  // Remove duplicate username if present
  if (stripos($safeMsg, $row['user_name']) !== false) {
    $safeMsg = preg_replace('/' . preg_quote($row['user_name'], '/') . '/i', '', $safeMsg, 1);
  }

  // For 'follow' notifications, the message should not be clickable
  if ($row['type'] === 'follow') {
    return "<a href=\"$profileLink\" style=\"text-decoration:none; color:inherit; font-weight:bold;\">$username</a> $safeMsg";
  }

  // For others, the message is clickable
  $messageLink = "view_post.php?post_id={$row['post_id']}&actor_id={$row['actor_id']}&action={$row['type']}";
  return "<a href=\"$profileLink\" style=\"text-decoration:none; color:inherit; font-weight:bold;\">$username</a> <a href=\"$messageLink\" style=\"text-decoration:none; color:inherit;\">$safeMsg</a>";
}


require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$markRead = $con->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$markRead->bind_param("i", $_SESSION['user_id']);
$markRead->execute();

// Fetch notifications (actor info)
$stmt = $con->prepare("
    SELECT n.*, u.user_name, u.profile_image
    FROM notifications n
    JOIN users u ON u.user_id = n.actor_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connectify – Notifications</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      background: radial-gradient(circle at 20% 20%, #d8b4ff 0%, #c084fc 35%, #a855f7 80%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Inter', sans-serif;
      color: #333;
    }
    .card {
      width: 380px;
      max-width: 92vw;
      background: #ffffffee;
      backdrop-filter: blur(6px);
      border-radius: 20px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      padding: 28px 32px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }
    h1 {
      margin: 0;
      font-size: 1.8rem;
      font-weight: 700;
      text-align: center;
      color: #6a1b9a;
    }
    .list {
      list-style: none;
      padding: 0;
      margin: 10px 0 0 0;
      max-height: 55vh;
      overflow-y: auto;
    }
    .item {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      padding: 12px 0;
      border-bottom: 1px solid #eee;
    }
    .item:last-child { border-bottom: none; }
    .avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
    }
    .message {
      flex: 1;
      font-size: 0.92rem;
      line-height: 1.35;
    }
    .time {
      font-size: 0.75rem;
      color: #777;
      margin-top: 4px;
    }
    .list::-webkit-scrollbar { width: 6px; }
    .list::-webkit-scrollbar-thumb {
      background: #c084fc;
      border-radius: 3px;
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
            <img src="<?= htmlspecialchars($row['profile_image'] ?: 'uploads/default-profile.png') ?>" class="avatar" alt="">
            <div class="message">
              <?= formatNotificationMessage($row) ?>
              <div class="time">
                <?= timeAgo($row['created_at']) ?>
              </div>
            </div>
          </li>
        <?php endwhile; ?>
      <?php endif; ?>
    </ul>
  </div>

</body>
</html>

<?php
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return "$diff seconds ago";
    if ($diff < 3600) return floor($diff / 60) . " minutes ago";
    if ($diff < 86400) return floor($diff / 3600) . " hours ago";
    if ($diff < 172800) return "Yesterday";

    return date("M j", $time);
}
?>
