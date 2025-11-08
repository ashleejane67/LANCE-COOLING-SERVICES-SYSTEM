<?php
// db.php — BASIC: sessions + mysqli (no PDO)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'lance_service';

$conn = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$conn) {
    die('Could not connect to MySQL. Please verify db.php credentials and that MySQL is running.');
}
mysqli_set_charset($conn, 'utf8mb4');

// Backward-compatibility aliases
$con        = $conn;
$connection = $conn;
