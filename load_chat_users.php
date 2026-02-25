<?php
session_start();
require 'connect.php';

$user_id = $_SESSION['user_id'];

$sql = "
SELECT u.user_id, u.user_name, u.profile_image
FROM follows f
JOIN users u ON f.following_id = u.user_id
WHERE f.follower_id = ?
";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

while ($u = $res->fetch_assoc()):
  $img = $u['profile_image'] ?: 'default_profile.png';
?>
<div class="chat-user"
     data-user-id="<?= $u['user_id'] ?>"
     data-username="<?= htmlspecialchars($u['user_name']) ?>"
     data-profile="<?= htmlspecialchars($img) ?>">

  <img src="<?= $img ?>">
  <strong>@<?= htmlspecialchars($u['user_name']) ?></strong>
</div>

<?php endwhile; ?>
