<?php
/**
 * get_online_status.php
 * Called by chat_panel.php to get online status of conversation partners.
 *
 * POST: user_ids = comma-separated list of user IDs
 * Returns JSON: { "7": { "online": true, "last_seen": "2m ago" }, ... }
 *
 * Rules:
 *  - Online = last_seen within 5 minutes
 *  - Last seen shown only if viewer and target are MUTUAL followers
 *  - If target has hide_last_seen=1, show nothing (not even "online")
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

require 'connect.php';

$viewer_id = (int)$_SESSION['user_id'];
$raw       = trim($_POST['user_ids'] ?? '');

if (!$raw) { echo json_encode([]); exit; }

// Sanitise — only accept integers
$ids = array_filter(
    array_map('intval', explode(',', $raw)),
    fn($id) => $id > 0 && $id !== $viewer_id
);

if (empty($ids)) { echo json_encode([]); exit; }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types        = str_repeat('i', count($ids));

/* Fetch last_seen + hide_last_seen for requested users */
$stmt = $con->prepare("
    SELECT user_id, last_seen, hide_last_seen
    FROM   users
    WHERE  user_id IN ($placeholders)
");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Find which of these users are MUTUAL followers with viewer */
$mutualStmt = $con->prepare("
    SELECT f1.following_id AS uid
    FROM   follows f1
    JOIN   follows f2
      ON   f2.follower_id  = f1.following_id
     AND   f2.following_id = ?
    WHERE  f1.follower_id = ?
      AND  f1.following_id IN ($placeholders)
");
$mutualParams = array_merge([$viewer_id, $viewer_id], $ids);
$mutualTypes  = 'ii' . $types;
$mutualStmt->bind_param($mutualTypes, ...$mutualParams);
$mutualStmt->execute();
$mutualRows = $mutualStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$mutualStmt->close();

$mutuals    = array_map('strval', array_column($mutualRows, 'uid'));
$mutualSet  = array_flip($mutuals); // fast O(1) lookup by string key

/* Build response */
$now    = time();
$result = [];

foreach ($rows as $row) {
    $uid      = (int)$row['user_id'];
    $uidStr   = (string)$uid;
    $hidden   = (int)$row['hide_last_seen'];
    $isMutual = isset($mutualSet[$uidStr]);
    $lastSeen = $row['last_seen'] ? strtotime($row['last_seen']) : null;

    if ($hidden) {
        $result[$uidStr] = ['online' => false, 'label' => ''];
        continue;
    }

    if (!$lastSeen) {
        $result[$uidStr] = ['online' => false, 'label' => ''];
        continue;
    }

    $diff   = $now - $lastSeen;
    $online = $diff < 300;

    if (!$isMutual) {
        $result[$uidStr] = [
            'online' => $online,
            'label'  => $online ? 'Online' : '',
        ];
        continue;
    }

    if ($online) {
        $label = 'Online';
    } elseif ($diff < 3600) {
        $label = 'last seen ' . floor($diff / 60) . 'm ago';
    } elseif ($diff < 86400) {
        $label = 'last seen ' . floor($diff / 3600) . 'h ago';
    } elseif ($diff < 172800) {
        $label = 'last seen yesterday';
    } else {
        $label = 'last seen ' . date('d M', $lastSeen);
    }

    $result[$uidStr] = ['online' => $online, 'label' => $label];
}

echo json_encode($result);