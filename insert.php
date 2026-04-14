<?php
// insert.php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require 'connect.php';

if (!isset($_SESSION['signup_data'])) {
    ob_end_clean();
    header("Location: sign_uppage.php");
    exit();
}

$data      = $_SESSION['signup_data'];
$full_name = $data['full_name'];
$gender    = $data['gender'];
$dob       = $data['dob'];
$phone     = $data['phone_number'];
$email     = $data['email_id'];
$username  = $data['user_name'];
$password  = password_hash($data['password'], PASSWORD_BCRYPT);

$stmt = $con->prepare("INSERT INTO users (full_name, gender, DOB, phone_number, email_id, user_name, password)
                       VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $full_name, $gender, $dob, $phone, $email, $username, $password);

if ($stmt->execute()) {
    unset($_SESSION['signup_data'], $_SESSION['otp'], $_SESSION['email']);
    ob_end_clean();
    header("Location: index.php");
    exit();
} else {
    echo "DB Insert Error: " . $stmt->error;
}

$stmt->close();
$con->close();