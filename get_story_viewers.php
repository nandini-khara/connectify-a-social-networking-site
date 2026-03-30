<?php
/**
 * get_story_viewers.php
 * ob_start() is THE VERY FIRST LINE so no warning/notice can corrupt JSON output.
 */
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

require_once __DIR__ . '/connect.php';

$me      = (int)$_SESSION['user_id'];
$storyId = isset($_GET['story_id']) ? (int)$_GET['story_id'] : 0;

if (!$storyId) {
    ob_end_clean();
    echo json_encode(['error' => 'Missing story_id']);
    exit();
}

// Verify you own this story
$own = $con->prepare("SELECT id FROM stories WHERE id = ? AND user_id = ?");
$own->bind_param("ii", $storyId, $me);
$own->execute();
if (!$own->get_result()->fetch_assoc()) {
    ob_end_clean();
    echo json_encode(['error' => "Story id=$storyId not found or not yours (you=$me)"]);
    exit();
}

// Fetch viewers — COALESCE guards against any legacy NULL viewed_at rows
$st = $con->prepare("
    SELECT
        u.user_id,
        u.user_name,
        u.profile_image,
        COALESCE(sv.viewed_at, '') AS viewed_at
    FROM story_views sv
    JOIN users u ON u.user_id = sv.viewer_id
    WHERE sv.story_id = ?
    ORDER BY sv.viewed_at DESC, sv.id DESC
");
$st->bind_param("i", $storyId);
$st->execute();
$viewers = $st->get_result()->fetch_all(MYSQLI_ASSOC);

ob_end_clean();
echo json_encode(['viewers' => $viewers, 'count' => count($viewers)]);