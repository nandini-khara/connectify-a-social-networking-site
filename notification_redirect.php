<?php
/**
 * notification_redirect.php
 * Marks a notification as read then redirects to the correct destination.
 *
 * Params:
 *   ?nid=  notification ID
 *   ?dest= (optional) encoded destination URL — if absent, goes to profile
 */
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$notif_id = (int)($_GET['nid'] ?? 0);
$dest     = $_GET['dest'] ?? '';

/* Mark this notification as read */
if ($notif_id) {
    $upd = $con->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $upd->bind_param('ii', $notif_id, $user_id);
    $upd->execute();
    $upd->close();

    /* If no dest was passed, figure it out from the notification itself */
    if (!$dest) {
        $sel = $con->prepare("SELECT type, post_id, comment_id, actor_id FROM notifications WHERE id=? AND user_id=? LIMIT 1");
        $sel->bind_param('ii', $notif_id, $user_id);
        $sel->execute();
        $n = $sel->get_result()->fetch_assoc();
        $sel->close();

        if ($n) {
            if ($n['type'] === 'follow') {
                $dest = 'public_profile.php?user_id=' . (int)$n['actor_id'];
            } elseif ($n['post_id']) {
                $dest = 'view_post.php?post_id=' . (int)$n['post_id']
                      . '&actor_id=' . (int)$n['actor_id']
                      . '&action='   . urlencode($n['type']);
                if ($n['type'] === 'comment' && $n['comment_id']) {
                    $dest .= '&highlight_comment=' . (int)$n['comment_id'];
                }
            }
        }
    }
}

/* Validate dest — only allow same-origin relative paths */
if ($dest) {
    $decoded = urldecode($dest);
    $parsed  = parse_url($decoded);
    $host    = $_SERVER['HTTP_HOST'] ?? '';
    $isSafe  = empty($parsed['host']) || $parsed['host'] === $host;
    if ($isSafe) {
        header("Location: " . $decoded);
        exit();
    }
}

/* Fallback */
header("Location: notifications_frontend.php");
exit();