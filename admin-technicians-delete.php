<?php
// admin-technicians-delete.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';

if (empty($_SESSION['staff_role']) || $_SESSION['staff_role'] !== 'admin') {
  header('Location: staff-login.php'); exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
  $stmt = mysqli_prepare($conn, "DELETE FROM technician WHERE technician_id=?");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
}
header('Location: admin-technicians.php?msg=' . urlencode('Technician deleted.'));
exit;
