<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['role'], $_SESSION['tech_id'], $_SESSION['tech_email']);
header('Location: staff-login.php');
exit;
