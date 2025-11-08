<?php
// staff_login_process.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php'; // must define $conn = mysqli_connect(...)

//
// 0) Read input
//
$login_id = isset($_POST['email']) ? trim($_POST['email']) : ''; // can be email OR username
$pass     = isset($_POST['password']) ? trim($_POST['password']) : '';

if ($login_id === '' || $pass === '') {
  header('Location: staff-login.php?error=' . urlencode('Please enter your credentials.'));
  exit;
}

//
// 1) Default ADMIN login (UNCHANGED - do not touch)
//
$default_email = 'admin@lance.local';
$default_pass  = 'admin123';

if (strcasecmp($login_id, $default_email) === 0 && $pass === $default_pass) {
  $_SESSION['admin_id']    = 1;
  $_SESSION['admin_email'] = $default_email;
  $_SESSION['role']        = 'admin';
  header('Location: admin-dashboard.php');
  exit;
}

/*
  2) TECHNICIAN login (NEW)
     - Accept email OR username in the same input field.
     - Verify against technician.password_hash (bcrypt).
     - Sessions: tech_id, tech_username, tech_email, role.
     - Redirect to technician area (change path if needed).
*/
if (! $conn) {
  header('Location: staff-login.php?error=' . urlencode('Database connection error.'));
  exit;
}

// Determine whether user typed an email or a username
$is_email = (strpos($login_id, '@') !== false);

// Build query safely (basic mysqli, no PDO)
$login_id_esc = mysqli_real_escape_string($conn, $login_id);
if ($is_email) {
  $sql = "SELECT technician_id, username, email, password_hash, name, status
          FROM technician
          WHERE email='{$login_id_esc}'
          LIMIT 1";
} else {
  $sql = "SELECT technician_id, username, email, password_hash, name, status
          FROM technician
          WHERE username='{$login_id_esc}'
          LIMIT 1";
}

$res = mysqli_query($conn, $sql);
if ($res && mysqli_num_rows($res) === 1) {
  $row = mysqli_fetch_assoc($res);

  // Optional: block inactive accounts
  if (isset($row['status']) && $row['status'] === 'inactive') {
    header('Location: staff-login.php?error=' . urlencode('Your account is inactive. Please contact admin.'));
    exit;
  }

  // Verify password using bcrypt hash stored in technician.password_hash
  if (!empty($row['password_hash']) && password_verify($pass, $row['password_hash'])) {
    // Success → set session and redirect
    $_SESSION['tech_id']        = (int)$row['technician_id'];
    $_SESSION['tech_username']  = $row['username'];
    $_SESSION['tech_email']     = $row['email'];
    $_SESSION['tech_name']      = $row['name'];
    $_SESSION['role']           = 'technician';
    $_SESSION['last_login_at']  = date('Y-m-d H:i:s');

    // (Optional) Update last_login in DB if you add that column later.
    // mysqli_query($conn, "UPDATE technician SET last_login=NOW() WHERE technician_id=".(int)$row['technician_id']);

    header('Location: technician-dashboard.php'); // create this page if not existing
    exit;
  }
}

// Fallback
header('Location: staff-login.php?error=' . urlencode('Invalid credentials.'));
exit;
