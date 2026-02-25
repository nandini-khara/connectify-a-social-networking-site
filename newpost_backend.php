<?php 
session_start();
require 'connect.php'; // Make sure this file sets up $con

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$post_text = isset($_POST['post_text']) ? trim($_POST['post_text']) : '';
$post_img = '';
$post_video = '';

// ===== ADD THIS EMPTY POST CHECK HERE =====
$media = $_FILES['post_img'] ?? null;

if (empty($post_text) && (empty($media) || $media['error'] == UPLOAD_ERR_NO_FILE)) {
    echo "<script>alert('Post cannot be empty. Please add text, image, or video.'); window.history.back();</script>";
    exit();
}
// ==========================================

// Handle file upload if a file is provided
if (isset($_FILES['post_img']) && $_FILES['post_img']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['post_img']['tmp_name'];
    $fileName = $_FILES['post_img']['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'webm'];

    if (in_array($fileExt, $allowedExts)) {
        // Determine target subdirectory based on file type
        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
            $uploadDir = 'uploads/post/images/';
        } elseif (in_array($fileExt, ['mp4', 'mov', 'webm'])) {
            $uploadDir = 'uploads/post/videos/';
        }

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newFileName = uniqid('post_', true) . '.' . $fileExt;
        $targetPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $targetPath)) {
            if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
                $post_img = $targetPath;
            } elseif (in_array($fileExt, ['mp4', 'mov', 'webm'])) {
                $post_video = $targetPath;
            }
        }
    }
}

// Insert into database
$stmt = $con->prepare("INSERT INTO post (user_id, post_text, post_img, post_video) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $post_text, $post_img, $post_video);
$stmt->execute();
$stmt->close();

header("Location: home.php");
exit();
?>
