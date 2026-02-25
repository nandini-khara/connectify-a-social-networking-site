<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthorized']);
    exit();
}

$user_id   = $_SESSION['user_id'];
$post_id   = $_POST['post_id'];
$recipients = json_decode($_POST['recipients'], true);

// You could insert a new row per recipient
$stmt = $con->prepare("INSERT INTO shares (sender_id, receiver_id, post_id, shared_at) VALUES (?, ?, ?, NOW())");

foreach ($recipients as $rid) {
    $stmt->bind_param("iii", $user_id, $rid, $post_id);
    $stmt->execute();
}

echo json_encode(['status' => 'success']);
?>
