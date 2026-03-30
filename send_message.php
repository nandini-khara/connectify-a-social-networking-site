<?php
/**
 * send_message.php — with text moderation + message notification
 * Notification rule: one notification per conversation (upsert by type+actor+user).
 * If receiver has muted message notifications, skip.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','msg'=>'Not logged in']);
    exit;
}

require 'connect.php';
require_once 'chat_crypto.php';
require_once 'content_moderation.php';

$sender_id      = (int)$_SESSION['user_id'];
$receiver_id    = (int)($_POST['receiver_id']    ?? 0);
$message        = trim($_POST['message']         ?? '');
$gif_url        = trim($_POST['gif_url']         ?? '');
$shared_post_id = (int)($_POST['shared_post_id'] ?? 0);
$reply_to_id    = (int)($_POST['reply_to_id']    ?? 0);

if (!$receiver_id) {
    echo json_encode(['status'=>'error','msg'=>'No recipient']);
    exit;
}

/* Block check */
$blk = $con->prepare("SELECT 1 FROM blocks WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?) LIMIT 1");
$blk->bind_param('iiii', $sender_id, $receiver_id, $receiver_id, $sender_id);
$blk->execute();
if ($blk->get_result()->num_rows > 0) {
    echo json_encode(['status'=>'blocked','msg'=>'Cannot send message']);
    exit;
}
$blk->close();

/* Text moderation */
if ($message !== '') {
    $textCheck = moderateText($message);
    if (!$textCheck['ok']) {
        echo json_encode(['status'=>'error','msg'=>$textCheck['reason']]);
        exit;
    }
}

/* GIF as message */
if ($gif_url !== '') $message = $gif_url;

if ($message === '' && !$shared_post_id) {
    echo json_encode(['status'=>'error','msg'=>'Empty message']);
    exit;
}

/* Encrypt */
$aesKey = getOrCreateConversationKey($con, $sender_id, $receiver_id);
$msgEnc = $message !== '' ? encryptMessage($message, $aesKey) : '';

/* Insert message */
$replyVal = $reply_to_id    ?: null;
$postVal  = $shared_post_id ?: null;

$ins = $con->prepare("
    INSERT INTO messages
        (sender_id, receiver_id, message_text, message_enc, status, shared_post_id, reply_to_id)
    VALUES (?,?,?,?,'sent',?,?)
");
$ins->bind_param('iissii', $sender_id, $receiver_id, $message, $msgEnc, $postVal, $replyVal);

if (!$ins->execute()) {
    echo json_encode(['status'=>'error','msg'=>'DB error: '.$con->error]);
    exit;
}
$ins->close();

/* ── MESSAGE NOTIFICATION ────────────────────────────────────────
   Rule: one notification per (sender → receiver) conversation.
   We UPSERT — if one already exists and is unread, just update
   the timestamp and message so the receiver sees the latest.
   If receiver has muted message notifications, skip entirely.
─────────────────────────────────────────────────────────────── */
$muteCheck = $con->prepare("SELECT mute_msg_notifs FROM users WHERE user_id=? LIMIT 1");
$muteCheck->bind_param('i', $receiver_id);
$muteCheck->execute();
$muteRow = $muteCheck->get_result()->fetch_assoc();
$muteCheck->close();
$isMuted = (int)($muteRow['mute_msg_notifs'] ?? 0);

if (!$isMuted) {
    $senderName = $_SESSION['user_name'] ?? 'Someone';
    $preview    = $shared_post_id
        ? "$senderName sent you a post"
        : ($message !== '' ? "$senderName: " . mb_substr($message, 0, 40) . (mb_strlen($message) > 40 ? '…' : '') : "$senderName sent you a message");

    /* Check if an UNREAD message notification from this sender already exists */
    $existsQ = $con->prepare("
        SELECT id FROM notifications
        WHERE user_id=? AND actor_id=? AND type='message' AND is_read=0
        LIMIT 1
    ");
    $existsQ->bind_param('ii', $receiver_id, $sender_id);
    $existsQ->execute();
    $existingRow = $existsQ->get_result()->fetch_assoc();
    $existsQ->close();

    if ($existingRow) {
        /* Update existing unread notification — just refresh the message + time */
        $upd = $con->prepare("
            UPDATE notifications SET message=?, created_at=NOW()
            WHERE id=?
        ");
        $upd->bind_param('si', $preview, $existingRow['id']);
        $upd->execute();
        $upd->close();
    } else {
        /* Insert fresh notification */
        $ins2 = $con->prepare("
            INSERT INTO notifications (user_id, actor_id, type, message, post_id, comment_id, is_read, created_at)
            VALUES (?, ?, 'message', ?, NULL, NULL, 0, NOW())
        ");
        $ins2->bind_param('iis', $receiver_id, $sender_id, $preview);
        $ins2->execute();
        $ins2->close();
    }
}

echo json_encode(['status'=>'sent']);
exit;