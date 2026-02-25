<?php
session_start();
require 'connect.php';
require_once __DIR__ . '/lib/push_notification.php'; // ✅ Include notification logic

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in.']);
    exit();
}

$follower_id  = (int)$_SESSION['user_id'];
$following_id = (int)($_POST['target_user_id'] ?? 0);
$action       = $_POST['action'] ?? '';

if (!$following_id || !in_array($action, ['follow', 'unfollow'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit();
}

/* BLOCK GUARD (only applies to follow) */
if ($action === 'follow') {
    $check_block = $con->prepare(
        "SELECT 1 FROM blocks
         WHERE (blocker_id = ? AND blocked_id = ?)
            OR (blocker_id = ? AND blocked_id = ?)"
    );
    $check_block->bind_param("iiii",
        $following_id, $follower_id,
        $follower_id,  $following_id
    );
    $check_block->execute();
    if ($check_block->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error',
                          'message' => 'Follow not allowed — user is blocked']);
        exit();
    }
}

/* FOLLOW */
if ($action === 'follow') {
    $check = $con->prepare("SELECT 1 FROM follows
                            WHERE follower_id = ? AND following_id = ?");
    $check->bind_param("ii", $follower_id, $following_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Already following']);
        exit();
    }

    $stmt = $con->prepare("INSERT INTO follows (follower_id, following_id)
                           VALUES (?, ?)");
    $stmt->bind_param("ii", $follower_id, $following_id);

    if ($stmt->execute()) {
        // 🔔 Push follow notification
        $actorName = $_SESSION['user_name'] ?? 'Someone';
        if ($follower_id !== $following_id) {
            pushNotification(
                $con,
                $following_id,      // recipient
                $follower_id,       // actor
                'follow',           // type
                null,
                null,
                "$actorName started following you"
            );
        }

        echo json_encode(['status' => 'success', 'message' => 'Followed']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to follow']);
    }
    exit();
}

/* UNFOLLOW */
$stmt = $con->prepare("DELETE FROM follows
                       WHERE follower_id = ? AND following_id = ?");
$stmt->bind_param("ii", $follower_id, $following_id);
echo json_encode(
    $stmt->execute()
      ? ['status' => 'success', 'message' => 'Unfollowed']
      : ['status' => 'error',   'message' => 'Failed to unfollow']
);
