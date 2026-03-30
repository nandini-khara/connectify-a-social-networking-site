<?php
/**
 * privacy_backend.php
 * Handles: is_private toggle AND hide_last_seen toggle
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']);
    exit;
}

require 'connect.php';
$user_id = (int)$_SESSION['user_id'];

/* ── is_private ── */
if (isset($_POST['is_private'])) {
    $val  = (int)$_POST['is_private'] ? 1 : 0;
    $stmt = $con->prepare("UPDATE users SET is_private=? WHERE user_id=?");
    $stmt->bind_param('ii', $val, $user_id);
    echo $stmt->execute()
        ? json_encode(['status'=>'success','is_private'=>$val])
        : json_encode(['status'=>'error','msg'=>'DB error']);
    $stmt->close();
    exit;
}

/* ── hide_last_seen ── */
if (isset($_POST['hide_last_seen'])) {
    $val  = (int)$_POST['hide_last_seen'] ? 1 : 0;
    $stmt = $con->prepare("UPDATE users SET hide_last_seen=? WHERE user_id=?");
    $stmt->bind_param('ii', $val, $user_id);
    echo $stmt->execute()
        ? json_encode(['status'=>'success','hide_last_seen'=>$val])
        : json_encode(['status'=>'error','msg'=>'DB error']);
    $stmt->close();
    exit;
}

echo json_encode(['status'=>'error','msg'=>'No valid field provided']);