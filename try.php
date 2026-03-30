<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require 'connect.php';
include 'getdark_mode.php';

$user_id = $_SESSION['user_id'];

$dm_stmt = $con->prepare("SELECT dark_mode FROM users WHERE user_id = ?");
$dm_stmt->bind_param("i", $user_id);
$dm_stmt->execute();
$dm_row  = $dm_stmt->get_result()->fetch_assoc();
$is_dark = ($dm_row && $dm_row['dark_mode'] == 1);

/* ── Users I follow ── */
$followingMap = [];
$followStmt = $con->prepare("SELECT following_id FROM follows WHERE follower_id = ?");
$followStmt->bind_param("i", $user_id);
$followStmt->execute();
$followRes = $followStmt->get_result();
while ($f = $followRes->fetch_assoc()) {
  $followingMap[(int)$f['following_id']] = true;
}

/* ── Mutual friends (they follow me AND I follow them) — for Stories ── */
$mutualSql = "
  SELECT u.user_id, u.user_name, u.profile_image
  FROM follows f1
  JOIN follows f2 ON f1.following_id = f2.follower_id AND f2.following_id = f1.follower_id
  JOIN users u ON u.user_id = f1.following_id
  WHERE f1.follower_id = ?
  GROUP BY u.user_id
";
$mutualStmt = $con->prepare($mutualSql);
$mutualStmt->bind_param("i", $user_id);
$mutualStmt->execute();
$mutualFriends = $mutualStmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ── Fetch user ── */
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) die("User not found.");

/* ── Liked posts ── */
$likedPosts = [];
$likedStmt  = $con->prepare("SELECT post_id FROM likes WHERE user_id = ?");
$likedStmt->bind_param("i", $user_id);
$likedStmt->execute();
$likedRes = $likedStmt->get_result();
while ($l = $likedRes->fetch_assoc()) { $likedPosts[(int)$l['post_id']] = true; }

/* ── Saved posts ── */
$savedPosts = [];
$savedStmt  = $con->prepare("SELECT post_id FROM saves WHERE user_id = ?");
$savedStmt->bind_param("i", $user_id);
$savedStmt->execute();
$savedRes = $savedStmt->get_result();
while ($s = $savedRes->fetch_assoc()) { $savedPosts[(int)$s['post_id']] = true; }

/* ── Feed posts ── */
$postSql = "
  SELECT p.id AS post_id, p.user_id, p.post_text, p.post_img, p.post_video, p.created_at,
         u.user_name, u.profile_image
  FROM post AS p
  JOIN users AS u ON p.user_id = u.user_id
  WHERE NOT EXISTS (
    SELECT 1 FROM blocks b
    WHERE (b.blocker_id = ? AND b.blocked_id = p.user_id)
       OR (b.blocker_id = p.user_id AND b.blocked_id = ?)
  )
  ORDER BY p.created_at DESC
  LIMIT 10 OFFSET ?
";
$postStmt = $con->prepare($postSql);
$offset = 0;
$postStmt->bind_param("iii", $user_id, $user_id, $offset);
$postStmt->execute();
$postResult = $postStmt->get_result();

