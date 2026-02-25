<?php
session_start();
header('Content-Type: application/json');

if (
    !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id']) ||
    !isset($_POST['action']) || !isset($_POST['post_id'])
) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}
require 'connect.php';
require_once __DIR__ . '/lib/push_notification.php';

$user_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id']);
$action = $_POST['action'];

$response = ['status' => 'error'];

switch ($action) {
        case 'like':
        $check = $con->prepare("SELECT * FROM likes WHERE user_id = ? AND post_id = ?");
        $check->bind_param("ii", $user_id, $post_id);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $delete = $con->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
            $delete->bind_param("ii", $user_id, $post_id);
            $delete->execute();
            $response = ['status' => 'unliked'];
        } else {
            $insert = $con->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
            $insert->bind_param("ii", $user_id, $post_id);
            $insert->execute();
            $response = ['status' => 'liked'];

            // 🔔 Push like notification
            $ownerStmt = $con->prepare("SELECT user_id FROM post WHERE id = ?");
            $ownerStmt->bind_param("i", $post_id);
            $ownerStmt->execute();
            $ownerResult = $ownerStmt->get_result();
            $owner = $ownerResult->fetch_assoc();

            if ($owner && $owner['user_id'] != $user_id) {
                $actorName = $_SESSION['user_name'] ?? 'Someone';
                pushNotification(
                    $con,
                    $owner['user_id'],      // recipient
                    $user_id,               // actor
                    'like',                 // type
                    $post_id,
                    null,                   // no comment ID
                    "$actorName liked your post"
                );
            }
        }
        break;


    case 'save':
        $check = $con->prepare("SELECT * FROM saves WHERE user_id = ? AND post_id = ?");
        $check->bind_param("ii", $user_id, $post_id);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $delete = $con->prepare("DELETE FROM saves WHERE user_id = ? AND post_id = ?");
            $delete->bind_param("ii", $user_id, $post_id);
            $delete->execute();
            $response = ['status' => 'unsaved'];
        } else {
            $insert = $con->prepare("INSERT INTO saves (user_id, post_id) VALUES (?, ?)");
            $insert->bind_param("ii", $user_id, $post_id);
            $insert->execute();
            $response = ['status' => 'saved'];
        }
        break;

       case 'share':
        $insert = $con->prepare("INSERT INTO shares (user_id, post_id, shared_at) VALUES (?, ?, NOW())");
        $insert->bind_param("ii", $user_id, $post_id);
        $insert->execute();
        $response = ['status' => 'shared'];

        // 🔔 Push share notification
        $ownerStmt = $con->prepare("SELECT user_id FROM post WHERE id = ?");
        $ownerStmt->bind_param("i", $post_id);
        $ownerStmt->execute();
        $ownerResult = $ownerStmt->get_result();
        $owner = $ownerResult->fetch_assoc();

        if ($owner && $owner['user_id'] != $user_id) {
            $actorName = $_SESSION['user_name'] ?? 'Someone';
            pushNotification(
                $con,
                $owner['user_id'],
                $user_id,
                'share',
                $post_id,
                null,
                "$actorName shared your post"
            );
        }
        break;


    default:
        $response = ['status' => 'error', 'message' => 'Invalid action'];
}

echo json_encode($response);
?>
