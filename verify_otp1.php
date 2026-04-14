<?php
// verify_otp1.php
session_start();
$otp2 = $_POST['otp'];

if ($_SESSION['otp'] == $otp2) {
    // Don't echo anything before header()
    header('Location: insert.php');
    exit();
} else {
    header('Location: enterotp1.php?msg=Incorrect+OTP!');
    exit();
}
?>