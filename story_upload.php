<?php
/**
 * story_upload.php
 * Accepts: POST multipart with media file + optional caption/music_name/bg_color
 * Returns: JSON {status, story_id, media_path}
 * Stories expire after 24 hours automatically.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']); exit;
}

require 'connect.php';
require_once 'content_moderation.php';

$user_id    = (int)$_SESSION['user_id'];
$caption    = trim($_POST['caption']    ?? '');
$music_name = trim($_POST['music_name'] ?? '');
$bg_color   = preg_replace('/[^#a-fA-F0-9]/', '', $_POST['bg_color'] ?? '#000000');

// Moderate caption text
if ($caption !== '') {
    $check = moderateText($caption);
    if (!$check['ok']) {
        echo json_encode(['status'=>'error','msg'=>$check['reason']]); exit;
    }
}

// Validate file
if (empty($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status'=>'error','msg'=>'No file uploaded']); exit;
}

$file    = $_FILES['media'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$imgExts = ['jpg','jpeg','png','gif','webp'];
$vidExts = ['mp4','webm','mov'];
$allowed = array_merge($imgExts, $vidExts);

if (!in_array($ext, $allowed)) {
    echo json_encode(['status'=>'error','msg'=>'File type not allowed']); exit;
}

$type = in_array($ext, $imgExts) ? 'image' : 'video';

// AI moderation
$modCheck = moderateFile($file['tmp_name'], $type);
if (!$modCheck['ok']) {
    echo json_encode(['status'=>'error','msg'=>$modCheck['reason']]); exit;
}

// Save file
$dir = 'uploads/stories/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$newName = 'story_' . $user_id . '_' . time() . '.' . $ext;
$dest    = $dir . $newName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['status'=>'error','msg'=>'Upload failed']); exit;
}

// Insert into DB — expires in 24h
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
$stmt = $con->prepare("
    INSERT INTO stories (user_id, media_path, media_type, caption, music_name, bg_color, expires_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param('issssss', $user_id, $dest, $type, $caption, $music_name, $bg_color, $expires);

if ($stmt->execute()) {
    echo json_encode(['status'=>'success','story_id'=>$stmt->insert_id,'media_path'=>$dest,'type'=>$type]);
} else {
    unlink($dest); // clean up
    echo json_encode(['status'=>'error','msg'=>'DB error']);
}
$stmt->close();