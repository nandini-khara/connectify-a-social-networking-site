<?php
/* --------------------------------------------------------------
   delete_comment.php
   Deletes one comment if the requester is
   – the comment’s author  OR
   – the owner of the post the comment belongs to
   -------------------------------------------------------------- */
session_start();
require 'connect.php';

/* 1. basic validation ----------------------------------------- */
if (!isset($_POST['comment_id'])) {
    http_response_code(400);
    exit('No comment ID');
}
$comment_id = (int)$_POST['comment_id'];
$user_id    = $_SESSION['user_id'] ?? 0;

/* 2. find who owns what --------------------------------------- */
$sql = "
    SELECT c.user_id        AS author_id,
           p.user_id        AS post_owner
      FROM comments c
      JOIN post p ON p.id = c.post_id   -- ← your post table
     WHERE c.id = ?                     -- ← your comment PK
     LIMIT 1
";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $comment_id);
$stmt->execute();
$meta = $stmt->get_result()->fetch_assoc();

if (!$meta) {
    echo json_encode(['status'=>'error','msg'=>'Comment not found']);
    exit;
}

/* 3. permission check ----------------------------------------- */
$allowed = ($user_id == $meta['author_id'] || $user_id == $meta['post_owner']);

if (!$allowed) {
    echo json_encode(['status'=>'error','msg'=>'Not permitted']);
    exit;
}

/* 4. delete ---------------------------------------------------- */
$del = $con->prepare("DELETE FROM comments WHERE id = ?");
$del->bind_param('i', $comment_id);
$del->execute();

echo json_encode(['status'=>'success']);

