<?php
/**
 * add_story.php
 * Schema: stories(id, user_id, media_path, media_type, caption, music_url, music_name, bg_color, created_at, expires_at)
 * NOTE: vid_start_sec / vid_end_sec / muted / song_start_sec do NOT exist as DB columns.
 *       They are stored in music_name and handled client-side only.
 */
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

function jsonExit(string $status, string $msg, array $extra = []): void {
    ob_end_clean();
    echo json_encode(array_merge(['status' => $status, 'msg' => $msg], $extra));
    exit();
}

if (!isset($_SESSION['user_id'])) {
    jsonExit('error', 'Not logged in');
}

require_once __DIR__ . '/connect.php';

if (!isset($con) || $con->connect_errno) {
    jsonExit('error', 'DB connection failed');
}

$user_id = (int)$_SESSION['user_id'];

/* ── Validate uploaded file ── */
if (empty($_FILES['story_file']) || $_FILES['story_file']['error'] !== UPLOAD_ERR_OK) {
    jsonExit('error', 'No file uploaded (error code: ' . ($_FILES['story_file']['error'] ?? 'none') . ')');
}

$file     = $_FILES['story_file'];
$mimeType = mime_content_type($file['tmp_name']);
$allowed  = [
    'image/jpeg','image/png','image/gif','image/webp',
    'video/mp4','video/webm','video/quicktime','video/x-msvideo'
];

if (!in_array($mimeType, $allowed)) {
    jsonExit('error', 'File type not allowed: ' . $mimeType);
}
if ($file['size'] > 40 * 1024 * 1024) {
    jsonExit('error', 'File too large (max 40 MB)');
}

$mediaType = (strpos($mimeType, 'video') === 0) ? 'video' : 'image';

/* ── Upload directory ── */
$uploadDir = __DIR__ . '/uploads/stories/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    jsonExit('error', 'Cannot create upload directory');
}

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: ($mediaType === 'video' ? 'mp4' : 'jpg');
$filename = 'story_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = $uploadDir . $filename;
$webPath  = 'uploads/stories/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    jsonExit('error', 'Failed to move uploaded file');
}

/* ── Map POST fields to DB columns ──
   caption   = emoji sticker string
   music_url  = iTunes 30-sec preview URL
   music_name = "Song Title — Artist" (combined, max 200 chars)
   bg_color   = always #000000 (no column for trim/mute — handled client-side)
── */
$caption   = mb_substr($_POST['emojis']        ?? '', 0, 300);
$musicUrl  = mb_substr($_POST['song_preview']  ?? '', 0, 500);

$musicName = '';
if (!empty($_POST['song_title'])) {
    $musicName = mb_substr(trim($_POST['song_title']), 0, 100);
    if (!empty($_POST['song_artist'])) {
        $musicName .= ' — ' . mb_substr(trim($_POST['song_artist']), 0, 95);
    }
}
$musicName = mb_substr($musicName, 0, 200);
$bgColor   = '#000000';

/* ── Insert into stories table ── */
$stmt = $con->prepare("
    INSERT INTO stories
        (user_id, media_path, media_type, caption, music_url, music_name, bg_color, expires_at)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
");

if (!$stmt) {
    @unlink($destPath);
    jsonExit('error', 'Prepare failed: ' . $con->error);
}

$stmt->bind_param('issssss',
    $user_id, $webPath, $mediaType, $caption, $musicUrl, $musicName, $bgColor
);

if (!$stmt->execute()) {
    @unlink($destPath);
    jsonExit('error', 'Execute failed: ' . $stmt->error);
}

$newId = $stmt->insert_id;

// Clean up any already-expired stories
$con->query("DELETE FROM stories WHERE expires_at < NOW()");

ob_end_clean();
echo json_encode([
    'status'     => 'success',
    'story_id'   => $newId,
    'file_path'  => $webPath,
    'media_type' => $mediaType,
]);