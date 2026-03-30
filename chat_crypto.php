<?php
/**
 * chat_crypto.php
 * ───────────────────────────────────────────────────────────────
 * Include this in send_message.php, load_messages.php, and
 * admin_decrypt_conversation.php.
 *
 * HOW IT WORKS
 * ─────────────
 *  • Each pair of users gets one AES-256-GCM key.
 *  • That key is RSA-encrypted with the server public key and
 *    stored in `conversation_keys`. The raw key never hits the DB.
 *  • To read messages, the server decrypts the AES key on the fly
 *    using the private key, then decrypts the message text.
 *  • Admin tool: supply both keys → can decrypt any conversation.
 *
 * ONE-TIME SERVER SETUP (run as root / sudo, outside webroot):
 *   openssl genrsa -out /var/keys/chat_private.pem 4096
 *   openssl rsa  -in /var/keys/chat_private.pem \
 *                -pubout -out /var/keys/chat_public.pem
 *   chmod 600 /var/keys/chat_private.pem
 *   chmod 644 /var/keys/chat_public.pem
 *
 * Then update the two paths below.
 */

// define('CHAT_PUBLIC_KEY_PATH',  '/var/keys/chat_public.pem');
// define('CHAT_PRIVATE_KEY_PATH', '/var/keys/chat_private.pem');
define('CHAT_PUBLIC_KEY_PATH',  'C:/xampp/keys/chat_public.pem');
define('CHAT_PRIVATE_KEY_PATH', 'C:/xampp/keys/chat_private.pem');
/* ─────────────────────────────────────────────────────────────
 * getOrCreateConversationKey($con, $uid_a, $uid_b)
 *
 * Returns the raw 32-byte AES key for this conversation.
 * Creates and stores a new one if none exists yet.
 * ───────────────────────────────────────────────────────────── */
function getOrCreateConversationKey($con, $uid_a, $uid_b) {
    $lo = min((int)$uid_a, (int)$uid_b);
    $hi = max((int)$uid_a, (int)$uid_b);

    // Try to load existing key
    $s = $con->prepare(
        "SELECT aes_key_enc FROM conversation_keys WHERE user_a=? AND user_b=?"
    );
    $s->bind_param('ii', $lo, $hi);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if ($row) {
        return _decryptAesKey($row['aes_key_enc']);
    }

    // Generate new random 256-bit key
    $rawKey = random_bytes(32);

    // Encrypt it with the server RSA public key
    $pubKey = @openssl_pkey_get_public('file://' . CHAT_PUBLIC_KEY_PATH);
    if ($pubKey) {
        $enc = '';
        openssl_public_encrypt($rawKey, $enc, $pubKey, OPENSSL_PKCS1_OAEP_PADDING);
        $stored = base64_encode($enc);
    } else {
        // No RSA key configured yet — store raw (dev/fallback only, remove in production)
        $stored = base64_encode($rawKey);
    }

    $ins = $con->prepare(
        "INSERT IGNORE INTO conversation_keys (user_a, user_b, aes_key_enc) VALUES (?,?,?)"
    );
    $ins->bind_param('iis', $lo, $hi, $stored);
    $ins->execute();
    $ins->close();

    return $rawKey;
}

/* ─────────────────────────────────────────────────────────────
 * _decryptAesKey($stored)   [internal helper]
 * ───────────────────────────────────────────────────────────── */
function _decryptAesKey($stored) {
    $blob    = base64_decode($stored);
    $privKey = @openssl_pkey_get_private('file://' . CHAT_PRIVATE_KEY_PATH);
    if (!$privKey) {
        // Private key not on this machine (normal for web server in production)
        // Return blob as-is — works only in the dev/fallback path above
        return $blob;
    }
    $raw = '';
    openssl_private_decrypt($blob, $raw, $privKey, OPENSSL_PKCS1_OAEP_PADDING);
    return $raw;
}

/* ─────────────────────────────────────────────────────────────
 * encryptMessage($plaintext, $aesKey)
 *
 * Returns base64( 12-byte-IV | ciphertext | 16-byte-GCM-tag )
 * ───────────────────────────────────────────────────────────── */
function encryptMessage($plaintext, $aesKey) {
    $iv  = random_bytes(12);
    $tag = '';
    $ct  = openssl_encrypt(
        $plaintext, 'aes-256-gcm', $aesKey,
        OPENSSL_RAW_DATA, $iv, $tag, '', 16
    );
    return base64_encode($iv . $ct . $tag);
}

/* ─────────────────────────────────────────────────────────────
 * decryptMessage($encoded, $aesKey)
 *
 * Reverses encryptMessage(). Returns plaintext string.
 * ───────────────────────────────────────────────────────────── */
function decryptMessage($encoded, $aesKey) {
    $raw = base64_decode($encoded);
    $iv  = substr($raw,  0, 12);
    $tag = substr($raw, -16);
    $ct  = substr($raw,  12, strlen($raw) - 28);

    $plain = openssl_decrypt(
        $ct, 'aes-256-gcm', $aesKey,
        OPENSSL_RAW_DATA, $iv, $tag
    );
    return ($plain === false) ? '[decryption failed]' : $plain;
}