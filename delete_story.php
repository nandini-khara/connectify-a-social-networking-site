<?php
/**
 * delete_story.php
 * ob_start() is THE VERY FIRST LINE so no warning/notice can corrupt JSON output.
 */
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'msg' => 'Not logged in']);
    exit();
}

require_once __DIR__ . '/connect.php';

$me      = (int)$_SESSION['user_id'];
$storyId = isset($_POST['story_id']) ? (int)$_POST['story_id'] : 0;

if (!$storyId) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'msg' => 'Invalid story ID']);
    exit();
}

// Verify ownership
$st = $con->prepare("SELECT user_id, media_path FROM stories WHERE id = ?");
$st->bind_param("i", $storyId);
$st->execute();
$story = $st->get_result()->fetch_assoc();

if (!$story) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'msg' => "Story id=$storyId not found"]);
    exit();
}
if ((int)$story['user_id'] !== $me) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'msg' => 'Not your story']);
    exit();
}

// Delete story_views rows first (safe even if no FK constraint)
$dv = $con->prepare("DELETE FROM story_views WHERE story_id = ?");
if ($dv) {
    $dv->bind_param("i", $storyId);
    $dv->execute();
}

// Delete the media file — handle Windows (XAMPP) paths safely
if (!empty($story['media_path'])) {
    // Normalize: replace any forward slash with the OS separator
    $rel  = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $story['media_path']), DIRECTORY_SEPARATOR);
    $full = __DIR__ . DIRECTORY_SEPARATOR . $rel;
    if (file_exists($full)) {
        @unlink($full);
    }
}

// Delete the story row
$del = $con->prepare("DELETE FROM stories WHERE id = ? AND user_id = ?");
$del->bind_param("ii", $storyId, $me);
$del->execute();

ob_end_clean();
if ($del->affected_rows > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'No rows deleted — already gone? ' . $con->error]);
}