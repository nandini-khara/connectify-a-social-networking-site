<?php
/**
 * send_message_file.php
 * Handles file uploads for chat messages.
 * Expects POST: receiver_id (int), file (uploaded file), file_type (media|audio|document|voice)
 */

/* ── Always return JSON, never let PHP errors leak as HTML ── */
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

/* ── Buffer output so any stray echo/warning doesn't break JSON ── */
ob_start();

function fail(string $msg, string $status = 'error'): never {
    ob_end_clean();
    echo json_encode(['status' => $status, 'msg' => $msg]);
    exit();
}

/* ── Session & auth ── */
session_start();
if (!isset($_SESSION['user_id'])) {
    fail('Not logged in');
}

require 'connect.php';

$sender_id   = (int)$_SESSION['user_id'];
$receiver_id = (int)($_POST['receiver_id'] ?? 0);
$file_type   = trim($_POST['file_type'] ?? 'media');

/* ── Basic validation ── */
if ($receiver_id <= 0) {
    fail('Missing receiver_id');
}
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension',
    ];
    $code = $_FILES['file']['error'] ?? -1;
    fail($uploadErrors[$code] ?? 'Upload error code ' . $code);
}

/* ── Block check ── */
$bq = $con->prepare(
    "SELECT 1 FROM blocks
     WHERE (blocker_id=? AND blocked_id=?)
        OR (blocker_id=? AND blocked_id=?)
     LIMIT 1"
);
$bq->bind_param('iiii', $sender_id, $receiver_id, $receiver_id, $sender_id);
$bq->execute();
if ($bq->get_result()->num_rows > 0) {
    fail('Blocked', 'blocked');
}

/* ── Create upload directory ── */
$uploadDir = 'uploads/chat_files/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        fail('Cannot create upload directory');
    }
}

/* ── Validate extension ── */
$file    = $_FILES['file'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = [
    // images
    'jpg','jpeg','png','gif','webp','bmp',
    // video
    'mp4','mov','webm','avi','mkv',
    // audio
    'mp3','ogg','wav','m4a','aac',
    // documents
    'pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip','rar',
];
if (!in_array($ext, $allowed, true)) {
    fail('File type .' . $ext . ' is not allowed');
}

/* ── Size limit: 50 MB ── */
if ($file['size'] > 50 * 1024 * 1024) {
    fail('File too large (max 50 MB)');
}

/* ── Move file ── */
$filename = uniqid('cf_', true) . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    fail('Failed to move uploaded file — check folder permissions on ' . $uploadDir);
}

/* ── Insert message ── */
// Format understood by load_messages.php: [TYPE:path]
$allowed_types = ['media', 'audio', 'document', 'voice'];
if (!in_array($file_type, $allowed_types, true)) {
    $file_type = 'media';
}
/*
$message = '[' . strtoupper($file_type) . ':' . $dest . ']';

$stmt = $con->prepare(
    "INSERT INTO messages (sender_id, receiver_id, message, created_at, is_read)
     VALUES (?, ?, ?, NOW(), 0)"
);
$stmt->bind_param('iis', $sender_id, $receiver_id, $message);*/
$stmt = $con->prepare(
    "INSERT INTO messages 
    (sender_id, receiver_id, message_text, media_path, message_type, status, created_at)
    VALUES (?, ?, ?, ?, ?, 'sent', NOW())"
);

$emptyText = null; // no text for file message
$stmt->bind_param(
    'iisss',
    $sender_id,
    $receiver_id,
    $emptyText,
    $dest,
    $file_type
);

if (!$stmt->execute()) {
    // File uploaded but DB failed — clean up
    @unlink($dest);
    fail('Database error: ' . $con->error);
}

ob_end_clean();
echo json_encode([
    'status' => 'sent',
    'path'   => $dest,
    'type'   => $file_type,
]);