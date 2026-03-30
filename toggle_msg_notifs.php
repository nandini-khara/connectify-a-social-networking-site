<?php
/**
 * toggle_msg_notifs.php
 * Toggles mute_msg_notifs for the logged-in user.
 * Called via fetch() from notifications_frontend.php
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']);
    exit;
}

require 'connect.php';
$user_id = (int)$_SESSION['user_id'];
$mute    = isset($_POST['mute']) ? (int)$_POST['mute'] : 0;
$mute    = $mute ? 1 : 0;

$stmt = $con->prepare("UPDATE users SET mute_msg_notifs=? WHERE user_id=?");
$stmt->bind_param('ii', $mute, $user_id);

echo $stmt->execute()
    ? json_encode(['status'=>'success','muted'=>$mute])
    : json_encode(['status'=>'error','msg'=>'DB error']);

$stmt->close();