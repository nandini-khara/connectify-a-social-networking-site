<?php
/**
 * admin_decrypt_conversation.php
 * ─────────────────────────────────────────────────────────────
 * ADMIN ONLY — protect this file. Never expose it publicly.
 *
 * Usage:
 *   admin_decrypt_conversation.php?user_a=1&user_b=2
 *
 * Shows full conversation plain-text, oldest → newest.
 * Requires CHAT_PRIVATE_KEY_PATH to be readable by PHP.
 */
session_start();

// ── Protect: only logged-in admins ───────────────────────────
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die('403 Forbidden — admins only');
}

require 'connect.php';
require 'chat_crypto.php';

$uid_a = (int)($_GET['user_a'] ?? 0);
$uid_b = (int)($_GET['user_b'] ?? 0);
if (!$uid_a || !$uid_b) {
    die('Usage: ?user_a=ID&user_b=ID');
}

$lo = min($uid_a, $uid_b);
$hi = max($uid_a, $uid_b);

// Load encrypted AES key
$s = $con->prepare(
    "SELECT aes_key_enc FROM conversation_keys WHERE user_a=? AND user_b=?"
);
$s->bind_param('ii', $lo, $hi);
$s->execute();
$keyRow = $s->get_result()->fetch_assoc();
$s->close();

if (!$keyRow) {
    die('No conversation key found. This pair may have no messages, or messages predate encryption.');
}

// RSA-decrypt the AES key (needs private key)
$aesKey = _decryptAesKey($keyRow['aes_key_enc']);
if (!$aesKey || strlen($aesKey) < 8) {
    die('Could not decrypt AES key — ensure CHAT_PRIVATE_KEY_PATH is correct and readable.');
}

// Fetch all messages oldest → newest
$stmt = $con->prepare(
    "SELECT m.id, m.sender_id, m.message, m.message_enc, m.created_at, u.user_name
       FROM messages m
       JOIN users u ON u.user_id = m.sender_id
      WHERE (m.sender_id=? AND m.receiver_id=?)
         OR (m.sender_id=? AND m.receiver_id=?)
      ORDER BY m.created_at ASC"
);
$stmt->bind_param('iiii', $uid_a, $uid_b, $uid_b, $uid_a);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Output plain text
header('Content-Type: text/plain; charset=utf-8');
echo "=== CONVERSATION: user $uid_a  ↔  user $uid_b ===\n";
echo "=== " . count($rows) . " messages — oldest to newest ===\n\n";

foreach ($rows as $r) {
    $ts   = date('Y-m-d H:i:s', strtotime($r['created_at']));
    $name = $r['user_name'];
    if (!empty($r['message_enc'])) {
        $text = decryptMessage($r['message_enc'], $aesKey);
    } else {
        $text = $r['message'] ?? '(no text)';   // legacy / file-only message
    }
    echo "[$ts] $name: $text\n";
}