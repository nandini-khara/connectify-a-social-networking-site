<?php
/**
 * check_block.php
 * Returns whether the logged-in user has blocked the target user.
 * POST: target_id
 * Response: { "blocked": 1 } or { "blocked": 0 }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['blocked' => 0]); exit(); }

require_once 'connect.php';

$my_id     = (int)$_SESSION['user_id'];
$target_id = (int)($_POST['target_id'] ?? 0);

if (!$target_id) { echo json_encode(['blocked' => 0]); exit(); }

$stmt = $con->prepare("SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1");
$stmt->bind_param("ii", $my_id, $target_id);
$stmt->execute();
$stmt->store_result();

echo json_encode(['blocked' => $stmt->num_rows > 0 ? 1 : 0]);
?>