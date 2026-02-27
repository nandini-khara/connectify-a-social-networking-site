<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) exit();
require_once 'connect.php';

$my_id     = (int)$_SESSION['user_id'];
$sender_id = (int)$_POST['sender_id'];

$con->query("UPDATE messages SET seen = 1 
             WHERE receiver_id = $my_id 
             AND sender_id = $sender_id 
             AND seen = 0");

echo json_encode(['status' => 'ok']);
?>