<?php
session_start();  
require 'connect.php';

$query = $_POST['query'] ?? '';

if (trim($query) === '') {
  echo '<p>No search query.</p>';
  exit;
}
$currentUser = $_SESSION['user_id'] ?? 0; // ② NEW
$sql = "
  SELECT user_id, user_name, profile_image
    FROM users
   WHERE user_name LIKE CONCAT('%', ?, '%')
     /* Hide anyone blocked either way --------------------------- */
     AND NOT EXISTS (
           SELECT 1 FROM blocks b
            WHERE (b.blocker_id = ? AND b.blocked_id = users.user_id)
               OR (b.blocker_id = users.user_id AND b.blocked_id = ?)
         )
   LIMIT 10
";
$stmt = $con->prepare($sql);
$stmt->bind_param("sii", $query, $currentUser, $currentUser);   // ③ NEW
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
  while ($row = $res->fetch_assoc()) {
    $img = $row['profile_image'] ?: 'uploads/default-profile.png';
    $userLink = "public_profile.php?user_id=" . $row['user_id'];
$userName  = htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8');
    echo "<a href='$userLink' style='display:flex;align-items:center;padding:8px;border-bottom:1px solid #eee;text-decoration:none;color:#333;'>
      <img src='$img' alt='' style='width:30px;height:30px;border-radius:50%;margin-right:10px;object-fit:cover;'>
      @{$row['user_name']}
    </a>";
  }
} else {
  echo "<p style='padding:8px;'>No results found.</p>";
}
?>
