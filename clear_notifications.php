<?php
/**
 * clear_notifications.php
 * Deletes ALL notifications for the logged-in user.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']);
    exit;
}

require 'connect.php';
$user_id = (int)$_SESSION['user_id'];

$stmt = $con->prepare("DELETE FROM notifications WHERE user_id = ?");
$stmt->bind_param('i', $user_id);

echo $stmt->execute()
    ? json_encode(['status'=>'success'])
    : json_encode(['status'=>'error','msg'=>'Could not clear notifications']);

$stmt->close();