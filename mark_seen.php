<!-- <?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) exit();
require_once 'connect.php';

$my_id     = (int)$_SESSION['user_id'];
$sender_id = (int)$_POST['sender_id'];

$con->query("UPDATE messages SET seen = 1 
             WHERE receiver_id = $my_id 
             AND sender_id = $sender_id 
             AND seen = 0");

echo json_encode(['status' => 'ok']);
?> -->
<?php
/**
 * mark_seen.php
 * Called by chat_panel.php when a conversation is opened.
 * 1. Marks messages as seen (delivered → seen)
 * 2. Clears the message notification from that sender
 */
session_start();
if (!isset($_SESSION['user_id'], $_POST['sender_id'])) exit;

require 'connect.php';

$receiver_id = (int)$_SESSION['user_id'];
$sender_id   = (int)$_POST['sender_id'];

/* Mark messages as seen */
$upd = $con->prepare("
    UPDATE messages
    SET status='seen', seen_at=NOW()
    WHERE receiver_id=? AND sender_id=? AND status IN ('sent','delivered')
");
$upd->bind_param('ii', $receiver_id, $sender_id);
$upd->execute();
$upd->close();

/* Clear the message notification so it disappears from notification list */
$del = $con->prepare("
    UPDATE notifications
    SET is_read=1
    WHERE user_id=? AND actor_id=? AND type='message'
");
$del->bind_param('ii', $receiver_id, $sender_id);
$del->execute();
$del->close();

echo json_encode(['status'=>'ok']);