<?php
/* --------------------------------------------------------------
   load_comments.php
   Returns the HTML block for all comments on a single post
   -------------------------------------------------------------- */
session_start();
require 'connect.php';

/* ---- 0. quick validation ------------------------------------ */
if (!isset($_POST['post_id'])) {
    http_response_code(400);
    exit('No post ID');
}
$post_id  = (int)$_POST['post_id'];
$user_id  = $_SESSION['user_id'] ?? 0;

/* ---- 1. who owns the post? ---------------------------------- */
$postOwnerId = null;
$postQ = $con->prepare("SELECT user_id FROM post WHERE id = ? LIMIT 1");
$postQ->bind_param('i', $post_id);
$postQ->execute();
$postOwnerId = $postQ->get_result()->fetch_column();

/* ---- 2. fetch all comments (+ author data) ------------------ */
$sql = "
  SELECT c.id, c.comment_text, c.commented_at, c.user_id,
         u.user_name, u.profile_image
    FROM comments c
    JOIN users u ON u.user_id = c.user_id
   WHERE c.post_id = ?
   ORDER BY c.commented_at
";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $post_id);
$stmt->execute();
$result = $stmt->get_result();

/* ---- 3. render ---------------------------------------------- */
while ($c = $result->fetch_assoc()) {

    $avatar   = $c['profile_image'] ?: 'uploads/default-profile.png';
    $isAuthor = ($c['user_id'] == $user_id);
    $isOwner  = ($user_id == $postOwnerId);
    $canDel   = ($isAuthor || $isOwner);

    $profileLink = $isAuthor
        ? 'myprofile_frontend.php'
        : 'public_profile.php?user_id=' . $c['user_id'];

    echo '
    <div class="comment d-flex mb-2 align-items-start"
         data-comment-id="' . $c['id'] . '">

      <a href="' . $profileLink . '" class="me-2">
        <img src="' . htmlspecialchars($avatar) . '" class="c-avatar" alt="">
      </a>

      <div class="flex-grow-1">
        <a href="' . $profileLink . '">
          <strong>@' . htmlspecialchars($c['user_name']) . '</strong>
        </a> ' .
        nl2br(htmlspecialchars($c['comment_text'])) . '<br>
        <small class="text-muted">' .
          date('d M Y, h:i A', strtotime($c['commented_at'])) .
        '</small>
      </div>';

      /* Optional delete button (author OR post owner) */
      if ($canDel) {
        echo '
        <button class="delete-comment btn btn-sm btn-link text-danger p-0 ms-2"
                title="Delete"
                data-comment-id="' . $c['id'] . '">
          <i class="fas fa-trash-alt"></i>
        </button>';
      }

    echo '</div>';
}
