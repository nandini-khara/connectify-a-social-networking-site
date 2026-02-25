<?php
$otp1=$_POST['otp'];
session_start();
if($_SESSION['otp']==$otp1)
{
echo '<script> alert("email is verified")</script>';
header('location:resetpassword.php');
}
else{
header('location:enterotp.php?msg=incorrect otp!');
}
?>