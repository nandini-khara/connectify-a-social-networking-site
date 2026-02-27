<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) exit();
require_once 'connect.php';

$my_id = (int)$_SESSION['user_id'];

$result = $con->query("SELECT sender_id, COUNT(*) as cnt 
                       FROM messages 
                       WHERE receiver_id = $my_id AND seen = 0
                       GROUP BY sender_id");

$counts = [];
while ($row = $result->fetch_assoc()) {
    $counts[$row['sender_id']] = (int)$row['cnt'];
}

echo json_encode($counts);
?>