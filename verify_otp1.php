<?php
$otp2=$_POST['otp'];
session_start();
if($_SESSION['otp']==$otp2)
{
echo '<script> alert("email is verified")</script>';
header('location:insert.php');
}
else{
header('location:enterotp1.php?msg=incorrect otp!');
}
?>