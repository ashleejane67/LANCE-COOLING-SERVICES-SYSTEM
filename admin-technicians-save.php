<?php
// admin-technicians-save.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';

if (empty($_SESSION['staff_role']) || $_SESSION['staff_role'] !== 'admin') {
  header('Location: staff-login.php'); exit;
}

$tid          = isset($_POST['technician_id']) ? (int)$_POST['technician_id'] : 0;
$name         = trim($_POST['name'] ?? '');
$email        = trim($_POST['email'] ?? '');
$phone        = trim($_POST['phone_number'] ?? '');
$position     = trim($_POST['position'] ?? '');
$username     = trim($_POST['username'] ?? '');
$password_raw = trim($_POST['password'] ?? '');

if ($name === '' || $username === '') {
  header('Location: admin-technicians.php?msg=' . urlencode('Name and Username are required.')); exit;
}

if ($tid > 0) {
  // UPDATE
  if ($password_raw !== '') {
    $hash = password_hash($password_raw, PASSWORD_BCRYPT);
    $sql  = "UPDATE technician
             SET name=?, email=?, phone_number=?, position=?, username=?, password_hash=?
             WHERE technician_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { header('Location: admin-technicians.php?msg=' . urlencode('DB error (prepare update w/ pass).')); exit; }
    mysqli_stmt_bind_param($stmt, 'ssssssi', $name, $email, $phone, $position, $username, $hash, $tid);
  } else {
    $sql  = "UPDATE technician
             SET name=?, email=?, phone_number=?, position=?, username=?
             WHERE technician_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { header('Location: admin-technicians.php?msg=' . urlencode('DB error (prepare update).')); exit; }
    mysqli_stmt_bind_param($stmt, 'sssssi', $name, $email, $phone, $position, $username, $tid);
  }
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);
  header('Location: admin-technicians.php?msg=' . urlencode('Technician updated.')); exit;

} else {
  // INSERT
  if ($password_raw === '') {
    header('Location: admin-technicians.php?msg=' . urlencode('Password is required for new technician.')); exit;
  }
  // unique username check
  $uq = mysqli_prepare($conn, "SELECT technician_id FROM technician WHERE username=? LIMIT 1");
  if ($uq) {
    mysqli_stmt_bind_param($uq, 's', $username);
    mysqli_stmt_execute($uq);
    $rs = mysqli_stmt_get_result($uq);
    if ($rs && mysqli_fetch_assoc($rs)) {
      mysqli_stmt_close($uq);
      header('Location: admin-technicians.php?msg=' . urlencode('Username already exists.')); exit;
    }
    mysqli_stmt_close($uq);
  }

  $hash = password_hash($password_raw, PASSWORD_BCRYPT);
  $sql  = "INSERT INTO technician (name, email, phone_number, position, username, password_hash, status, created_at)
           VALUES (?, ?, ?, ?, ?, ?, 'available', NOW())";
  $stmt = mysqli_prepare($conn, $sql);
  if (!$stmt) { header('Location: admin-technicians.php?msg=' . urlencode('DB error (prepare insert).')); exit; }
  mysqli_stmt_bind_param($stmt, 'ssssss', $name, $email, $phone, $position, $username, $hash);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_close($stmt);

  header('Location: admin-technicians.php?msg=' . urlencode('Technician added.')); exit;
}
