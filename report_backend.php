<?php
// ── Error handler: ensures PHP errors return JSON, not HTML ──────────────────
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function ($e) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
});

set_error_handler(function ($errno, $errstr) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => "PHP Error [$errno]: $errstr"]);
    exit;
});

// ── Session & headers ────────────────────────────────────────────────────────
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

require 'connect.php';

// ── Input ────────────────────────────────────────────────────────────────────
$reporter_id = (int) $_SESSION['user_id'];
$category    = isset($_POST['category']) ? trim(strip_tags($_POST['category'])) : '';
$text        = isset($_POST['text'])     ? trim(strip_tags($_POST['text']))     : '';
$steps       = isset($_POST['steps'])    ? trim(strip_tags($_POST['steps']))    : '';
$ip          = $_SERVER['REMOTE_ADDR'] ?? '';

if ($category === '' || $text === '') {
    echo json_encode(['status' => 'error', 'message' => 'Category and description are required.']);
    exit();
}

// ── Map friendly category names → enum/short values ─────────────────────────
$typeMap = [
    'Bug / error'            => 'bug',
    'Performance issue'      => 'performance',
    'Login / account access' => 'login',
    'Messages not sending'   => 'messages',
    'Notifications broken'   => 'notifications',
    'Content not loading'    => 'content',
    'Other'                  => 'other',
];
$report_type = $typeMap[$category] ?? 'other';

// ── Truncate ─────────────────────────────────────────────────────────────────
$category = substr($category, 0, 100);
$text     = substr($text,     0, 800);
$steps    = substr($steps,    0, 400);
$extra    = $steps !== '' ? "Steps: $steps" : null;

// ── Ensure the table supports nullable reported_id + required columns ─────────
// Run once — safe to leave in (uses IF NOT EXISTS / column check logic via ALTER)
$con->query("
    ALTER TABLE user_reports
        MODIFY COLUMN reported_id INT DEFAULT NULL
");
// Ignore error if column already nullable — mysqli doesn't throw on ALTER no-ops

// Add missing columns if they don't exist yet (safe on repeated calls)
// ── Add missing columns only if they don't already exist ─────────────────────
$existingColumns = [];
$colResult = $con->query("SHOW COLUMNS FROM user_reports");
while ($col = $colResult->fetch_assoc()) {
    $existingColumns[] = $col['Field'];
}

$columnsToAdd = [
    'report_type' => "ALTER TABLE user_reports ADD COLUMN report_type VARCHAR(50)  DEFAULT NULL",
    'source'      => "ALTER TABLE user_reports ADD COLUMN source      ENUM('chat','settings') NOT NULL DEFAULT 'chat'",
    'extra_info'  => "ALTER TABLE user_reports ADD COLUMN extra_info  TEXT         DEFAULT NULL",
    'ip_address'  => "ALTER TABLE user_reports ADD COLUMN ip_address  VARCHAR(45)  DEFAULT NULL",
];

foreach ($columnsToAdd as $colName => $alterSql) {
    if (!in_array($colName, $existingColumns)) {
        $con->query($alterSql);
    }
}

// ── Insert ───────────────────────────────────────────────────────────────────
$stmt = $con->prepare(
    "INSERT INTO user_reports
         (reporter_id, reported_id, report_type, source, reason, extra_info, ip_address)
     VALUES
         (?, NULL, ?, 'settings', ?, ?, ?)"
);

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $con->error]);
    exit();
}

$stmt->bind_param("issss", $reporter_id, $report_type, $text, $extra, $ip);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Execute failed: ' . $stmt->error]);
}

$stmt->close();
?>