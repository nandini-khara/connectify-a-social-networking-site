<?php
$host = 'localhost';
$port = 3307; // updated port
$user = 'root';
$password = '';
$database = 'CONNECTIFY';

// Create connection with custom port
$con = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

$con->set_charset("utf8mb4");
?>
