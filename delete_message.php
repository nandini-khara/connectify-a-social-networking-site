<?php
/**
 * delete_message.php
 *
 * POST params:
 *   message_id  — ID of the message to delete
 *   scope       — 'me'  → soft-delete (hidden only for this user)
 *                 'all' → hard-delete (only sender can do this)
 *
 * Response JSON:
 *   { status: 'success' }
 *   { status: 'error', msg: '...' }
 *
 * Required DB objects:
 *   messages table            — existing
 *   message_deletions table   — for soft-delete (see CREATE below)
 *
 * CREATE TABLE IF NOT EXISTS message_deletions (
 *   message_id  INT NOT NULL,
 *   user_id     INT NOT NULL,
 *   deleted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
 *   PRIMARY KEY (message_id, user_id),
 *   FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
 * );
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit();
}

require_once 'connect.php';

$uid       = (int)$_SESSION['user_id'];
$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$scope     = $_POST['scope'] ?? '';

if (!$messageId) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid message ID']);
    exit();
}
if (!in_array($scope, ['me', 'all'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid scope']);
    exit();
}

/* ── Fetch message to verify ownership ── */
$chk = $con->prepare("SELECT sender_id, receiver_id FROM messages WHERE message_id = ?");
$chk->bind_param('i', $messageId);
$chk->execute();
$msg = $chk->get_result()->fetch_assoc();

if (!$msg) {
    echo json_encode(['status' => 'error', 'msg' => 'Message not found']);
    exit();
}

$isSender   = ((int)$msg['sender_id']   === $uid);
$isReceiver = ((int)$msg['receiver_id'] === $uid);

// Only participants can delete
if (!$isSender && !$isReceiver) {
    echo json_encode(['status' => 'error', 'msg' => 'Access denied']);
    exit();
}

/* ════════════════════════════════
   SCOPE: me  (soft delete)
   Records that THIS user deleted it.
   The other person still sees the message.
   load_messages.php must filter these out with:
     WHERE NOT EXISTS (
       SELECT 1 FROM message_deletions md
       WHERE md.message_id = m.message_id AND md.user_id = ?
     )
════════════════════════════════ */
if ($scope === 'me') {
    $ins = $con->prepare("
        INSERT IGNORE INTO message_deletions (message_id, user_id)
        VALUES (?, ?)
    ");
    $ins->bind_param('ii', $messageId, $uid);
    if ($ins->execute()) {
        echo json_encode(['status' => 'success', 'scope' => 'me']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'DB error: ' . $con->error]);
    }
    exit();
}

/* ════════════════════════════════
   SCOPE: all  (hard delete)
   Only the SENDER can delete for everyone.
   Permanently removes the row (cascade removes message_deletions entries).
════════════════════════════════ */
if ($scope === 'all') {
    if (!$isSender) {
        echo json_encode(['status' => 'error', 'msg' => 'Only the sender can delete for everyone']);
        exit();
    }
    $del = $con->prepare("DELETE FROM messages WHERE message_id = ?");
    $del->bind_param('i', $messageId);
    if ($del->execute()) {
        echo json_encode(['status' => 'success', 'scope' => 'all']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'DB error: ' . $con->error]);
    }
    exit();
}

echo json_encode(['status' => 'error', 'msg' => 'Unhandled case']);