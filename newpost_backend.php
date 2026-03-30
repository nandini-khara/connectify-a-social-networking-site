<?php
/**
 * newpost_backend.php — MERGED BEST VERSION
 *
 * Combines:
 *  ✅ Your original folder structure (uploads/post/images/ and videos/)
 *  ✅ Your original empty-post check
 *  ✅ Your original file type whitelist
 *  ✅ Your original directory auto-creation
 *  ✅ My text moderation (blocks vulgar captions)
 *  ✅ My image/video AI moderation (Sightengine — blocks nudity/violence)
 */

session_start();
require 'connect.php';
require_once 'content_moderation.php';   // ← the moderation file

// Login check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$post_text  = isset($_POST['post_text']) ? trim($_POST['post_text']) : '';
$post_img   = '';
$post_video = '';

/* ── 1. EMPTY POST CHECK (from your original) ─────────────────── */
$media = $_FILES['post_img'] ?? null;
if (empty($post_text) && (empty($media) || $media['error'] == UPLOAD_ERR_NO_FILE)) {
    echo "<script>alert('Post cannot be empty. Please add text, image, or video.'); window.history.back();</script>";
    exit();
}

/* ── 2. TEXT MODERATION (blocks vulgar captions) ──────────────── */
if ($post_text !== '') {
    $textCheck = moderateText($post_text);
    if (!$textCheck['ok']) {
        // Same style as your original error handling
        $msg = addslashes($textCheck['reason']);
        echo "<script>alert('$msg'); window.history.back();</script>";
        exit();
    }
}

/* ── 3. FILE UPLOAD + MEDIA MODERATION ────────────────────────── */
if (isset($_FILES['post_img']) && $_FILES['post_img']['error'] === UPLOAD_ERR_OK) {
    $fileTmp  = $_FILES['post_img']['tmp_name'];
    $fileName = $_FILES['post_img']['name'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $imageExts = ['jpg', 'jpeg', 'png', 'gif'];
    $videoExts = ['mp4', 'mov', 'webm'];
    $allowedExts = array_merge($imageExts, $videoExts);

    if (!in_array($fileExt, $allowedExts)) {
        echo "<script>alert('File type not allowed. Please upload an image (jpg/png/gif) or video (mp4/mov/webm).'); window.history.back();</script>";
        exit();
    }

    // ── AI moderation BEFORE saving ──────────────────────────────
    // (File is still in tmp — if it fails, nothing is saved to disk)
    if (in_array($fileExt, $imageExts)) {
        $mediaCheck = moderateFile($fileTmp, 'image');
    } else {
        $mediaCheck = moderateFile($fileTmp, 'video');
    }

    if (!$mediaCheck['ok']) {
        $msg = addslashes($mediaCheck['reason']);
        echo "<script>alert('$msg'); window.history.back();</script>";
        exit();
    }

    // ── Passed moderation — now save to your folder structure ────
    if (in_array($fileExt, $imageExts)) {
        $uploadDir = 'uploads/post/images/';
    } else {
        $uploadDir = 'uploads/post/videos/';
    }

    // Auto-create directory (from your original)
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName = uniqid('post_', true) . '.' . $fileExt;
    $targetPath  = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmp, $targetPath)) {
        if (in_array($fileExt, $imageExts)) {
            $post_img = $targetPath;
        } else {
            $post_video = $targetPath;
        }
    } else {
        echo "<script>alert('File upload failed. Please try again.'); window.history.back();</script>";
        exit();
    }
}

/* ── 4. INSERT INTO DATABASE (same as your original) ──────────── */
$stmt = $con->prepare("INSERT INTO post (user_id, post_text, post_img, post_video) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $post_text, $post_img, $post_video);
$stmt->execute();
$stmt->close();

header("Location: home.php");
exit();