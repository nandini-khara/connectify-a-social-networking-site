<?php
/**
 * update_last_seen.php
 *
 * Works TWO ways:
 *
 * 1. As an INCLUDE at the top of any logged-in page:
 *      require_once 'update_last_seen.php';
 *    (Must be after session_start() and require 'connect.php')
 *
 * 2. As a standalone POST endpoint called by fetch():
 *      fetch('update_last_seen.php', { method: 'POST' })
 *    The chat panel calls this every 30s to keep last_seen fresh.
 */

// If called as standalone endpoint, bootstrap session + DB
$_standalone = !isset($con);
if ($_standalone) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once __DIR__ . '/connect.php';
}

if (isset($_SESSION['user_id'], $con)) {
    $uid = (int)$_SESSION['user_id'];
    $upd = $con->prepare("UPDATE users SET last_seen = NOW() WHERE user_id = ?");
    if ($upd) {
        $upd->bind_param('i', $uid);
        $upd->execute();
        $upd->close();
    }
}

// If called as standalone, send response and exit
if ($_standalone) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}