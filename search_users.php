<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) exit;

$me     = (int)$_SESSION['user_id'];     // current user
$search = trim($_POST['query'] ?? '');
if ($search === '') exit;

/* same query, plus NOT‑EXISTS to hide anyone blocked either way */
$sql = "
  SELECT user_id, full_name, profile_image
    FROM users
   WHERE full_name LIKE ?
     AND NOT EXISTS (
           SELECT 1 FROM blocks b
            WHERE (b.blocker_id = ? AND b.blocked_id = users.user_id)
               OR (b.blocker_id = users.user_id AND b.blocked_id = ?)
         )
   LIMIT 10
";
$stmt  = $con->prepare($sql);
$like  = "%{$search}%";
$stmt->bind_param('sii', $like, $me, $me);   // ← bind three params
$stmt->execute();
$result = $stmt->get_result();

if (!$result->num_rows) {
    echo '<p style="padding:8px;">No results found.</p>';
    exit;
}

while ($row = $result->fetch_assoc()) {
    $img  = $row['profile_image'] ?: 'uploads/default-profile.png';
    $name = htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8');

    echo "<div class='share-user'>
            <img src='".htmlspecialchars($img, ENT_QUOTES, 'UTF-8')."' alt=''>
            <span>$name</span>
            <button onclick=\"alert('Post shared with $name')\">Send</button>
          </div>";
}
?>
