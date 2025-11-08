<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_id']) && (empty($_SESSION['role']) || $_SESSION['role']!=='admin')) {
  header('Location: staff-login.php'); exit;
}
