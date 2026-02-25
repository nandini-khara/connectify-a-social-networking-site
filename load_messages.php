<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'], $_POST['user_id'])) {
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$other_id = (int) $_POST['user_id'];

// Mark messages as seen
$seen = $con->prepare("
    UPDATE messages
    SET status = 'seen', seen_at = NOW()
    WHERE receiver_id = ? AND sender_id = ? AND status IN ('sent','delivered')
");
$seen->bind_param("ii", $user_id, $other_id);
$seen->execute();

// Fetch messages WITH shared post info
$stmt = $con->prepare("
    SELECT m.sender_id, m.message_text, m.status, m.created_at,
           m.shared_post_id,
           p.post_text, p.post_img, p.post_video,
           u.user_name AS post_author
    FROM messages m
    LEFT JOIN post p ON m.shared_post_id = p.id
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE (m.sender_id=? AND m.receiver_id=?)
       OR (m.sender_id=? AND m.receiver_id=?)
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()):
    $isMe      = ($row['sender_id'] == $user_id);
    $sideClass = $isMe ? 'me' : 'them';
    $time      = date('h:i A', strtotime($row['created_at']));

    // Status ticks
    $statusIcon = '';
    if ($isMe) {
        if ($row['status'] === 'sent')      $statusIcon = '✔';
        elseif ($row['status'] === 'delivered') $statusIcon = '✔✔';
        elseif ($row['status'] === 'seen')  $statusIcon = '<span class="seen">✔✔</span>';
    }
?>

<div class="chat-message <?= $sideClass ?>">
  <div class="message-bubble">

    <?php if (!empty($row['shared_post_id'])): ?>
      <!-- ✅ Shared post preview card -->
      <div style="border:1px solid #ddd; border-radius:8px; padding:8px;
                  background:#f9f9f9; color:#333; font-size:13px; max-width:220px;">
        <div style="font-weight:600; margin-bottom:4px; color:#6a1b9a;">
          📤 Shared Post
        </div>
        <?php if (!empty($row['post_author'])): ?>
          <div style="font-size:11px; color:#888; margin-bottom:4px;">
            by @<?= htmlspecialchars($row['post_author']) ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($row['post_img'])): ?>
          <img src="<?= htmlspecialchars($row['post_img']) ?>"
               style="width:100%; border-radius:6px; margin-bottom:4px;">
        <?php endif; ?>
        <?php if (!empty($row['post_video'])): ?>
          <video src="<?= htmlspecialchars($row['post_video']) ?>"
                 controls style="width:100%; border-radius:6px; margin-bottom:4px;"></video>
        <?php endif; ?>
        <?php if (!empty($row['post_text'])): ?>
          <div style="font-size:12px;">
            <?= nl2br(htmlspecialchars(substr($row['post_text'], 0, 100))) ?>
            <?= strlen($row['post_text']) > 100 ? '...' : '' ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($row['message_text'])): ?>
      <div class="text"><?= nl2br(htmlspecialchars($row['message_text'])) ?></div>
    <?php endif; ?>

    <div class="meta">
      <span class="time"><?= $time ?></span>
      <span class="status"><?= $statusIcon ?></span>
    </div>

  </div>
</div>

<?php endwhile; ?>