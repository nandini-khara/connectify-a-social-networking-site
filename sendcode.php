 <?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

session_start();
require 'connect.php'; // DB connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['identifier']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("Location: forgetpassword.php");
        exit();
    }

    $stmt = $con->prepare("SELECT * FROM users WHERE email_id = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "This email ID is not registered.";
        header("Location: forgetpassword.php");
        exit();
    } else {
        $mail = new PHPMailer(true);
        $otp = rand(100000, 999999); // Ensure it's 6 digits

        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'connectify408@gmail.com';
            $mail->Password   = 'cizljldxxvaygtws';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            //Recipients
            $mail->setFrom('connectify408@gmail.com', 'Connectify');
            $mail->addAddress($email);

            //Content
            $mail->isHTML(true);
            $mail->Subject = 'Forgot password?';
            $mail->Body    = 'Your 6-digit verification code is: <b>' . $otp . '</b>';

            $mail->send();
		session_start();
            $_SESSION['otp']=$otp;
		$_SESSION['email']=$email;
		 header("Location: enterotp.php");
            // Set success message and redirect to verify page
            $_SESSION['otp_sent'] = "An OTP has been sent to your email!";
            header("Location: enterotp.php");
            exit();

        } catch (Exception $e) {
            $_SESSION['error'] = "Message could not be sent. Error: {$mail->ErrorInfo}";
            header("Location: forgetpassword.php");
            exit();
        }
    }

    $stmt->close();
    $con->close();
} else {
    header("Location: forgetpassword.php");
    exit();
}
?>
