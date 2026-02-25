<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ------------------------------------------------------------------
   1.  Handle file uploads (exactly as before)
------------------------------------------------------------------ */
$profileImagePath    = '';
$backgroundImagePath = '';

if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $profileImageName = time() . '_' . basename($_FILES['profile_image']['name']);
    $profileImagePath = 'uploads/' . $profileImageName;
    move_uploaded_file($_FILES['profile_image']['tmp_name'], $profileImagePath);
}

if (!empty($_FILES['background_image']['name']) && $_FILES['background_image']['error'] === UPLOAD_ERR_OK) {
    $bgImageName        = time() . '_' . basename($_FILES['background_image']['name']);
    $backgroundImagePath = 'uploads/' . $bgImageName;
    move_uploaded_file($_FILES['background_image']['tmp_name'], $backgroundImagePath);
}

/* ------------------------------------------------------------------
   2.  New — detect deletion requests
------------------------------------------------------------------ */
$deleteProfile    = isset($_POST['delete_profile_image'])    && $_POST['delete_profile_image']    === '1';
$deleteBackground = isset($_POST['delete_background_image']) && $_POST['delete_background_image'] === '1';

/* ------------------------------------------------------------------
   3.  Fetch current image paths so we can unlink if needed
------------------------------------------------------------------ */
$currentQuery = "SELECT profile_image, background_image FROM users WHERE user_id = ?";
$stmtCur      = $con->prepare($currentQuery);
$stmtCur->bind_param("i", $user_id);
$stmtCur->execute();
$current      = $stmtCur->get_result()->fetch_assoc() ?? ['profile_image'=>'','background_image'=>''];
$stmtCur->close();

/* ------------------------------------------------------------------
   4.  Gather the rest of the form data (unchanged)
------------------------------------------------------------------ */
$full_name    = $_POST['full_name'];
$user_name    = $_POST['user_name'];
$phone_number = $_POST['phone_number'];
$bio          = $_POST['Bio'];

/* ------------------------------------------------------------------
   5.  Build the dynamic UPDATE statement
------------------------------------------------------------------ */
$updateQuery = "UPDATE users SET full_name = ?, user_name = ?, phone_number = ?, Bio = ?";
$params = [$full_name, $user_name, $phone_number, $bio];
$types  = "ssss";

/* ---------- profile image ---------- */
if ($profileImagePath !== '') {                               // new upload
    if (!empty($current['profile_image']) && file_exists($current['profile_image'])) {
        @unlink($current['profile_image']);
    }
    $updateQuery .= ", profile_image = ?";
    $params[] = $profileImagePath;
    $types   .= "s";
} elseif ($deleteProfile) {                                   // delete only
    if (!empty($current['profile_image']) && file_exists($current['profile_image'])) {
        @unlink($current['profile_image']);
    }
    $updateQuery .= ", profile_image = NULL";
}

/* ---------- background image ---------- */
if ($backgroundImagePath !== '') {                            // new upload
    if (!empty($current['background_image']) && file_exists($current['background_image'])) {
        @unlink($current['background_image']);
    }
    $updateQuery .= ", background_image = ?";
    $params[] = $backgroundImagePath;
    $types   .= "s";
} elseif ($deleteBackground) {                                // delete only
    if (!empty($current['background_image']) && file_exists($current['background_image'])) {
        @unlink($current['background_image']);
    }
    $updateQuery .= ", background_image = NULL";
}

/* ---------- where clause ---------- */
$updateQuery .= " WHERE user_id = ?";
$params[] = $user_id;
$types   .= "i";

/* ------------------------------------------------------------------
   6.  Execute
------------------------------------------------------------------ */
$stmt = $con->prepare($updateQuery);
if ($stmt === false) {
    die("Prepare failed: " . $con->error);
}
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    header("Location: myprofile_frontend.php");
    exit();
} else {
    echo "Update failed: " . $stmt->error;
}

$stmt->close();
$con->close();
?>
