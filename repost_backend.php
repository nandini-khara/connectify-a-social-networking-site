<?php
session_start();
require 'connect.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'You must be logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. Get original post ID and caption
$original_post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$caption = isset($_POST['caption']) ? trim($_POST['caption']) : '';

if ($original_post_id <= 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid post ID.']);
    exit();
}

// 3. Get original post media
$stmt = $con->prepare("SELECT post_img, post_video FROM post WHERE id = ?");
$stmt->bind_param("i", $original_post_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Original post not found.']);
    exit();
}

$original = $result->fetch_assoc();
$post_img = $original['post_img'] ?? '';
$post_video = $original['post_video'] ?? '';

$stmt->close();

// 4. Insert new repost
$insert = $con->prepare("INSERT INTO post (user_id, post_text, post_img, post_video) VALUES (?, ?, ?, ?)");
$insert->bind_param("isss", $user_id, $caption, $post_img, $post_video);
$success = $insert->execute();
$insert->close();

if ($success) {
    echo json_encode(['status' => 'success', 'msg' => 'Post reposted!']);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Could not repost.']);
}

exit();
?>
