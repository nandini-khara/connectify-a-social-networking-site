<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['nid'])) {
    header("Location: index.php");
    exit();
}

$notif_id = intval($_GET['nid']);

// Fetch notification details
$stmt = $con->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $notif_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: notifications.php"); // fallback
    exit();
}

$row = $result->fetch_assoc();

// Mark it read
$update = $con->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
$update->bind_param("i", $notif_id);
$update->execute();

// Redirect based on type
switch ($row['type']) {
    case 'like':
    case 'comment':
        if ($row['post_id']) {
            $actionType = $row['type'] === 'like' ? 'liked' : 'commented';
            header("Location: view_post.php?post_id={$row['post_id']}&actor_id={$row['actor_id']}&action={$actionType}");
            exit();
        }
        break;

    case 'save':
    case 'repost':
        if ($row['post_id']) {
            header("Location: view_post.php?post_id={$row['post_id']}");
            exit();
        }
        break;

    case 'follow':
        header("Location: public_profile.php?user_id=" . $row['actor_id']);
        exit();

    default:
        header("Location: notifications.php");
        exit();
}
?>
