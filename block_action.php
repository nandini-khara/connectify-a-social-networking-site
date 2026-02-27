<?php
/*block_action.php*/
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$blocker_id = $_SESSION['user_id'];
$blocked_id = $_POST['target_user_id'] ?? null;
$action = $_POST['action'] ?? '';

if (!$blocked_id || $blocker_id == $blocked_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid target']);
    exit();
}

if ($action === 'block') {
    // 1️⃣ Insert block
    $stmt = $con->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $blocker_id, $blocked_id);
    $stmt->execute();

    // 2️⃣ Remove blocker -> blocked follow
    $stmt1 = $con->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt1->bind_param("ii", $blocker_id, $blocked_id);
    $stmt1->execute();

    // 3️⃣ Remove blocked -> blocker follow
    $stmt2 = $con->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
    $stmt2->bind_param("ii", $blocked_id, $blocker_id);
    $stmt2->execute();

    echo json_encode(['status' => 'success', 'message' => 'User blocked, follows removed']);
} elseif ($action === 'unblock') {
    $stmt = $con->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->bind_param("ii", $blocker_id, $blocked_id);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'message' => 'User unblocked']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
