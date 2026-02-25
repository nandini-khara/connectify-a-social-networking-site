<?php
require('connect.php');
session_start();

if (!isset($_SESSION['email'])) {
    $_SESSION['reset_error'] = "Session expired. Please try again.";
    header('location:resetpassword.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pwd = $_POST['new_password'];
    $confirm_pwd = $_POST['confirm_password'];
    $email = $_SESSION['email'];

    // Check if both passwords match
    if ($pwd !== $confirm_pwd) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header('location:resetpassword.php');
        exit();
    }

    // Hash the password before storing (important!)
    $hashed_pwd = password_hash($pwd, PASSWORD_DEFAULT);

    // Update query
    $sql = "UPDATE users SET password = '$hashed_pwd' WHERE email_id = '$email'";
    $result = $con->query($sql);

    if ($result) {
        // Unset session and redirect
        unset($_SESSION['email']);
        header('location:index.php');
        exit();
    } else {
        $_SESSION['reset_error'] = "Error updating password. Please try again.";
        header('location:resetpassword.php');
        exit();
    }
}
?>
