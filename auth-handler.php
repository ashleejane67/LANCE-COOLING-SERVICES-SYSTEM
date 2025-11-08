<?php
// auth-handler.php
session_start();
include 'db.php'; // expects $conn = mysqli_connect(...)

function go($p){ header("Location: $p"); exit; }

if (!isset($_POST['action'])) go('index.php');

$action = $_POST['action'];

if ($action === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $pass === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['auth_error'] = 'Please fill out all fields with a valid email.';
        go('index.php?auth=open&tab=register');
    }

    // check duplicate
    $stmt = mysqli_prepare($conn, "SELECT customer_id FROM customer WHERE email=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        $_SESSION['auth_error'] = 'That email is already registered.';
        mysqli_stmt_close($stmt);
        go('index.php?auth=open&tab=register');
    }
    mysqli_stmt_close($stmt);

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $username = $email; // keep simple, satisfies non-null username columns

    $stmt = mysqli_prepare($conn, "INSERT INTO customer (name, email, username, password) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $username, $hash);
    if (!mysqli_stmt_execute($stmt)) {
        $_SESSION['auth_error'] = 'Could not create account. Please try again.';
        mysqli_stmt_close($stmt);
        go('index.php?auth=open&tab=register');
    }
    $cid = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $_SESSION['customer_id'] = $cid;
    $_SESSION['customer_name'] = $name;
    $_SESSION['auth_ok'] = 'Account created. Welcome!';
    go('customer-dashboard.php');
}

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $_SESSION['auth_error'] = 'Please enter email and password.';
        go('index.php?auth=open&tab=login');
    }

    $stmt = mysqli_prepare($conn, "SELECT customer_id, name, password FROM customer WHERE email=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $cid, $name, $hash);

    if (mysqli_stmt_fetch($stmt) && password_verify($pass, $hash)) {
        $_SESSION['customer_id'] = $cid;
        $_SESSION['customer_name'] = $name;
        mysqli_stmt_close($stmt);
        go('customer-dashboard.php');
    }
    mysqli_stmt_close($stmt);

    $_SESSION['auth_error'] = 'Invalid email or password.';
    go('index.php?auth=open&tab=login');
}

go('index.php');
