<?php
session_start();
require 'connect.php'; // your DB connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']); // Changed to match frontend's email field name
    $password = $_POST['password'];

    // Query to check email only
    $stmt = $con->prepare("SELECT * FROM users WHERE email_id = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password (assuming passwords are hashed in DB)
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['user_name'];
            
            // Redirect to home page
            header("Location: home.php");
            exit();
        } else {
            // Wrong password
            header("Location: index.php?error=" . urlencode("Login credentials not matched, try again!"));
            exit();
        }
    } else {
        // No user found with this email
        header("Location: index.php?error=" . urlencode("Login credentials not matched, try again!"));
        exit();
    }
    
    $stmt->close();
    $con->close();
} else {
    // If not a POST request, redirect to login page
    header("Location: index.php");
    exit();
}
?>