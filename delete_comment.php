<?php
/* delete_comment.php */
session_start();
require 'connect.php';

header('Content-Type: application/json');

$comment_id = (int)($_POST['comment_id'] ?? 0);
$user_id    = (int)($_SESSION['user_id']  ?? 0);

if (!$user_id)        { echo json_encode(['status'=>'error','msg'=>'Not logged in']);     exit; }
if ($comment_id <= 0) { echo json_encode(['status'=>'error','msg'=>'Invalid comment ID']); exit; }

/* PK is `id` — confirmed: c.comment_id does not exist, c.id does */
$stmt = $con->prepare("
    SELECT c.user_id AS author_id,
           p.user_id AS post_owner
    FROM   comments c
    JOIN   post     p ON p.id = c.post_id
    WHERE  c.id = ?
    LIMIT  1
");
$stmt->bind_param('i', $comment_id);
$stmt->execute();
$meta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meta) { echo json_encode(['status'=>'error','msg'=>'Comment not found']); exit; }

if ($user_id !== (int)$meta['author_id'] && $user_id !== (int)$meta['post_owner']) {
    echo json_encode(['status'=>'error','msg'=>'Not permitted']);
    exit;
}

$del = $con->prepare("DELETE FROM comments WHERE id = ?");
$del->bind_param('i', $comment_id);

echo $del->execute()
    ? json_encode(['status'=>'success'])
    : json_encode(['status'=>'error','msg'=>'Delete failed: ' . $con->error]);

$del->close();