/* ── My profile image for stories ── */
$myAvatar = !empty($user['profile_image']) ? $user['profile_image'] : 'default_profile.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connectify Feed</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
* { box-sizing: border-box; font-family: 'Inter', sans-serif; }
body { margin: 0; background: #f5f5f5; display: flex; flex-direction: column; height: 100vh; }
header { background: #6a1b9a; color: white; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; position: relative; }
.searchbar { position: relative; }
.searchbar input { padding: .5rem .5rem .5rem 2rem; border-radius: 8px; border: none; width: 200px; }
.searchbar i { position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: #888; }
.icons { display: flex; gap: 1rem; font-size: 1.2rem; align-items: center; position: relative; cursor: pointer; }
.dropdown { position: absolute; top: 60px; right: 20px; background: white; color: black; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,.1); display: none; flex-direction: column; min-width: 150px; z-index: 10; }
.dropdown a { padding: .75rem 1rem; text-decoration: none; color: #333; }
.dropdown a:hover { background: #eee; }
.show-dropdown { display: flex !important; }
main { display: flex; flex: 1; overflow: hidden; }
.feed { flex: 2; padding: 1rem; overflow-y: auto; }
.post { background: white; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,.05); display: flex; flex-direction: column; }
.post p { max-height: none; overflow-y: visible; margin-bottom: 1rem; }
.post-header { display: flex; justify-content: space-between; align-items: center; }
.post-actions { display: flex; gap: 1.5rem; margin-top: .5rem; font-size: 1.3rem; cursor: pointer; }
.post img, .post video { max-width: 100%; height: auto; border-radius: 8px; margin-top: 10px; }
.post-header, .post-actions { flex-shrink: 0; }
.follow-btn { background: #6a1b9a; color: white; border: none; padding: .4rem .75rem; border-radius: 8px; cursor: pointer; }
#filePreview { margin-top: 10px; max-width: 100%; }
#filePreview video, #filePreview img { max-width: 100%; max-height: 300px; display: block; margin-top: 10px; }
.add-comment { display: flex; align-items: center; gap: 6px; width: 100%; margin-top: 8px; }
.comment-input { flex: 1; }
.comment-submit { margin-left: auto; background: none; border: none; padding: 0; font-weight: 600; color: #6a1b9a; }
.comment { display: flex; align-items: flex-start; gap: 8px; margin-top: 8px; }
.comment .c-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.comment-author { text-decoration: none; color: inherit; }
.c-body { font-size: .9rem; }
.comments { max-height: 200px; overflow-y: auto; margin-top: 10px; padding-right: 5px; }
.share-sidebar { position: fixed; top: 0; right: -300px; width: 280px; height: 100%; background-color: #fff; border-left: 2px solid #eee; box-shadow: -2px 0 10px rgba(0,0,0,.1); padding: 20px; transition: right .3s ease; z-index: 200; overflow-y: auto; }
.share-sidebar.open { right: 0; }
.share-sidebar h5 { font-weight: 600; margin-bottom: 15px; }
.share-user { display: flex; align-items: center; margin-bottom: 12px; }
.share-user img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin-right: 10px; }
.share-user button { margin-left: auto; padding: 4px 10px; border: none; border-radius: 12px; background-color: #6a1b9a; color: white; font-size: .8rem; cursor: pointer; }
.delete-comment i { pointer-events: none; }
.comment .delete-comment { flex-shrink: 0; align-self: flex-start; }
.right-sidebar { flex: 1; padding: 1rem; background: #f9f9f9; border-left: 1px solid #ddd; overflow-y: auto; }
.sidebar-card { background: #fff; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
.sidebar-card h5 { font-weight: 600; margin-bottom: .75rem; }
.post-date { margin-top: 6px; font-size: .8rem; color: #777; align-self: flex-end; }
@media (max-width: 992px) { .right-sidebar { display: none; } }

/* ══════════════════════════════════════════════
   STORIES SECTION (right sidebar — original position)
   ══════════════════════════════════════════════ */
.stories {
  background: white;
  padding: 0.75rem;
  border-radius: 10px;
  margin-bottom: 1rem;
}
.stories h3 { margin-top: 0; font-size: 1rem; }
.story-container {
  display: flex;
  gap: 0.75rem;
  overflow-x: auto;
  padding: 0.5rem 0;
  scrollbar-width: thin;
}
.sv-nav.prev { left: 0; pointer-events: none; }
.sv-nav.next { right: 0; pointer-events: none; }
.story-container::-webkit-scrollbar { height: 3px; }
.story-container::-webkit-scrollbar-thumb { background: #c49de0; border-radius: 3px; }
.sv-header {
  position: absolute; top: 18px; left: 0; right: 0;
  display: flex; align-items: center; gap: 8px;
  padding: 0 12px; z-index: 5;  /* Change from 3 to 5 */
}.sv-header button {
  position: relative;
  z-index: 10;
  pointer-events: all;
}
/* Story Viewer Overlay */
#storyViewer {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.92);
  z-index: 5000;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
#storyViewer.open { display: flex; }
.sv-wrap {
  position: relative;
  width: 360px; max-width: 96vw;
  background: #111;
  border-radius: 16px;
  overflow: hidden;
}
.sv-progress-bar {
  display: flex; gap: 3px;
  padding: 8px 10px 0;
  position: absolute; top: 0; left: 0; right: 0; z-index: 2;
}
.sv-seg {
  flex: 1; height: 3px;
  background: rgba(255,255,255,.35);
  border-radius: 2px;
  overflow: hidden;
}
.sv-seg-fill { height: 100%; background: #fff; width: 0; transition: width linear; }
.sv-media { width: 100%; max-height: 500px; object-fit: cover; display: block; }
.sv-header {
  position: absolute; top: 18px; left: 0; right: 0;
  display: flex; align-items: center; gap: 8px;
  padding: 0 12px; z-index: 3;
}
.sv-header img { width: 32px; height: 32px; border-radius: 50%; border: 2px solid #fff; }
.sv-header strong { color: #fff; font-size: .82rem; flex: 1; }
.sv-close {
  color: #fff; background: none; border: none;
  font-size: 22px; cursor: pointer; padding: 0;
}
.sv-overlay-info {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: 30px 14px 14px;
  background: linear-gradient(to top, rgba(0,0,0,.7), transparent);
  color: #fff; font-size: .8rem;
  display: flex; align-items: center; gap: 8px;
}
.sv-nav {
  position: absolute; top: 0; bottom: 0; width: 40%;
  cursor: pointer; z-index: 4;
}
.sv-nav.prev { left: 0; }
.sv-nav.next { right: 0; }

/* Story Upload Modal — full Instagram-like */
#storyUploadModal .modal-dialog { max-width: 540px; }
#storyUploadModal .modal-body { padding: 1rem; max-height: 80vh; overflow-y: auto; }
.story-opt-btn {
  display: flex; align-items: center; gap: 10px;
  padding: .75rem 1rem;
  background: #f5f0ff; border: 1.5px solid #c49de0;
  border-radius: 10px; cursor: pointer;
  font-weight: 600; color: #6a1b9a; transition: background .15s;
}
.story-opt-btn:hover { background: #ede0ff; }
.story-emoji-row { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
.story-emoji-row span { font-size: 22px; cursor: pointer; padding: 3px; border-radius: 6px; transition: background .12s; }
.story-emoji-row span:hover { background: #f0e0ff; }
.story-preview-wrap { position: relative; margin-top: 10px; text-align: center; }
.story-preview-wrap img,
.story-preview-wrap video { max-width: 100%; max-height: 200px; border-radius: 10px; display: block; margin: 0 auto; }
.story-section-label { font-size: .75rem; font-weight: 700; color: #6a1b9a; text-transform: uppercase; letter-spacing: .04em; margin: 10px 0 4px; }

/* Video trim slider */
.trim-wrap { background: #f5f0ff; border-radius: 10px; padding: 10px; margin-top: 6px; }
.trim-timeline { position: relative; height: 40px; background: #ddd; border-radius: 6px; overflow: hidden; margin-bottom: 6px; cursor: pointer; }
.trim-timeline canvas { position: absolute; inset: 0; width: 100%; height: 100%; }
.trim-handle { position: absolute; top: 0; bottom: 0; width: 4px; background: #6a1b9a; cursor: ew-resize; z-index: 2; border-radius: 2px; }
.trim-handle::after { content:''; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:14px; height:14px; background:#6a1b9a; border-radius:50%; border:2px solid #fff; }
.trim-selection { position: absolute; top: 0; bottom: 0; background: rgba(106,27,154,.25); border: 2px solid #6a1b9a; pointer-events: none; }
.trim-info { font-size: .73rem; color: #6a1b9a; text-align: center; font-weight: 600; }
.trim-limit-warn { font-size: .7rem; color: #e91e63; text-align: center; margin-top: 2px; display: none; }

/* Song search */
.song-search-wrap { position: relative; margin-top: 4px; }
.song-search-wrap input { width: 100%; padding: 7px 34px 7px 10px; border: 1.5px solid #c49de0; border-radius: 8px; font-size: .84rem; outline: none; }
.song-search-wrap input:focus { border-color: #6a1b9a; }
.song-search-clear { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #999; cursor: pointer; font-size: 16px; line-height: 1; padding: 0; }
.song-results { max-height: 200px; overflow-y: auto; margin-top: 4px; border: 1px solid #e8d8ff; border-radius: 8px; display: none; }
.song-result-item { display: flex; align-items: center; gap: 8px; padding: 7px 10px; cursor: pointer; transition: background .12s; border-bottom: 1px solid #f5f0ff; }
.song-result-item:last-child { border-bottom: none; }
.song-result-item:hover { background: #f5f0ff; }
.song-result-item img { width: 36px; height: 36px; border-radius: 5px; flex-shrink: 0; }
.song-result-info { flex: 1; min-width: 0; }
.song-result-title { font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.song-result-artist { font-size: .72rem; color: #888; }
.song-result-play { width: 28px; height: 28px; border-radius: 50%; background: #6a1b9a; border: none; color: #fff; cursor: pointer; font-size: 11px; flex-shrink: 0; }
.song-selected-card { display: none; align-items: center; gap: 8px; background: #f5f0ff; border: 1.5px solid #c49de0; border-radius: 8px; padding: 8px; margin-top: 6px; }
.song-selected-card img { width: 38px; height: 38px; border-radius: 5px; flex-shrink: 0; }
.song-selected-info { flex: 1; min-width: 0; }
.song-selected-title { font-size: .82rem; font-weight: 700; }
.song-selected-artist { font-size: .72rem; color: #888; }
.song-remove-btn { background: none; border: none; color: #e91e63; font-size: 18px; cursor: pointer; padding: 0; flex-shrink: 0; }
/* Song portion slider */
.song-trim-wrap { margin-top: 8px; background: #ede0ff; border-radius: 8px; padding: 8px; }
.song-trim-wrap label { font-size: .72rem; color: #6a1b9a; font-weight: 600; }
.song-trim-slider { width: 100%; accent-color: #6a1b9a; }
.song-trim-info { font-size: .7rem; color: #555; text-align: center; }

/* Selected emoji display */
#storyEmojiOverlay { font-size: 1.4rem; letter-spacing: 4px; min-height: 28px; margin-top: 4px; }

/* ══════════════════════════════════════════════
   CALENDAR SECTION
   ══════════════════════════════════════════════ */
.cal-card { background: #fff; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
.cal-card h5 { font-weight: 700; margin: 0 0 .6rem 0; color: #6a1b9a; font-size: .9rem; }
.cal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: .5rem; }
.cal-header button { background: none; border: none; color: #6a1b9a; font-size: 1rem; cursor: pointer; }
.cal-header span { font-weight: 600; font-size: .88rem; }
.cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; text-align: center; font-size: .72rem; }
.cal-day-label { color: #999; font-weight: 600; padding: 2px 0; }
.cal-cell {
  padding: 4px 2px; border-radius: 6px; cursor: pointer;
  transition: background .15s; position: relative;
  font-size: .75rem;
}
.cal-cell:hover { background: #f0e0ff; }
.cal-cell.today { background: #6a1b9a; color: #fff; font-weight: 700; border-radius: 50%; }
.cal-cell.has-event::after {
  content: '';
  position: absolute; bottom: 1px; left: 50%; transform: translateX(-50%);
  width: 4px; height: 4px; border-radius: 50%; background: #e91e63;
}
.cal-cell.other-month { color: #ccc; }
.event-list { margin-top: .6rem; max-height: 120px; overflow-y: auto; }
.event-item {
  display: flex; align-items: center; gap: 8px;
  padding: 5px 0; border-bottom: 1px solid #f0e0ff; font-size: .78rem;
}
.event-dot { width: 8px; height: 8px; border-radius: 50%; background: #6a1b9a; flex-shrink: 0; }
.event-item-text { flex: 1; }
.event-item-time { color: #6a1b9a; font-weight: 600; font-size: .72rem; white-space: nowrap; }
.event-del { background: none; border: none; color: #ccc; font-size: 12px; cursor: pointer; padding: 0; }
.event-del:hover { color: #e91e63; }
.cal-add-btn {
  width: 100%; margin-top: .5rem;
  padding: 6px; border: 1.5px dashed #c49de0;
  border-radius: 8px; background: none; color: #6a1b9a;
  font-size: .8rem; cursor: pointer; transition: background .15s;
}
.cal-add-btn:hover { background: #f5f0ff; }

/* Add Event Modal */
#addEventModal .form-control { font-size: .85rem; }
#addEventModal label { font-size: .8rem; font-weight: 600; color: #6a1b9a; }

/* ══════════════════════════════════════════════
   TO-DO LIST SECTION
   ══════════════════════════════════════════════ */
.todo-card { background: #fff; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
.todo-card h5 { font-weight: 700; margin: 0 0 .6rem 0; color: #6a1b9a; font-size: .9rem; }
.todo-add-row { display: flex; gap: 6px; margin-bottom: .5rem; }
.todo-add-row input {
  flex: 1; padding: 5px 10px;
  border: 1.5px solid #c49de0; border-radius: 8px;
  font-size: .82rem; outline: none;
}
.todo-add-row input:focus { border-color: #6a1b9a; }
.todo-time-input {
  width: 90px; padding: 5px 8px;
  border: 1.5px solid #c49de0; border-radius: 8px;
  font-size: .82rem; outline: none;
}
.todo-add-row button {
  padding: 5px 12px; background: #6a1b9a;
  color: white; border: none; border-radius: 8px;
  font-size: .82rem; cursor: pointer;
}
.todo-list { list-style: none; padding: 0; margin: 0; max-height: 200px; overflow-y: auto; }
.todo-item {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 0; border-bottom: 1px solid #f5f0ff;
  font-size: .82rem;
}
.todo-check { width: 16px; height: 16px; cursor: pointer; accent-color: #6a1b9a; }
.todo-text { flex: 1; }
.todo-text.done { text-decoration: line-through; color: #bbb; }
.todo-timer-badge {
  font-size: .68rem; color: #fff;
  background: #6a1b9a; border-radius: 6px;
  padding: 1px 6px; white-space: nowrap;
}
.todo-timer-badge.urgent { background: #e91e63; animation: pulse-badge .8s infinite alternate; }
@keyframes pulse-badge { from { opacity: 1; } to { opacity: .5; } }
.todo-del { background: none; border: none; color: #ddd; font-size: 12px; cursor: pointer; padding: 0; }
.todo-del:hover { color: #e91e63; }

/* ══════════════════════════════════════════════
   NOTIFICATION BANNER
   ══════════════════════════════════════════════ */
#notifBanner {
  position: fixed; top: 70px; right: 20px; z-index: 9000;
  background: #6a1b9a; color: white;
  border-radius: 12px; padding: 12px 18px;
  box-shadow: 0 8px 24px rgba(106,27,154,.4);
  display: none; flex-direction: column; gap: 4px;
  max-width: 300px; animation: slideInBanner .3s ease;
}
@keyframes slideInBanner { from { transform: translateX(320px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
#notifBanner .nb-title { font-weight: 700; font-size: .9rem; }
#notifBanner .nb-body  { font-size: .8rem; opacity: .88; }
#notifBanner .nb-close { position: absolute; top: 8px; right: 12px; background: none; border: none; color: white; font-size: 16px; cursor: pointer; }
</style>

<?php if ($is_dark): ?>
<style>
body { background: #111 !important; color: #fff !important; }
main { background: #111 !important; }
.feed { background: #111 !important; }
.post { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; color: #fff !important; }
.post p, .post strong { color: #fff !important; }
.post-date { color: #999 !important; }
.post-actions i { color: #ccc !important; }
.post-actions .like-btn .fas.fa-heart { color: red !important; }
.post-actions .save-btn .fas.fa-bookmark { color: green !important; }
.comment-input { background: #2a2a2a !important; border: 1px solid #3a3a3a !important; color: #fff !important; }
.comment-input::placeholder { color: #888 !important; }
.comment-submit { color: #bb86fc !important; background: none !important; }
.comments { background: transparent !important; color: #fff !important; }
.c-body, .c-body * { color: #fff !important; }
.right-sidebar { background: #111 !important; border-left: 1px solid #2e2e2e !important; }
.stories { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; }
.stories h3 { color: #fff !important; }
.cal-card, .todo-card, .sidebar-card { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; color: #fff !important; }
.cal-card h5, .todo-card h5 { color: #bb86fc !important; }
.cal-header span { color: #fff !important; }
.cal-day-label { color: #888 !important; }
.cal-cell { color: #ddd !important; }
.cal-cell.today { background: #7b2cbf !important; color: #fff !important; }
.cal-cell.other-month { color: #444 !important; }
.event-item { border-color: #2e2e2e !important; }
.event-item-text { color: #ddd !important; }
.todo-item { border-color: #2e2e2e !important; color: #ddd !important; }
.todo-add-row input, .todo-time-input { background: #2a2a2a !important; border-color: #3a3a3a !important; color: #fff !important; }
.share-sidebar { background: #1e1e1e !important; border-left: 1px solid #2e2e2e !important; color: #fff !important; }
.share-sidebar h5 { color: #fff !important; }
.share-user span { color: #fff !important; }
#shareSearch { background: #2a2a2a !important; border: 1px solid #3a3a3a !important; color: #fff !important; }
.searchbar input { background: #2a2a2a !important; color: #fff !important; }
#mainSearchResults { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; color: #fff !important; }
.dropdown { background: #1e1e1e !important; border: 1px solid #2e2e2e !important; }
.dropdown a { color: #fff !important; }
.dropdown a:hover { background: #2a2a2a !important; }
.modal-content { background: #1e1e1e !important; color: #fff !important; border: 1px solid #2e2e2e !important; }
.modal-header, .modal-footer { background: #1e1e1e !important; border-color: #2e2e2e !important; }
.modal-title { color: #fff !important; }
.close { color: #fff !important; text-shadow: none !important; }
.modal-body textarea { background: #2a2a2a !important; color: #fff !important; border: 1px solid #3a3a3a !important; }
</style>
<?php endif; ?>

</head>
<body>

<header>
  <div class="logo">Connectify</div>
  <div class="searchbar">
    <i class="fas fa-search"></i>
    <input type="text" placeholder="Search...">
  </div>
  <div class="icons">
    <span data-toggle="modal" data-target="#newPostModal" title="New Post"><i class="fas fa-plus-circle"></i></span>
    <span style="position: relative;">
      <i class="fas fa-bell" id="notifBell"></i>
      <span id="notifCount" style="position:absolute;top:-6px;right:-10px;background:red;color:white;font-size:10px;font-weight:bold;padding:2px 6px;border-radius:50%;display:none;"></span>
    </span>
    <span id="openChat"><i class="fas fa-comment-dots"></i></span>
    <span id="profileIcon"><i class="fas fa-user-circle"></i></span>
    <div class="dropdown" id="dropdownMenu">
      <a href="myprofile_frontend.php">My Profile</a>
      <a href="settings_frontend.php">Settings</a>
      <a href="logout_fe.php">Logout</a>
    </div>
  </div>
</header>

<div id="mainSearchResults" style="position:absolute;top:60px;left:50%;transform:translateX(-50%);background:#fff;border:1px solid #ccc;border-radius:8px;max-height:300px;overflow-y:auto;width:300px;z-index:1000;display:none;"></div>

<!-- Notification Banner -->
<div id="notifBanner">
  <button class="nb-close" onclick="document.getElementById('notifBanner').style.display='none'">✕</button>
  <div class="nb-title" id="nb-title">📅 Reminder</div>
  <div class="nb-body"  id="nb-body"></div>
</div>

<main>
  <section class="feed">

    <!-- ── POSTS ── -->
    <?php
    if ($postResult->num_rows > 0) {
      while ($row = $postResult->fetch_assoc()) {
        $isLiked = !empty($likedPosts[$row['post_id']]);
        $isSaved = !empty($savedPosts[$row['post_id']]);
        $likeCls  = $isLiked ? 'fas' : 'far';
        $likeSty  = $isLiked ? 'color:red;' : '';
        $saveCls  = $isSaved ? 'fas' : 'far';
        $saveSty  = $isSaved ? 'color:green;' : '';
        $profileLink  = ($row['user_id'] == $user_id) ? 'myprofile_frontend.php' : 'public_profile.php?user_id='.$row['user_id'];
        $userName     = htmlspecialchars($row['user_name']);
        $profileImage = !empty($row['profile_image']) ? $row['profile_image'] : 'default_profile.png';
        $postText     = nl2br(htmlspecialchars($row['post_text']));
        $postImg      = $row['post_img'];
        $postVideo    = $row['post_video'];
        $postDate     = date("d M Y, h:i A", strtotime($row['created_at']));
    ?>
    <div class="post" data-post-id="<?= $row['post_id'] ?>">
      <div class="post-header">
        <div style="display:flex;align-items:center;gap:10px;">
          <a href="<?= $profileLink ?>" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:10px;">
            <img src="<?= $profileImage ?>" alt="profile" style="width:40px;height:40px;border-radius:50%;">
            <strong>@<?= $userName ?></strong>
          </a>
        </div>
        <?php if ($row['user_id'] != $user_id):
              $isFollowing = !empty($followingMap[$row['user_id']]); ?>
          <button class="follow-btn" data-user-id="<?= $row['user_id'] ?>" data-following="<?= $isFollowing ? '1' : '0' ?>">
            <?= $isFollowing ? 'Unfollow' : 'Follow' ?>
          </button>
        <?php endif; ?>
      </div>
      <p><?= $postText ?></p>
      <?php if (!empty($postImg)): ?>
        <img src="<?= $postImg ?>" alt="post image" style="max-width:60%;margin-top:10px;border-radius:8px;">
      <?php endif; ?>
      <?php if (!empty($postVideo)): ?>
        <video controls style="max-width:80%;margin-top:10px;border-radius:8px;">
          <source src="<?= $postVideo ?>" type="video/mp4">
        </video>
      <?php endif; ?>
      <div class="post-actions">
        <span class="like-btn" data-post-id="<?= $row['post_id'] ?>">
          <i class="<?= $likeCls ?> fa-heart" style="<?= $likeSty ?>"></i>
        </span>
        <span class="comment-btn"><i class="far fa-comment"></i></span>
        <span class="share-btn" data-post-id="<?= $row['post_id'] ?>">
          <i class="bi bi-share-fill"></i>
        </span>
        <span class="save-btn" data-post-id="<?= $row['post_id'] ?>">
          <i class="<?= $saveCls ?> fa-bookmark" style="<?= $saveSty ?>"></i>
        </span>
      </div>
      <div id="comments-<?= $row['post_id'] ?>" class="comments d-none"></div>
      <div class="add-comment d-none">
        <input type="text" class="comment-input form-control" placeholder="Add a comment…" data-post-id="<?= $row['post_id'] ?>">
        <button class="comment-submit btn btn-link p-0" data-post-id="<?= $row['post_id'] ?>">Post</button>
      </div>
      <div class="post-date"><?= $postDate ?></div>
    </div>
    <?php }} else { echo "<p>No posts to show.</p>"; } ?>

  </section>

  <!-- ── RIGHT SIDEBAR ── -->
  <aside class="right-sidebar">

    <!-- ── STORIES ── -->
    <div class="stories">
      <h3>Stories</h3>
      <div class="story-container" id="storiesContainer">
        <!-- Populated by loadStorySidebar() on DOMContentLoaded -->
        <div style="font-size:.75rem;color:#999;padding:4px;">Loading…</div>
      </div>
    </div>

    <!-- ── CALENDAR ── -->
    <div class="cal-card">
      <h5>📅 Calendar</h5>
      <div class="cal-header">
        <button id="calPrev">&#8249;</button>
        <span id="calMonthYear"></span>
        <button id="calNext">&#8250;</button>
      </div>
      <div class="cal-grid" id="calGrid"></div>
      <div class="event-list" id="eventList"></div>
      <button class="cal-add-btn" data-toggle="modal" data-target="#addEventModal">+ Add Event</button>
    </div>

    <!-- ── TO-DO LIST ── -->
    <div class="todo-card">
      <h5>✅ To-Do List</h5>
      <div class="todo-add-row">
        <input type="text" id="todoInput" placeholder="Add a task…" />
        <input type="time" id="todoTime" class="todo-time-input" title="Set reminder time" />
        <button onclick="addTodo()">Add</button>
      </div>
      <ul class="todo-list" id="todoList"></ul>
    </div>

  </aside>
</main>

<!-- ══ STORY VIEWER ══ -->
<div id="storyViewer">
  <div class="sv-wrap" id="svWrap">

    <!-- Progress bar (one segment per story slide) -->
    <div class="sv-progress-bar" id="svProgressBar"></div>

    <!-- Header: avatar, name, time, mute, delete, close -->
    <div class="sv-header">
      <img id="svAvatar" src="" alt="">
      <div style="display:flex;flex-direction:column;flex:1;min-width:0;">
        <strong id="svName" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></strong>
        <small id="svTime" style="font-size:.65rem;color:rgba(255,255,255,.65);"></small>
      </div>
      <!-- Mute button (only for video) -->
      <button id="svMuteBtn" onclick="toggleStoryMute()" title="Mute/Unmute" style="display:none;background:rgba(0,0,0,.4);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:14px;margin-right:4px;">🔊</button>
      <!-- Delete button (only shown for own stories) -->
      <button id="svDeleteBtn" onclick="deleteCurrentStory()" title="Delete story" style="display:none;background:rgba(220,53,69,.7);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:14px;margin-right:4px;">🗑️</button>
      <!-- Viewers button (only shown for own stories) -->
      <button id="svViewersBtn" onclick="showStoryViewers()" title="Who viewed this" style="display:none;background:rgba(0,0,0,.4);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:14px;margin-right:6px;">👁️</button>
      <button class="sv-close" onclick="closeStoryView()">✕</button>
    </div>

    <!-- Tap zones: prev / next -->
    <div class="sv-nav prev" onclick="storyNav(-1)"></div>
    <div class="sv-nav next" onclick="storyNav(1)"></div>

    <!-- Media area -->
    <div id="svMedia" style="min-height:300px;"></div>

    <!-- Bottom info bar: song + emoji -->
    <div class="sv-overlay-info" id="svInfo"></div>

    <!-- Viewers drawer (slides up) -->
    <div id="svViewersDrawer" style="display:none;position:absolute;bottom:0;left:0;right:0;background:rgba(20,0,30,.92);border-radius:0 0 16px 16px;padding:14px;max-height:220px;overflow-y:auto;z-index:10;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <strong style="color:#fff;font-size:.85rem;">👁️ Viewers</strong>
        <button onclick="document.getElementById('svViewersDrawer').style.display='none'" style="background:none;border:none;color:#fff;font-size:16px;cursor:pointer;">✕</button>
      </div>
      <div id="svViewersList" style="font-size:.78rem;color:#ddd;"></div>
    </div>

  </div>
</div>

<!-- ══ STORY UPLOAD MODAL ══ -->
<div class="modal fade" id="storyUploadModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📸 Add Story</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">

        <!-- Step 1: Choose file -->
        <label class="story-opt-btn" for="storyFileInput" style="margin-bottom:0;">
          🖼️ Choose Photo / Video (max 15 sec for video)
          <input type="file" id="storyFileInput" accept="image/*,video/*" style="display:none" onchange="storyFileChosen(this)">
        </label>

        <!-- Preview -->
        <div class="story-preview-wrap" id="storyPreviewArea" style="display:none;"></div>

        <!-- Video trim section -->
        <div id="storyTrimSection" style="display:none;">
          <div class="story-section-label">✂️ Trim Video (max 15 seconds)</div>
          <div class="trim-wrap">
            <div class="trim-timeline" id="trimTimeline">
              <canvas id="trimCanvas"></canvas>
              <div class="trim-selection" id="trimSelection"></div>
              <div class="trim-handle" id="trimHandleL" style="left:0;"></div>
              <div class="trim-handle" id="trimHandleR" style="right:0;"></div>
            </div>
            <div class="trim-info" id="trimInfo">0.0s → 15.0s (15.0s)</div>
            <div class="trim-limit-warn" id="trimLimitWarn">⚠️ Maximum 15 seconds allowed</div>
          </div>
        </div>

        <!-- Mute toggle for video -->
        <div id="storyMuteRow" style="display:none;margin-top:8px;">
          <label style="display:flex;align-items:center;gap:8px;font-size:.82rem;cursor:pointer;">
            <input type="checkbox" id="storyMuteCheck"> 🔇 Mute video sound
          </label>
        </div>

        <!-- Emoji stickers -->
        <div class="story-section-label">🎨 Add Emoji Stickers</div>
        <div class="story-emoji-row">
          <span onclick="addStoryEmoji('❤️')">❤️</span><span onclick="addStoryEmoji('🔥')">🔥</span>
          <span onclick="addStoryEmoji('😂')">😂</span><span onclick="addStoryEmoji('🎉')">🎉</span>
          <span onclick="addStoryEmoji('😍')">😍</span><span onclick="addStoryEmoji('💯')">💯</span>
          <span onclick="addStoryEmoji('✨')">✨</span><span onclick="addStoryEmoji('🎵')">🎵</span>
          <span onclick="addStoryEmoji('🌟')">🌟</span><span onclick="addStoryEmoji('😎')">😎</span>
          <span onclick="addStoryEmoji('🙌')">🙌</span><span onclick="addStoryEmoji('💥')">💥</span>
          <span onclick="addStoryEmoji('🫶')">🫶</span><span onclick="addStoryEmoji('🤩')">🤩</span>
          <span onclick="addStoryEmoji('😜')">😜</span><span onclick="addStoryEmoji('🎸')">🎸</span>
        </div>
        <div id="storyEmojiOverlay"></div>
        <button onclick="storyEmojiStr='';document.getElementById('storyEmojiOverlay').textContent='';" style="font-size:.72rem;background:none;border:none;color:#e91e63;cursor:pointer;padding:0;margin-top:2px;">✕ Clear stickers</button>

        <!-- Song search -->
        <div class="story-section-label">🎵 Add Song</div>
        <div class="song-search-wrap">
          <input type="text" id="songSearchInput" placeholder="Search for a song (e.g. Shape of You)…" oninput="songSearchDebounce()">
          <button class="song-search-clear" onclick="clearSongSearch()" title="Clear">✕</button>
        </div>
        <div class="song-results" id="songResults"></div>

        <!-- Selected song card -->
        <div class="song-selected-card" id="songSelectedCard">
          <img id="songSelArt" src="" alt="">
          <div class="song-selected-info">
            <div class="song-selected-title" id="songSelTitle"></div>
            <div class="song-selected-artist" id="songSelArtist"></div>
            <div style="font-size:.68rem;color:#6a1b9a;margin-top:2px;">▶ 30-sec preview will play for viewers</div>
          </div>
          <button class="song-remove-btn" onclick="removeSong()" title="Remove song">✕</button>
        </div>

        <!-- Song portion selector -->
        <div class="song-trim-wrap" id="songTrimWrap" style="display:none;">
          <label>🎚️ Choose which part to play (drag to select start point):</label>
          <input type="range" class="song-trim-slider" id="songStartSlider" min="0" max="29" step="0.5" value="0" oninput="updateSongStartLabel()">
          <div class="song-trim-info" id="songStartInfo">Starts at: 0s of the 30-sec preview</div>
          <!-- Hidden audio for preview -->
          <audio id="songPreviewAudio" style="width:100%;margin-top:6px;" controls></audio>
        </div>

        <!-- Visibility note -->
        <small style="color:#999;font-size:.72rem;margin-top:8px;display:block;">
          ℹ️ Visible to <strong>mutual friends only</strong> for <strong>24 hours</strong>, then auto-deleted.
        </small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="shareStoryBtn" onclick="submitStory()" style="background:#6a1b9a;border-color:#6a1b9a;">Share Story</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ NEW POST MODAL ══ -->
<div class="modal fade" id="newPostModal" tabindex="-1" role="dialog" aria-labelledby="newPostModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="newpost_backend.php" method="POST" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newPostModalLabel">Create New Post</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <textarea name="post_text" class="form-control mb-3" placeholder="What's on your mind?" rows="3"></textarea>
        <input type="file" name="post_img" class="form-control-file mb-3" accept="image/*,video/*" id="mediaInput"/>
        <div id="filePreview"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Post</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ ADD EVENT MODAL ══ -->
<div class="modal fade" id="addEventModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📅 Add Event</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Event Name</label>
          <input type="text" class="form-control" id="evtName" placeholder="e.g. Team meeting">
        </div>
        <div class="form-group">
          <label>Date</label>
          <input type="date" class="form-control" id="evtDate">
        </div>
        <div class="form-group">
          <label>Time (for notification)</label>
          <input type="time" class="form-control" id="evtTime">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveEvent()" style="background:#6a1b9a;border-color:#6a1b9a;">Save Event</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ SHARE SIDEBAR ══ -->
<div class="share-sidebar" id="shareSidebar">
  <h5>Share with</h5>
  <input type="text" id="shareSearch" placeholder="Search users..." style="width:100%;padding:6px 10px;margin-bottom:10px;border:1px solid #ccc;border-radius:6px;">
  <div id="searchResults"></div>
  <?php
    $following_sql = "SELECT u.user_id, u.full_name, u.profile_image FROM follows f JOIN users u ON f.following_id = u.user_id WHERE f.follower_id = ? AND NOT EXISTS (SELECT 1 FROM blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.user_id) OR (b.blocker_id=u.user_id AND b.blocked_id=?))";
    $following_query = $con->prepare($following_sql);
    $following_query->bind_param("iii", $user_id, $user_id, $user_id);
    $following_query->execute();
    $following_result = $following_query->get_result();
    while ($follow_user = $following_result->fetch_assoc()):
      $profileImg = !empty($follow_user['profile_image']) ? $follow_user['profile_image'] : 'uploads/default-profile.png';
  ?>
    <div class="share-user">
      <img src="<?= htmlspecialchars($profileImg) ?>" alt="">
      <span><?= htmlspecialchars($follow_user['full_name']) ?></span>
      <button class="share-send-btn" data-user-id="<?= $follow_user['user_id'] ?>" data-name="<?= htmlspecialchars($follow_user['full_name']) ?>">Send</button>
    </div>
  <?php endwhile; ?>
  <button id="repostBtn" style="width:100%;padding:10px;margin-top:15px;border:none;border-radius:6px;background-color:#6a1b9a;color:#fff;font-weight:600;cursor:pointer;">Repost</button>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ════════════════════════════════════════
   PROFILE DROPDOWN
   ════════════════════════════════════════ */
const profileIcon  = document.getElementById('profileIcon');
const dropdownMenu = document.getElementById('dropdownMenu');
profileIcon.addEventListener('click', e => { e.stopPropagation(); dropdownMenu.classList.toggle('show-dropdown'); });
window.addEventListener('click', e => {
  if (!dropdownMenu.contains(e.target) && !profileIcon.contains(e.target))
    dropdownMenu.classList.remove('show-dropdown');
});

/* ════════════════════════════════════════
   FILE PREVIEW (new post modal)
   ════════════════════════════════════════ */
const mediaInput = document.getElementById('mediaInput');
const filePreview = document.getElementById('filePreview');
mediaInput.addEventListener('change', function () {
  filePreview.innerHTML = '';
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const url = e.target.result;
    if (file.type.startsWith('image/')) {
      const img = document.createElement('img'); img.src = url; filePreview.appendChild(img);
    } else if (file.type.startsWith('video/')) {
      const v = document.createElement('video'); v.src = url; v.controls = true; filePreview.appendChild(v);
    }
  };
  reader.readAsDataURL(file);
});

/* ════════════════════════════════════════
   INTERACTIONS (like / save / comments)
   ════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  function toggleAction(button, action) {
    const postId = button.dataset.postId;
    fetch('interact_post.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `post_id=${postId}&action=${action}` })
      .then(r => r.json()).then(data => {
        const icon = button.querySelector('i');
        if (action === 'like') {
          icon.classList.toggle('fas', data.status === 'liked');
          icon.classList.toggle('far', data.status !== 'liked');
          icon.style.color = data.status === 'liked' ? 'red' : '';
        } else if (action === 'save') {
          icon.classList.toggle('fas', data.status === 'saved');
          icon.classList.toggle('far', data.status !== 'saved');
          icon.style.color = data.status === 'saved' ? 'green' : '';
        }
      }).catch(() => alert('Something went wrong!'));
  }
  document.querySelectorAll('.like-btn').forEach(btn => btn.addEventListener('click', () => toggleAction(btn, 'like')));
  document.querySelectorAll('.save-btn').forEach(btn => btn.addEventListener('click', () => toggleAction(btn, 'save')));

  document.querySelectorAll('.comment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const post = btn.closest('.post');
      const postId = post.dataset.postId;
      const commentsBox = post.querySelector('.comments');
      const addBox = post.querySelector('.add-comment');
      commentsBox.classList.toggle('d-none');
      addBox.classList.toggle('d-none');
      if (!addBox.classList.contains('d-none')) addBox.querySelector('.comment-input').focus();
      if (!commentsBox.dataset.loaded) {
        fetch('load_comments.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`post_id=${postId}` })
          .then(r => r.text()).then(html => { commentsBox.innerHTML = html; commentsBox.dataset.loaded = 'true'; })
          .catch(() => { commentsBox.innerHTML = '<p class="text-danger">Failed to load</p>'; });
      }
    });
  });

  document.querySelectorAll('.comment-submit').forEach(btn => {
    btn.addEventListener('click', () => {
      const postId = btn.dataset.postId;
      const input  = document.querySelector(`.comment-input[data-post-id="${postId}"]`);
      const text   = input.value.trim();
      if (!text) return;
      fetch('comment_post.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`post_id=${postId}&comment=${encodeURIComponent(text)}` })
        .then(r => r.json()).then(data => {
          if (data.status === 'success') {
            const box = document.getElementById(`comments-${postId}`);
            box.classList.remove('d-none');
            box.insertAdjacentHTML('beforeend', data.html);
            input.value = '';
          } else { alert(data.msg); }
        }).catch(() => alert('Network error'));
    });
  });

  document.querySelectorAll('.share-btn').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); document.getElementById('shareSidebar').classList.add('open'); });
  });
  document.addEventListener('click', e => {
    const sidebar = document.getElementById('shareSidebar');
    if (!sidebar.contains(e.target) && !e.target.closest('.share-btn') && sidebar.classList.contains('open'))
      sidebar.classList.remove('open');
  });

  document.getElementById('shareSearch').addEventListener('input', function () {
    const q = this.value.trim();
    const div = document.getElementById('searchResults');
    if (!q) { div.innerHTML = ''; return; }
    fetch('search_users.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`query=${encodeURIComponent(q)}` })
      .then(r => r.text()).then(html => { div.innerHTML = html; }).catch(() => { div.innerHTML = '<p class="text-danger">Search failed</p>'; });
  });

  const searchInput = document.querySelector('.searchbar input');
  const resultsBox  = document.getElementById('mainSearchResults');
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim();
    if (!q) { resultsBox.style.display = 'none'; resultsBox.innerHTML = ''; return; }
    fetch('search_profiles.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`query=${encodeURIComponent(q)}` })
      .then(r => r.text()).then(html => { resultsBox.innerHTML = html; resultsBox.style.display = 'block'; })
      .catch(() => { resultsBox.innerHTML = '<p class="text-danger">Search failed</p>'; resultsBox.style.display = 'block'; });
  });
  window.addEventListener('click', e => {
    if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) resultsBox.style.display = 'none';
  });
});

/* Delete comment */
document.addEventListener('click', e => {
  const btn = e.target.closest('.delete-comment');
  if (!btn) return;
  if (!confirm('Delete this comment?')) return;
  fetch('delete_comment.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'comment_id='+encodeURIComponent(btn.dataset.commentId) })
    .then(r => r.json()).then(data => { if (data.status === 'success') btn.closest('.comment').remove(); else alert(data.msg || 'Could not delete'); })
    .catch(() => alert('Network error'));
});

/* Notification count */
function loadNotificationCount() {
  fetch('get_notification_count.php').then(r => r.json()).then(data => {
    const badge = document.getElementById('notifCount');
    if (data.count > 0) { badge.textContent = data.count; badge.style.display = 'inline-block'; }
    else { badge.style.display = 'none'; }
  }).catch(console.error);
}
document.addEventListener('DOMContentLoaded', () => { loadNotificationCount(); setInterval(loadNotificationCount, 30000); });
document.getElementById('notifBell').addEventListener('click', () => { window.location.href = 'notifications_frontend.php'; });

/* Follow / Unfollow */
document.addEventListener('click', e => {
  const btn = e.target.closest('.follow-btn');
  if (!btn) return;
  const targetId    = btn.dataset.userId;
  const isFollowing = btn.dataset.following === '1';
  fetch('follow_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`target_user_id=${targetId}&action=${isFollowing ? 'unfollow' : 'follow'}` })
    .then(r => r.json()).then(data => {
      if (data.status === 'followed' || data.status === 'unfollowed') {
        const newState = data.status === 'followed' ? '1' : '0';
        document.querySelectorAll(`.follow-btn[data-user-id="${targetId}"]`).forEach(b => {
          b.dataset.following = newState;
          b.textContent = newState === '1' ? 'Unfollow' : 'Follow';
        });
      }
    }).catch(() => alert('Network error'));
});

/* Repost */
let selectedPostId = null;
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.share-btn').forEach(btn => {
    btn.addEventListener('click', function (e) { e.stopPropagation(); selectedPostId = this.dataset.postId; document.getElementById('shareSidebar').classList.add('open'); });
  });
});
document.getElementById('repostBtn').addEventListener('click', function () {
  if (!selectedPostId) { alert('No post selected.'); return; }
  const caption = prompt('Add a comment to your repost (optional):', '');
  fetch('repost_backend.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`post_id=${selectedPostId}&caption=${encodeURIComponent(caption || '')}` })
    .then(r => r.json()).then(data => { alert(data.status === 'success' ? 'Post reposted!' : 'Failed to repost.'); })
    .catch(() => alert('Error reposting.'));
});


/* ════════════════════════════════════════════════════════════════
   STORIES — FULL INSTAGRAM-LIKE SYSTEM
   • File upload with directory auto-create
   • Video trim (max 15 sec) with drag handles
   • Song search via iTunes Search API (no API key needed)
   • Song portion selector (start point of 30-sec preview)
   • Emoji stickers
   • Mute video option
   • Viewer list + delete for own stories
   ════════════════════════════════════════════════════════════════ */

const MY_USER_ID  = <?= (int)$user_id ?>;
const MY_AVATAR   = '<?= htmlspecialchars($myAvatar, ENT_QUOTES) ?>';
const MY_USERNAME = '<?= htmlspecialchars(addslashes($user['user_name'])) ?>';

/* ── state ── */
let storyEmojiStr = '';
let storyFile     = null;
let storyVideoDur = 0;
let trimStart     = 0;
let trimEnd       = 15;
let selectedSong  = null;  // { title, artist, preview, artwork }
let songSearchTimer = null;
let songAudioCtx  = null;

/* ── viewer state ── */
let svStories   = [];
let svIndex     = 0;
let svUserId    = null;
let svAnimFrame = null;
let svStartTime = null;
let svDuration  = 5000;
let svVideoEl   = null;
let svAudioEl   = null;   // playing song preview during story view

/* ════════════════════════════
   SIDEBAR BUBBLE LOADER
   ════════════════════════════ */
function loadStorySidebar() {
  const container = document.getElementById('storiesContainer');
  fetch('get_stories.php?sidebar=1')
    .then(r => r.json())
    .then(data => {
      container.innerHTML = '';
      const myEntry  = Array.isArray(data) ? data.find(u => u.user_id == MY_USER_ID) : null;
      const myTotal  = myEntry ? parseInt(myEntry.total||0) : 0;
      const ringColor = myTotal > 0 ? '#6a1b9a' : '#ccc';

      /* my bubble */
      const myWrap = document.createElement('div');
      myWrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0;';
      myWrap.innerHTML = `
        <div style="position:relative;width:60px;height:60px;cursor:pointer;" onclick="${myTotal>0?'openStoryView(MY_USER_ID,MY_USERNAME,MY_AVATAR)':'openStoryUpload()'}">
          <img src="${MY_AVATAR}" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:3px solid ${ringColor};">
          <div onclick="event.stopPropagation();openStoryUpload();"
               style="position:absolute;bottom:0;right:0;width:20px;height:20px;background:#6a1b9a;border-radius:50%;border:2px solid white;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;font-weight:700;cursor:pointer;">+</div>
        </div>
        <span style="font-size:.65rem;color:#555;text-align:center;">${myTotal>0?'Your Story':'Add Story'}</span>`;
      container.appendChild(myWrap);

      if (Array.isArray(data)) {
        data.filter(u => u.user_id != MY_USER_ID).forEach(u => {
          const hasUnseen = parseInt(u.unseen) > 0;
          const border    = hasUnseen ? '#e91e63' : '#bbb';
          const img       = u.profile_image || 'default_profile.png';
          const wrap      = document.createElement('div');
          wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer;flex-shrink:0;';
          wrap.onclick = () => openStoryView(u.user_id, u.user_name, img);
          wrap.innerHTML = `
            <div style="position:relative;">
              <img src="${img}" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:3px solid ${border};">
              ${hasUnseen?`<span style="position:absolute;top:-2px;right:-2px;background:#e91e63;color:#fff;font-size:9px;font-weight:700;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;border:1.5px solid #fff;">${u.unseen}</span>`:''}
            </div>
            <span style="font-size:.65rem;color:#555;max-width:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center;">@${u.user_name}</span>`;
          container.appendChild(wrap);
        });
      }
    })
    .catch(() => {
      container.innerHTML = `<div onclick="openStoryUpload()" style="display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer;flex-shrink:0;">
        <div style="position:relative;width:60px;height:60px;">
          <img src="${MY_AVATAR}" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:3px solid #ccc;">
          <div style="position:absolute;bottom:0;right:0;width:20px;height:20px;background:#6a1b9a;border-radius:50%;border:2px solid white;display:flex;align-items:center;justify-content:center;color:white;font-size:14px;font-weight:700;">+</div>
        </div>
        <span style="font-size:.65rem;color:#555;">Add Story</span>
      </div>`;
    });
}

/* ════════════════════════════
   UPLOAD MODAL — open / reset
   ════════════════════════════ */
function openStoryUpload() {
  storyEmojiStr = ''; storyFile = null; storyVideoDur = 0;
  trimStart = 0; trimEnd = 15; selectedSong = null;
  document.getElementById('storyPreviewArea').innerHTML = '';
  document.getElementById('storyPreviewArea').style.display = 'none';
  document.getElementById('storyEmojiOverlay').textContent = '';
  document.getElementById('storyTrimSection').style.display  = 'none';
  document.getElementById('storyMuteRow').style.display      = 'none';
  document.getElementById('songSelectedCard').style.display  = 'none';
  document.getElementById('songTrimWrap').style.display      = 'none';
  document.getElementById('songResults').style.display       = 'none';
  document.getElementById('songSearchInput').value           = '';
  document.getElementById('shareStoryBtn').disabled          = false;
  document.getElementById('shareStoryBtn').textContent       = 'Share Story';
  const prevAudio = document.getElementById('songPreviewAudio');
  prevAudio.src = ''; prevAudio.pause();
  $('#storyUploadModal').modal('show');
}

/* ════════════════════════════
   FILE CHOSEN → preview + trim
   ════════════════════════════ */
function storyFileChosen(inp) {
  storyFile = inp.files[0];
  if (!storyFile) return;
  const area = document.getElementById('storyPreviewArea');
  area.style.display = 'block';
  area.innerHTML = '';

  if (storyFile.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = e => { area.innerHTML = `<img src="${e.target.result}" style="max-height:190px;border-radius:10px;">`; };
    reader.readAsDataURL(storyFile);
    document.getElementById('storyTrimSection').style.display = 'none';
    document.getElementById('storyMuteRow').style.display     = 'none';

  } else if (storyFile.type.startsWith('video/')) {
    const url = URL.createObjectURL(storyFile);
    const vid  = document.createElement('video');
    vid.src    = url; vid.muted = true; vid.style.cssText = 'max-height:190px;border-radius:10px;width:100%;display:block;';
    area.appendChild(vid);
    document.getElementById('storyMuteRow').style.display = 'block';

    vid.onloadedmetadata = () => {
      storyVideoDur = vid.duration;
      trimStart = 0;
      trimEnd   = Math.min(storyVideoDur, 15);
      document.getElementById('storyTrimSection').style.display = 'block';
      initTrimSlider(vid, storyVideoDur);
    };
  }
}

/* ════════════════════════════
   VIDEO TRIM — drag handles
   ════════════════════════════ */
function initTrimSlider(vid, dur) {
  const timeline  = document.getElementById('trimTimeline');
  const handleL   = document.getElementById('trimHandleL');
  const handleR   = document.getElementById('trimHandleR');
  const selection = document.getElementById('trimSelection');
  const info      = document.getElementById('trimInfo');
  const warn      = document.getElementById('trimLimitWarn');

  function pct2sec(p) { return p * dur; }
  function sec2pct(s) { return (s / dur) * 100; }

  function updateUI() {
    const lp = sec2pct(trimStart), rp = sec2pct(trimEnd);
    handleL.style.left    = lp + '%';
    handleR.style.left    = rp + '%';
    selection.style.left  = lp + '%';
    selection.style.width = (rp - lp) + '%';
    const len = trimEnd - trimStart;
    info.textContent = `${trimStart.toFixed(1)}s → ${trimEnd.toFixed(1)}s (${len.toFixed(1)}s)`;
    warn.style.display = len > 15 ? 'block' : 'none';
    /* scrub preview */
    if (!isNaN(vid.duration)) vid.currentTime = trimStart;
  }
  updateUI();

  function makeDraggable(handle, isLeft) {
    let dragging = false;
    handle.addEventListener('mousedown',  e => { e.preventDefault(); dragging = true; });
    handle.addEventListener('touchstart', e => { e.preventDefault(); dragging = true; }, {passive:false});
    document.addEventListener('mousemove', e => { if (!dragging) return; move(e.clientX); });
    document.addEventListener('touchmove', e => { if (!dragging) return; move(e.touches[0].clientX); }, {passive:false});
    document.addEventListener('mouseup',  () => dragging = false);
    document.addEventListener('touchend', () => dragging = false);

    function move(clientX) {
      const rect = timeline.getBoundingClientRect();
      let p = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
      let s = pct2sec(p);
      if (isLeft) {
        trimStart = Math.max(0, Math.min(s, trimEnd - 0.5));
        if (trimEnd - trimStart > 15) trimStart = trimEnd - 15;
      } else {
        trimEnd   = Math.max(trimStart + 0.5, Math.min(s, dur));
        if (trimEnd - trimStart > 15) trimEnd = trimStart + 15;
      }
      updateUI();
    }
  }
  makeDraggable(handleL, true);
  makeDraggable(handleR, false);
}

/* ════════════════════════════
   EMOJI
   ════════════════════════════ */
function addStoryEmoji(em) {
  storyEmojiStr += em;
  document.getElementById('storyEmojiOverlay').textContent = storyEmojiStr;
}

/* ════════════════════════════
   SONG SEARCH (iTunes API — free, no key)
   ════════════════════════════ */
function songSearchDebounce() {
  clearTimeout(songSearchTimer);
  songSearchTimer = setTimeout(doSongSearch, 450);
}

function doSongSearch() {
  const q = document.getElementById('songSearchInput').value.trim();
  const box = document.getElementById('songResults');
  if (!q) { box.style.display = 'none'; box.innerHTML = ''; return; }
  box.style.display = 'block';
  box.innerHTML = '<div style="padding:8px 10px;font-size:.8rem;color:#999;">Searching…</div>';

  /* iTunes Search API — CORS-friendly, returns 30-sec preview URLs */
  fetch(`https://itunes.apple.com/search?term=${encodeURIComponent(q)}&media=music&limit=8&entity=song`)
    .then(r => r.json())
    .then(data => {
      if (!data.results || data.results.length === 0) {
        box.innerHTML = '<div style="padding:8px 10px;font-size:.8rem;color:#999;">No songs found.</div>';
        return;
      }
      box.innerHTML = '';
      data.results.forEach(song => {
        if (!song.previewUrl) return; // skip songs without preview
        const art = song.artworkUrl60 || '';
        const item = document.createElement('div');
        item.className = 'song-result-item';
        item.innerHTML = `
          <img src="${art}" alt="">
          <div class="song-result-info">
            <div class="song-result-title">${song.trackName}</div>
            <div class="song-result-artist">${song.artistName} · ${song.collectionName||''}</div>
          </div>
          <button class="song-result-play" title="Preview" onclick="previewSong('${song.previewUrl}',this)">▶</button>`;
        item.addEventListener('click', (e) => {
          if (e.target.closest('.song-result-play')) return;
          selectSong({ title: song.trackName, artist: song.artistName, preview: song.previewUrl, artwork: art });
        });
        box.appendChild(item);
      });
    })
    .catch(() => { box.innerHTML = '<div style="padding:8px;color:#f66;font-size:.8rem;">Search failed. Check internet connection.</div>'; });
}

let previewAudioEl = null;
function previewSong(url, btn) {
  if (previewAudioEl) { previewAudioEl.pause(); previewAudioEl = null; btn.textContent='▶'; return; }
  const audio = new Audio(url);
  audio.play().catch(()=>{});
  previewAudioEl = audio;
  btn.textContent = '⏹';
  audio.onended = () => { btn.textContent = '▶'; previewAudioEl = null; };
}

function selectSong(song) {
  if (previewAudioEl) { previewAudioEl.pause(); previewAudioEl = null; }
  selectedSong = song;
  document.getElementById('songResults').style.display = 'none';
  document.getElementById('songSearchInput').value = '';

  document.getElementById('songSelArt').src         = song.artwork;
  document.getElementById('songSelTitle').textContent  = song.title;
  document.getElementById('songSelArtist').textContent = song.artist;
  document.getElementById('songSelectedCard').style.display = 'flex';

  /* song portion slider */
  document.getElementById('songTrimWrap').style.display = 'block';
  document.getElementById('songStartSlider').value = 0;
  updateSongStartLabel();
  const pa = document.getElementById('songPreviewAudio');
  pa.src = song.preview;
}

function updateSongStartLabel() {
  const val = parseFloat(document.getElementById('songStartSlider').value);
  document.getElementById('songStartInfo').textContent = `Starts at: ${val.toFixed(1)}s of the 30-sec preview`;
  /* seek audio preview */
  const pa = document.getElementById('songPreviewAudio');
  if (!pa.paused) { pa.currentTime = val; }
}

function clearSongSearch() {
  document.getElementById('songSearchInput').value = '';
  document.getElementById('songResults').style.display = 'none';
  if (previewAudioEl) { previewAudioEl.pause(); previewAudioEl = null; }
}

function removeSong() {
  selectedSong = null;
  document.getElementById('songSelectedCard').style.display = 'none';
  document.getElementById('songTrimWrap').style.display     = 'none';
  const pa = document.getElementById('songPreviewAudio');
  pa.pause(); pa.src = '';
}

/* ════════════════════════════
   SUBMIT STORY
   ════════════════════════════ */
function submitStory() {
  if (!storyFile) { alert('Please choose a photo or video first.'); return; }
  const btn = document.getElementById('shareStoryBtn');
  btn.disabled = true; btn.textContent = 'Uploading…';

  const fd = new FormData();
  fd.append('story_file', storyFile);
  fd.append('emojis', storyEmojiStr);
  fd.append('muted',  document.getElementById('storyMuteCheck').checked ? '1' : '0');
  fd.append('vid_start_sec', trimStart.toFixed(2));
  fd.append('vid_end_sec',   trimEnd.toFixed(2));

  if (selectedSong) {
    fd.append('song_title',     selectedSong.title);
    fd.append('song_artist',    selectedSong.artist);
    fd.append('song_preview',   selectedSong.preview);
    fd.append('song_artwork',   selectedSong.artwork);
    fd.append('song_start_sec', document.getElementById('songStartSlider').value);
  } else {
    fd.append('song_title',''); fd.append('song_artist','');
    fd.append('song_preview',''); fd.append('song_artwork','');
    fd.append('song_start_sec','0');
  }

  fetch('add_story.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      $('#storyUploadModal').modal('hide');
      if (d.status === 'success') {
        loadStorySidebar();
        alert('Story shared! 🎉 Visible to mutual friends for 24 hours.');
      } else {
        alert('Upload failed: ' + (d.msg || 'unknown error'));
        btn.disabled = false; btn.textContent = 'Share Story';
      }
    })
    .catch(err => {
      alert('Network error — make sure add_story.php exists in your project folder.\nDetails: ' + err);
      btn.disabled = false; btn.textContent = 'Share Story';
    });
}

/* ════════════════════════════
   STORY VIEWER
   ════════════════════════════ */
function openStoryView(userId, userName, userImg) {
  svUserId = userId;
  fetch(`get_stories.php?user_id=${userId}`)
    .then(r => r.json())
    .then(data => {
      if (!Array.isArray(data) || data.length === 0) { alert('No active stories from @' + userName); return; }
      svStories = data; svIndex = 0;
      document.getElementById('svAvatar').src       = userImg || 'default_profile.png';
      document.getElementById('svName').textContent = '@' + userName;
      document.getElementById('storyViewer').classList.add('open');
      document.getElementById('svViewersDrawer').style.display = 'none';
      renderStorySlide();
    })
    .catch(() => alert('Could not load stories.'));
}

function renderStorySlide() {
  cancelAnimationFrame(svAnimFrame);
  if (svVideoEl)  { svVideoEl.pause();  svVideoEl  = null; }
  if (svAudioEl)  { svAudioEl.pause();  svAudioEl  = null; }

  const story   = svStories[svIndex];
  const isOwner = parseInt(story.user_id) === MY_USER_ID;

  document.getElementById('svDeleteBtn').style.display  = isOwner ? 'block' : 'none';
  document.getElementById('svViewersBtn').style.display = isOwner ? 'block' : 'none';
  document.getElementById('svMuteBtn').style.display    = story.file_type === 'video' ? 'block' : 'none';

  /* expiry label */
  const expires = new Date(story.expires_at.replace(' ','T'));
  const diffMin = Math.round((expires - Date.now()) / 60000);
  document.getElementById('svTime').textContent = diffMin > 60 ? `Expires in ${Math.round(diffMin/60)}h` : diffMin > 0 ? `Expires in ${diffMin}m` : 'Expiring…';

  /* progress bar */
  const bar = document.getElementById('svProgressBar');
  bar.innerHTML = '';
  svStories.forEach((_,i) => {
    const seg = document.createElement('div'); seg.className = 'sv-seg';
    const fill = document.createElement('div'); fill.className = 'sv-seg-fill';
    if (i < svIndex) fill.style.width = '100%';
    seg.appendChild(fill); bar.appendChild(seg);
  });

  /* media */
  const med = document.getElementById('svMedia');
  med.innerHTML = ''; svDuration = 5000;

  if (story.file_type === 'video') {
    const vid = document.createElement('video');
    vid.src         = story.file_path;
    vid.muted       = story.muted == 1;
    vid.autoplay    = true;
    vid.playsInline = true;
    vid.style.cssText = 'width:100%;max-height:500px;object-fit:cover;display:block;';
    /* honour trim */
    const vStart = parseFloat(story.vid_start_sec) || 0;
    const vEnd   = parseFloat(story.vid_end_sec)   || 15;
    vid.onloadedmetadata = () => {
      vid.currentTime = vStart;
      svDuration      = (vEnd - vStart) * 1000;
      startStoryProgress();
    };
    vid.ontimeupdate = () => { if (vid.currentTime >= vEnd) storyNav(1); };
    med.appendChild(vid);
    svVideoEl = vid;
    updateMuteBtn();
  } else {
    const img = document.createElement('img');
    img.src = story.file_path;
    img.style.cssText = 'width:100%;max-height:500px;object-fit:cover;display:block;';
    med.appendChild(img);
    startStoryProgress();
  }

  /* song overlay */
  const info = document.getElementById('svInfo');
  info.innerHTML = '';
  if (story.emojis) {
    const s = document.createElement('span');
    s.style.cssText = 'font-size:1.3rem;letter-spacing:3px;'; s.textContent = story.emojis;
    info.appendChild(s);
  }
  if (story.song_preview) {
    const songSpan = document.createElement('span');
    songSpan.style.cssText = 'font-size:.75rem;flex:1;text-align:right;cursor:pointer;';
    songSpan.innerHTML = `🎵 ${story.song_title} — ${story.song_artist}`;
    info.appendChild(songSpan);
    /* auto-play song preview */
    const audio      = new Audio(story.song_preview);
    audio.currentTime = parseFloat(story.song_start_sec) || 0;
    audio.play().catch(()=>{});
    svAudioEl = audio;
  } else if (story.song_title) {
    /* song title only (no preview URL) */
    const s = document.createElement('span');
    s.style.cssText = 'font-size:.75rem;flex:1;text-align:right;'; s.textContent = `🎵 ${story.song_title}`;
    info.appendChild(s);
  }
}

function startStoryProgress() {
  svStartTime = null;
  const fillEl = document.getElementById('svProgressBar').children[svIndex]?.querySelector('.sv-seg-fill');
  if (!fillEl) return;
  function tick(ts) {
    if (!svStartTime) svStartTime = ts;
    const pct = Math.min(100, ((ts - svStartTime) / svDuration) * 100);
    fillEl.style.width = pct + '%';
    if (pct < 100) svAnimFrame = requestAnimationFrame(tick);
    else storyNav(1);
  }
  svAnimFrame = requestAnimationFrame(tick);
}

function storyNav(dir) {
  cancelAnimationFrame(svAnimFrame);
  if (svVideoEl) { svVideoEl.pause(); svVideoEl = null; }
  if (svAudioEl) { svAudioEl.pause(); svAudioEl = null; }
  svIndex += dir;
  if (svIndex < 0) svIndex = 0;
  if (svIndex >= svStories.length) { closeStoryView(); return; }
  renderStorySlide();
}

function closeStoryView() {
  cancelAnimationFrame(svAnimFrame);
  if (svVideoEl) { svVideoEl.pause(); svVideoEl = null; }
  if (svAudioEl) { svAudioEl.pause(); svAudioEl = null; }
  document.getElementById('storyViewer').classList.remove('open');
  loadStorySidebar();
}

function toggleStoryMute() {
  if (!svVideoEl) return;
  svVideoEl.muted = !svVideoEl.muted;
  updateMuteBtn();
}
function updateMuteBtn() {
  const btn = document.getElementById('svMuteBtn');
  btn.textContent = svVideoEl?.muted ? '🔇' : '🔊';
}

function deleteCurrentStory() {
  if (!svStories[svIndex]) return;
  if (!confirm('Delete this story?')) return;
  fetch('delete_story.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'story_id='+svStories[svIndex].story_id })
    .then(r=>r.json()).then(d => {
      if (d.status==='success') {
        svStories.splice(svIndex,1);
        if (!svStories.length) { closeStoryView(); return; }
        if (svIndex >= svStories.length) svIndex = svStories.length-1;
        renderStorySlide(); loadStorySidebar();
      } else alert('Could not delete: '+(d.msg||'error'));
    }).catch(()=>alert('Network error'));
}

function showStoryViewers() {
  if (!svStories[svIndex]) return;
  const drawer = document.getElementById('svViewersDrawer');
  const list   = document.getElementById('svViewersList');
  drawer.style.display = 'block';
  list.innerHTML = '<div style="color:#aaa;font-size:.75rem;">Loading…</div>';
  fetch(`get_story_viewers.php?story_id=${svStories[svIndex].story_id}`)
    .then(r=>r.json()).then(d=>{
      if (d.error) { list.innerHTML=`<div style="color:#f66;">${d.error}</div>`; return; }
      if (!d.viewers||!d.viewers.length) { list.innerHTML='<div style="color:#aaa;">No views yet</div>'; return; }
      list.innerHTML=d.viewers.map(v=>{
        const img=v.profile_image||'default_profile.png';
        const t=new Date(v.viewed_at.replace(' ','T')).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
        return `<div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid rgba(255,255,255,.1);">
          <img src="${img}" style="width:26px;height:26px;border-radius:50%;object-fit:cover;">
          <span style="flex:1;">@${v.user_name}</span>
          <span style="color:#aaa;font-size:.65rem;">${t}</span>
        </div>`;
      }).join('');
    }).catch(()=>{list.innerHTML='<div style="color:#f66;">Failed</div>';});
}

document.addEventListener('keydown', e => {
  if (!document.getElementById('storyViewer').classList.contains('open')) return;
  if (e.key==='ArrowRight') storyNav(1);
  if (e.key==='ArrowLeft')  storyNav(-1);
  if (e.key==='Escape')     closeStoryView();
});


/* ════════════════════════════════════════
   CALENDAR
   ════════════════════════════════════════ */
let calYear, calMonth, calEvents = JSON.parse(localStorage.getItem('cnfy_events') || '{}');

function saveEvents() { localStorage.setItem('cnfy_events', JSON.stringify(calEvents)); }

function renderCal() {
  const now = new Date();
  if (calYear === undefined) { calYear = now.getFullYear(); calMonth = now.getMonth(); }
  const firstDay = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
  const names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  document.getElementById('calMonthYear').textContent = names[calMonth] + ' ' + calYear;

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';
  ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => {
    const el = document.createElement('div'); el.className = 'cal-day-label'; el.textContent = d;
    grid.appendChild(el);
  });
  for (let i = 0; i < firstDay; i++) {
    const el = document.createElement('div'); el.className = 'cal-cell other-month'; el.textContent = new Date(calYear, calMonth, -firstDay + i + 1).getDate();
    grid.appendChild(el);
  }
  for (let d = 1; d <= daysInMonth; d++) {
    const el = document.createElement('div');
    el.className = 'cal-cell';
    el.textContent = d;
    const key = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    if (calEvents[key] && calEvents[key].length) el.classList.add('has-event');
    if (d === now.getDate() && calMonth === now.getMonth() && calYear === now.getFullYear()) el.classList.add('today');
    el.addEventListener('click', () => showDayEvents(key));
    grid.appendChild(el);
  }
  renderEventList();
}

function showDayEvents(key) {
  // Pre-fill date in add event modal
  document.getElementById('evtDate').value = key;
  $('#addEventModal').modal('show');
}

function renderEventList() {
  const list = document.getElementById('eventList');
  list.innerHTML = '';
  const today = new Date();
  const key = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
  const evts = calEvents[key] || [];
  if (!evts.length) { list.innerHTML = '<div style="font-size:.75rem;color:#999;padding:4px 0;">No events today</div>'; return; }
  evts.forEach((ev, i) => {
    const item = document.createElement('div'); item.className = 'event-item';
    item.innerHTML = `<div class="event-dot"></div><div class="event-item-text">${ev.name}</div><div class="event-item-time">${ev.time || ''}</div><button class="event-del" onclick="deleteEvent('${key}',${i})">✕</button>`;
    list.appendChild(item);
  });
}

function saveEvent() {
  const name = document.getElementById('evtName').value.trim();
  const date = document.getElementById('evtDate').value;
  const time = document.getElementById('evtTime').value;
  if (!name || !date) { alert('Please enter event name and date.'); return; }
  if (!calEvents[date]) calEvents[date] = [];
  calEvents[date].push({ name, time });
  saveEvents();
  renderCal();
  $('#addEventModal').modal('hide');
  document.getElementById('evtName').value = '';
  document.getElementById('evtDate').value = '';
  document.getElementById('evtTime').value = '';
  scheduleEventNotification(name, date, time);
  alert('✅ Event saved!');
}

function deleteEvent(key, idx) {
  calEvents[key].splice(idx, 1);
  if (!calEvents[key].length) delete calEvents[key];
  saveEvents(); renderCal();
}

function scheduleEventNotification(name, dateStr, timeStr) {
  if (!timeStr) return;
  const [h, m] = timeStr.split(':').map(Number);
  const [y, mo, d] = dateStr.split('-').map(Number);
  const eventTime = new Date(y, mo - 1, d, h, m, 0);
  const now = new Date();
  const ms = eventTime - now;
  if (ms <= 0) return; // past
  // Browser notification
  if ('Notification' in window) {
    Notification.requestPermission().then(perm => {
      if (perm === 'granted') {
        setTimeout(() => {
          new Notification('📅 Connectify Reminder', { body: name, icon: 'favicon.ico' });
        }, ms);
      }
    });
  }
  // In-app banner fallback
  setTimeout(() => showNotifBanner('📅 Event Reminder', name), ms);
}

function showNotifBanner(title, body) {
  document.getElementById('nb-title').textContent = title;
  document.getElementById('nb-body').textContent  = body;
  const banner = document.getElementById('notifBanner');
  banner.style.display = 'flex';
  setTimeout(() => { banner.style.display = 'none'; }, 7000);
}

document.getElementById('calPrev').addEventListener('click', () => { calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; } renderCal(); });
document.getElementById('calNext').addEventListener('click', () => { calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; } renderCal(); });

document.addEventListener('DOMContentLoaded', () => {
  loadStorySidebar();
  renderCal();
  scheduleStoredEventNotifications();
  renderTodo();
  tickTodoTimers();
  setInterval(tickTodoTimers, 1000);
});

/* Re-schedule notifications for already-saved events on page load */
function scheduleStoredEventNotifications() {
  Object.entries(calEvents).forEach(([date, evts]) => {
    evts.forEach(ev => { if (ev.time) scheduleEventNotification(ev.name, date, ev.time); });
  });
}


/* ════════════════════════════════════════
   TO-DO LIST
   ════════════════════════════════════════ */
let todos = JSON.parse(localStorage.getItem('cnfy_todos') || '[]');

function saveTodos() { localStorage.setItem('cnfy_todos', JSON.stringify(todos)); }

function addTodo() {
  const text = document.getElementById('todoInput').value.trim();
  const time = document.getElementById('todoTime').value;
  if (!text) return;
  todos.push({ text, time, done: false, id: Date.now() });
  saveTodos();
  document.getElementById('todoInput').value = '';
  document.getElementById('todoTime').value  = '';
  if (time) {
    // Schedule in-app banner for todo reminder
    const now = new Date();
    const [h, m] = time.split(':').map(Number);
    const target = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, 0);
    const ms = target - now;
    if (ms > 0) {
      setTimeout(() => showNotifBanner('✅ To-Do Reminder', text), ms);
      if ('Notification' in window && Notification.permission === 'granted') {
        setTimeout(() => new Notification('✅ Connectify To-Do', { body: text, icon: 'favicon.ico' }), ms);
      }
    }
  }
  renderTodo();
}

function renderTodo() {
  const list = document.getElementById('todoList');
  list.innerHTML = '';
  todos.forEach((todo, i) => {
    const li = document.createElement('li'); li.className = 'todo-item';
    const timeLabel = todo.time ? `<span class="todo-timer-badge" id="ttbadge_${todo.id}">${todo.time}</span>` : '';
    li.innerHTML = `
      <input type="checkbox" class="todo-check" ${todo.done ? 'checked' : ''} onchange="toggleTodo(${i})">
      <span class="todo-text ${todo.done ? 'done' : ''}">${escHtml(todo.text)}</span>
      ${timeLabel}
      <button class="todo-del" onclick="deleteTodo(${i})">✕</button>`;
    list.appendChild(li);
  });
}

function toggleTodo(i) { todos[i].done = !todos[i].done; saveTodos(); renderTodo(); }
function deleteTodo(i) { todos.splice(i, 1); saveTodos(); renderTodo(); }

function tickTodoTimers() {
  const now = new Date();
  todos.forEach(todo => {
    if (!todo.time || todo.done) return;
    const badge = document.getElementById('ttbadge_' + todo.id);
    if (!badge) return;
    const [h, m] = todo.time.split(':').map(Number);
    const target = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, 0);
    const diffMs = target - now;
    if (diffMs <= 0) {
      badge.textContent = 'Due!';
      badge.classList.add('urgent');
    } else {
      const mins = Math.floor(diffMs / 60000);
      const secs = Math.floor((diffMs % 60000) / 1000);
      badge.textContent = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
      badge.classList.remove('urgent');
    }
  });
}

function escHtml(str) {
  const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
}
</script>

<?php include 'chat_panel.php'; ?>
<script src="share_send.js"></script>

</body>
</html>