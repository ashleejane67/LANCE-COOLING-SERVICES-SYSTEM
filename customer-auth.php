<?php
// Require session + DB, then ensure a signed-in customer exists
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';

if (empty($_SESSION['customer_id'])) {
    // not signed in → send to login page you already have
    header('Location: login.php');
    exit;
}
