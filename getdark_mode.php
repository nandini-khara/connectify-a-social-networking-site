<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    return;
}

require_once 'connect.php';

$user_id = $_SESSION['user_id'];

$query = "SELECT dark_mode FROM users WHERE user_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['dark_mode'] == 1) {
        echo '<link rel="stylesheet" href="darkmode.css">';
    }
}
?>
