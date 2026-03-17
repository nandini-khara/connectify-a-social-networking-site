<?php
/**
 * get_status.php
 * Returns JSON: { is_online: bool, last_seen_label: string }
 * Used by the message panel to poll partner's online status every 30s.
 *
 * Also updates the current user's own last_seen on every call.
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['is_online' => false, 'last_seen_label' => '']);
    exit();
}

require 'connect.php';
$uid       = (int)$_SESSION['user_id'];
$target_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$target_id) {
    echo json_encode(['is_online' => false, 'last_seen_label' => '']);
    exit();
}

/* ── Update current user's last_seen ── */
$upd = $con->prepare("UPDATE users SET last_seen = NOW() WHERE user_id = ?");
$upd->bind_param('i', $uid);
$upd->execute();

/* ── Fetch target's last_seen ── */
$sel = $con->prepare("SELECT last_seen FROM users WHERE user_id = ?");
$sel->bind_param('i', $target_id);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['is_online' => false, 'last_seen_label' => 'Unavailable']);
    exit();
}

$last_seen = $row['last_seen'];
$is_online = false;
$label     = 'Last seen: a long time ago';

if ($last_seen) {
    $diff      = time() - strtotime($last_seen);
    $is_online = ($diff < 180); // online if active within 3 minutes

    if ($is_online) {
        $label = 'Online now';
    } elseif ($diff < 60) {
        $label = 'Last seen: just now';
    } elseif ($diff < 3600) {
        $label = 'Last seen: ' . floor($diff / 60) . ' min ago';
    } else {
        $dt        = new DateTime($last_seen);
        $today     = (new DateTime())->format('Y-m-d');
        $yesterday = (new DateTime('yesterday'))->format('Y-m-d');
        $day       = $dt->format('Y-m-d');
        $time      = $dt->format('g:i A');

        if ($day === $today)         $label = "Last seen today at $time";
        elseif ($day === $yesterday) $label = "Last seen yesterday at $time";
        else                         $label = 'Last seen ' . $dt->format('M j, Y') . " at $time";
    }
}

echo json_encode([
    'is_online'       => $is_online,
    'last_seen_label' => $label,
]);