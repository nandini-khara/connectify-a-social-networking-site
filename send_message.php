<?php
session_start();
require 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'], $_POST['receiver_id'])) {
    echo json_encode(['status' => 'error']);
    exit;
}

$sender_id      = (int) $_SESSION['user_id'];
$receiver_id    = (int) $_POST['receiver_id'];
$message        = trim($_POST['message'] ?? '');
$shared_post_id = !empty($_POST['shared_post_id']) ? (int)$_POST['shared_post_id'] : null;

// Must have message OR shared post
if ($message === '' && $shared_post_id === null) {
    echo json_encode(['status' => 'empty']);
    exit;
}

// Block check
$blockCheck = $con->prepare("
    SELECT 1 FROM blocks
    WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?)
");
$blockCheck->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$blockCheck->execute();
if ($blockCheck->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'blocked']);
    exit;
}

// Insert — using your REAL column name: message_text
$stmt = $con->prepare("
    INSERT INTO messages (sender_id, receiver_id, message_text, shared_post_id, status, created_at)
    VALUES (?, ?, ?, ?, 'sent', NOW())
");
$stmt->bind_param("iisi", $sender_id, $receiver_id, $message, $shared_post_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'sent']);
} else {
    echo json_encode(['status' => 'error', 'msg' => $con->error]);
}
?>