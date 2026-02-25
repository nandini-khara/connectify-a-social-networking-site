<?php
session_start();
require 'connect.php';
require_once 'lib/notifications.php'; // if using helper function

if (!isset($_SESSION['user_id'], $_POST['post_id'])) {
    http_response_code(400);
    echo "Invalid request";
    exit();
}

$reposter_id = $_SESSION['user_id'];
$post_id = intval($_POST['post_id']);

// First, find the original post owner
$stmt = $con->prepare("SELECT user_id FROM posts WHERE post_id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $original_user_id = $row['user_id'];

    if ($original_user_id != $reposter_id) {
        // Push notification only if not reposting own post
        pushNotification(
            $con,
            $original_user_id,      // recipient
            $reposter_id,           // actor
            'repost',               // type
            $post_id,               // post_id
            null,                   // comment_id
            'reposted your post'    // message
        );
    }
}

echo "success";
