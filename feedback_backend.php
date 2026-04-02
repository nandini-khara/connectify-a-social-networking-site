<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

require 'connect.php';

$user_id  = (int) $_SESSION['user_id'];
$rating   = isset($_POST['rating'])   ? (int)   $_POST['rating']              : 0;
$reaction = isset($_POST['reaction']) ? trim(strip_tags($_POST['reaction']))   : '';
$text     = isset($_POST['text'])     ? trim(strip_tags($_POST['text']))       : '';

/* Validate: need at least one input */
if ($rating === 0 && $reaction === '' && $text === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please provide at least a rating, reaction, or review.']);
    exit();
}

/* Clamp rating to 0–5 */
$rating = max(0, min(5, $rating));

/* One-submission-per-user check */
$check = $con->prepare("SELECT feedback_id FROM user_feedback WHERE user_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    echo json_encode(['status' => 'error', 'message' => 'You have already submitted feedback.']);
    exit();
}

/* Insert */
$stmt = $con->prepare(
    "INSERT INTO user_feedback (user_id, rating, reaction, feedback_text, created_at)
     VALUES (?, ?, ?, ?, NOW())"
);
$stmt->bind_param("iiss", $user_id, $rating, $reaction, $text);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
}

$stmt->close();
?>