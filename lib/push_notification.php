<?php
function pushNotification(
    mysqli $con,
    int $recipient,
    int $actor,
    string $type,
    ?int $postId,
    ?int $commentId,
    string $msg
): bool {
    // Don't notify self
    if ($recipient === $actor) return false;

    // Check if actor exists
    $check = $con->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $check->bind_param("i", $actor);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows === 0) return false; // Invalid actor

    // Insert notification
    $stmt = $con->prepare("
        INSERT INTO notifications (user_id, actor_id, type, post_id, comment_id, message)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisiis", $recipient, $actor, $type, $postId, $commentId, $msg);
    return $stmt->execute();
}
