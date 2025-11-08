<?php
include 'db.php';

function current_user() {
  return isset($_SESSION['customer']) ? $_SESSION['customer'] : null;
}
function login_customer_row($row) {
  $_SESSION['customer'] = [
    'customer_id' => $row['customer_id'],
    'name'        => $row['name'],
    'email'       => $row['email']
  ];
}
function logout_customer() {
  $_SESSION = [];
  if (session_id()) session_destroy();
}
