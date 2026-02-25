<?php
session_start();
require 'connect.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$con->set_charset('utf8mb4');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: settings_frontend.php");
    exit();
}

$password = $_POST['password'] ?? '';

$stmt = $con->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($hash);

if (!$stmt->fetch() || !password_verify($password, $hash)) {
    echo "<script>alert('Incorrect password.'); window.location.href='delete_frontend.php';</script>";
    exit();
}
$stmt->close();

$con->begin_transaction();

try {
    // (a) Likes given by user
    $q = $con->prepare("DELETE FROM likes WHERE user_id = ?");
    $q->bind_param("i", $user_id);
    $q->execute();

    // (b) Likes on user's posts
    $q = $con->prepare("
        DELETE l
        FROM likes l
        JOIN post p ON p.id = l.post_id
        WHERE p.user_id = ?");
    $q->bind_param("i", $user_id);
    $q->execute();

    // (c) Comments written by user
    $q = $con->prepare("DELETE FROM comments WHERE user_id = ?");
    $q->bind_param("i", $user_id);
    $q->execute();

    // (d) Comments on user's posts
    $q = $con->prepare("
        DELETE c
        FROM comments c
        JOIN post p ON p.id = c.post_id
        WHERE p.user_id = ?");
    $q->bind_param("i", $user_id);
    $q->execute();

    // (e) Follow relationships
    $q = $con->prepare("DELETE FROM follows WHERE follower_id = ? OR following_id = ?");
    $q->bind_param("ii", $user_id, $user_id);
    $q->execute();

    // (f) Posts
    $q = $con->prepare("DELETE FROM post WHERE user_id = ?");
    $q->bind_param("i", $user_id);
    $q->execute();

    // (g) Finally delete user
    $q = $con->prepare("DELETE FROM users WHERE user_id = ?");
    $q->bind_param("i", $user_id);
    $q->execute();

    $con->commit();
    session_unset();
    session_destroy();
    header("Location: account_deleted.php");
    exit();

} catch (mysqli_sql_exception $e) {
    $con->rollback();
    error_log("Account-delete failed: ".$e->getMessage());
    echo "<pre style='color:red'>SQL ERROR: ".$e->getMessage()."</pre>";
    exit();
}
?>
