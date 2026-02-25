<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

session_start();
require 'connect.php'; // Even if not used now, keeps setup consistent
$_SESSION['signup_data'] = $_POST; // store full signup data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email_id']; // Already validated in signup page
    $otp = rand(100000, 999999);

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'connectify408@gmail.com';
        $mail->Password   = 'cizljldxxvaygtws';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('connectify408@gmail.com', 'Connectify');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - Connectify';
        $mail->Body    = "Hello,<br><br>Your OTP for email verification is: <b>$otp</b><br><br>Enter this code on the verification page to continue.";

        $mail->send();

        $_SESSION['otp'] = $otp;
        $_SESSION['email'] = $email;
        $_SESSION['otp_sent'] = "An OTP has been sent to your email.";

        header("Location: enterotp1.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "OTP could not be sent. Error: {$mail->ErrorInfo}";
        header("Location: sign_uppage.php");
        exit();
    }

} else {
    header("Location: sign_uppage.php");
    exit();
}
