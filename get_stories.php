<?php
/**
 * get_stories.php
 * ob_start() is THE VERY FIRST LINE — fixes warnings leaking into JSON
 * Schema: stories(id, user_id, media_path, media_type, caption, music_url, music_name, bg_color, created_at, expires_at)
 * Schema: story_views(id, story_id, viewer_id, viewed_at)
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

$me = (int)$_SESSION['user_id'];

// Clean expired stories
$con->query("DELETE FROM stories WHERE expires_at < NOW()");

function isMutualOrSelf(mysqli $con, int $me, int $target): bool {
    if ($me === $target) return true;
    $s = $con->prepare("
        SELECT 1 FROM follows f1
        JOIN follows f2 ON f1.following_id = f2.follower_id
                       AND f2.following_id = f1.follower_id
        WHERE f1.follower_id = ? AND f1.following_id = ? LIMIT 1
    ");
    $s->bind_param("ii", $me, $target);
    $s->execute();
    return (bool)$s->get_result()->fetch_assoc();
}

/* ────────────────────────────────────────────
   SIDEBAR — returns all users with active stories
   that the current user can see (self + mutual friends)
   ──────────────────────────────────────────── */
if (!empty($_GET['sidebar'])) {
    $sql = "
        SELECT
            u.user_id,
            u.user_name,
            u.profile_image,
            COUNT(s.id)                                                        AS total,
            SUM(CASE WHEN sv.viewer_id IS NULL THEN 1 ELSE 0 END)             AS unseen
        FROM users u
        JOIN stories s ON s.user_id = u.user_id AND s.expires_at > NOW()
        LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.viewer_id = ?
        WHERE u.user_id = ?
           OR (
               EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = u.user_id)
               AND EXISTS(SELECT 1 FROM follows WHERE follower_id = u.user_id AND following_id = ?)
           )
        GROUP BY u.user_id, u.user_name, u.profile_image
        ORDER BY unseen DESC, MAX(s.created_at) DESC
    ";
    $st = $con->prepare($sql);
    $st->bind_param("iiii", $me, $me, $me, $me);
    $st->execute();
    ob_end_clean();
    echo json_encode($st->get_result()->fetch_all(MYSQLI_ASSOC));
    exit();
}

/* ────────────────────────────────────────────
   OWN STORIES — used internally if needed
   ──────────────────────────────────────────── */
if (!empty($_GET['mine'])) {
    $sql = "
        SELECT
            s.id            AS story_id,
            s.user_id,
            s.media_path    AS file_path,
            s.media_type    AS file_type,
            s.caption       AS emojis,
            s.music_url     AS song_preview,
            s.music_name    AS song_title,
            ''              AS song_artist,
            0               AS song_start_sec,
            0               AS muted,
            0               AS vid_start_sec,
            15              AS vid_end_sec,
            s.expires_at,
            (SELECT COUNT(*) FROM story_views sv WHERE sv.story_id = s.id) AS view_count
        FROM stories s
        WHERE s.user_id = ? AND s.expires_at > NOW()
        ORDER BY s.created_at ASC
    ";
    $st = $con->prepare($sql);
    $st->bind_param("i", $me);
    $st->execute();
    ob_end_clean();
    echo json_encode($st->get_result()->fetch_all(MYSQLI_ASSOC));
    exit();
}

/* ────────────────────────────────────────────
   STORIES OF A SPECIFIC USER
   Called by openStoryView(userId, ...) in JS
   ──────────────────────────────────────────── */
$targetId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$targetId) {
    ob_end_clean();
    echo json_encode(['error' => 'Missing user_id']);
    exit();
}
if (!isMutualOrSelf($con, $me, $targetId)) {
    ob_end_clean();
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$sql = "
    SELECT
        s.id            AS story_id,
        s.user_id,
        s.media_path    AS file_path,
        s.media_type    AS file_type,
        s.caption       AS emojis,
        s.music_url     AS song_preview,
        s.music_name    AS song_title,
        ''              AS song_artist,
        0               AS song_start_sec,
        0               AS muted,
        0               AS vid_start_sec,
        15              AS vid_end_sec,
        s.expires_at,
        CASE WHEN sv.viewer_id IS NOT NULL THEN 1 ELSE 0 END AS seen_by_me,
        (SELECT COUNT(*) FROM story_views sv2 WHERE sv2.story_id = s.id) AS view_count
    FROM stories s
    LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.viewer_id = ?
    WHERE s.user_id = ? AND s.expires_at > NOW()
    ORDER BY s.created_at ASC
";
$st = $con->prepare($sql);
$st->bind_param("ii", $me, $targetId);
$st->execute();
$stories = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Record views (only when someone else views, not the owner) ── */
if (!empty($stories) && $targetId !== $me) {
    $mk = $con->prepare(
        "INSERT INTO story_views (story_id, viewer_id, viewed_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE viewed_at = viewed_at"
    );
    foreach ($stories as $s) {
        $sid = (int)$s['story_id'];
        $mk->bind_param("ii", $sid, $me);
        $mk->execute();
    }
}

ob_end_clean();
echo json_encode($stories);