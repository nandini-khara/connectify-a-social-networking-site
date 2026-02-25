<?php
session_start();
require 'connect.php';
require_once 'lib/push_notification.php';

// Simulate a logged-in user
$_SESSION['user_id'] = 2; // Replace with a real user_id in your DB
$_SESSION['user_name'] = 'test_user'; // Optional, for message

$actor_id = $_SESSION['user_id'];
$recipient_id = 1; // Replace with someone else's user_id (not the actor)
$type = 'like';
$post_id = 10;      // Replace with a valid post ID
$comment_id = null;
$message = $_SESSION['user_name'] . " liked your post";

$success = pushNotification($con, $recipient_id, $actor_id, $type, $post_id, $comment_id, $message);

if ($success) {
    echo "✅ Notification inserted successfully.";
} else {
    echo "❌ Failed to insert notification.";
}
