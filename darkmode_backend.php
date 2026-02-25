<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Not logged in");
}

if (isset($_POST['dark_mode'])) {
    $user_id = $_SESSION['user_id'];
    $dark_mode = $_POST['dark_mode'] == 1 ? 1 : 0;

    $query = "UPDATE users SET dark_mode = ? WHERE user_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $dark_mode, $user_id);
    if ($stmt->execute()) {
        echo "Mode updated";
    } else {
        http_response_code(500);
        echo "Error updating mode";
    }
}
?>
