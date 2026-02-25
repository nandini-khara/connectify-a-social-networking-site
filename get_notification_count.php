<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['count' => 0]);
  exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $con->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['count' => (int)$result['cnt']]);
