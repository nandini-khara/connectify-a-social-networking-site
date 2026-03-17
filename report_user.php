<?php
/**
 * report_user.php
 * POST endpoint called from chat_panel.php when a user reports another user.
 * Expects: reported_id (int), reason (string)
 * Returns: JSON { status: 'reported'|'error'|'already_reported', message: string }
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

require_once 'connect.php';

$reporter_id = (int)$_SESSION['user_id'];
$reported_id = isset($_POST['reported_id']) ? (int)$_POST['reported_id'] : 0;
$reason      = isset($_POST['reason'])      ? trim($_POST['reason'])      : '';

/* ── Basic validation ── */
if ($reported_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user']);
    exit();
}
if ($reporter_id === $reported_id) {
    echo json_encode(['status' => 'error', 'message' => 'Cannot report yourself']);
    exit();
}
if (mb_strlen($reason) < 3) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide a reason']);
    exit();
}

/* ── Sanitise ── */
$reason = mb_substr($reason, 0, 1000);

/* ── Check reported user exists ── */
$chk = $con->prepare("SELECT user_id FROM users WHERE user_id = ?");
$chk->bind_param('i', $reported_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

/* ── Prevent duplicate open reports within 24 hours ── */
$dup = $con->prepare("
    SELECT report_id FROM user_reports
    WHERE reporter_id = ?
      AND reported_id = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
      AND status = 'pending'
    LIMIT 1
");
$dup->bind_param('ii', $reporter_id, $reported_id);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'already_reported', 'message' => 'You already reported this user recently']);
    exit();
}

/* ── Insert report ── */
$ip         = $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

$ins = $con->prepare("
    INSERT INTO user_reports
        (reporter_id, reported_id, reason, ip_address, user_agent, status, created_at)
    VALUES
        (?, ?, ?, ?, ?, 'pending', NOW())
");
$ins->bind_param('iisss', $reporter_id, $reported_id, $reason, $ip, $user_agent);

if ($ins->execute()) {
    echo json_encode(['status' => 'reported', 'message' => 'Report submitted successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $con->error]);
}