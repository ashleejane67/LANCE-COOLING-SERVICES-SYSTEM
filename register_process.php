<?php
require_once 'db.php'; // gives $conn and the session

// --- helper: read the first non-empty POST value that matches any of the keys/regexes ---
function post_pick(array $candidates, $default = '') {
    foreach ($candidates as $c) {
        if (is_string($c) && isset($_POST[$c]) && trim($_POST[$c]) !== '') {
            return trim($_POST[$c]);
        }
        if ($c instanceof Closure) { // not used, but kept for extensibility
            $v = $c();
            if ($v !== '') return $v;
        }
        if (is_array($c) && isset($c['re'])) {           // regex match against keys
            foreach ($_POST as $k => $v) {
                if (preg_match($c['re'], $k) && trim($v) !== '') {
                    return trim($v);
                }
            }
        }
    }
    return $default;
}

// We will NOT assume any specific input names.
// Try the most common ones; also scan keys by regex (case-insensitive).
$name     = post_pick(['full_name', 'fullname', 'name', ['re'=>'/name/i']]);
$email    = post_pick(['email', ['re'=>'/email/i']]);
$username = post_pick(['username','user','uname',['re'=>'/user(name)?/i']]); // optional
$passRaw  = post_pick(['password','pass','pwd',['re'=>'/pass(word)?/i']]);

if ($name === '' || $email === '' || $passRaw === '') {
    header('Location: register.php?error=' . urlencode('Please fill in all fields.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php?error=' . urlencode('Please enter a valid email.'));
    exit;
}

// If username wasn't provided, derive from email prefix (keep it alnum/_)
if ($username === '') {
    $username = preg_replace('/[^a-z0-9_]+/i', '', strtok($email, '@'));
    if ($username === '') { $username = 'user'; }
}

// Make sure email/username are unique
$check = mysqli_prepare($conn, "SELECT customer_id, email, username FROM customer WHERE email=? OR username=? LIMIT 1");
if (!$check) {
    header('Location: register.php?error=' . urlencode('Database error (prepare check).'));
    exit;
}
mysqli_stmt_bind_param($check, 'ss', $email, $username);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_bind_result($check, $cid, $em, $un);
    mysqli_stmt_fetch($check);
    mysqli_stmt_free_result($check);
    mysqli_stmt_close($check);

    // If email exists -> stop
    if (strcasecmp($em ?? '', $email) === 0) {
        header('Location: register.php?error=' . urlencode('Email is already registered.'));
        exit;
    }
    // If username exists -> try to make a new one automatically
    $base = $username;
    for ($i = 0; $i < 6; $i++) {
        $try = $base . rand(100, 999);
        $chkUN = mysqli_prepare($conn, "SELECT customer_id FROM customer WHERE username=? LIMIT 1");
        mysqli_stmt_bind_param($chkUN, 's', $try);
        mysqli_stmt_execute($chkUN);
        mysqli_stmt_store_result($chkUN);
        if (mysqli_stmt_num_rows($chkUN) === 0) {
            $username = $try;
            mysqli_stmt_free_result($chkUN);
            mysqli_stmt_close($chkUN);
            break;
        }
        mysqli_stmt_free_result($chkUN);
        mysqli_stmt_close($chkUN);
    }
} else {
    mysqli_stmt_free_result($check);
    mysqli_stmt_close($check);
}

// Hash the password
$hash = password_hash($passRaw, PASSWORD_DEFAULT);

// Insert
$ins = mysqli_prepare($conn, "INSERT INTO customer (name, email, username, password_hash) VALUES (?, ?, ?, ?)");
if (!$ins) {
    header('Location: register.php?error=' . urlencode('Database error (prepare INSERT).'));
    exit;
}
mysqli_stmt_bind_param($ins, 'ssss', $name, $email, $username, $hash);
if (!mysqli_stmt_execute($ins)) {
    mysqli_stmt_close($ins);
    header('Location: register.php?error=' . urlencode('Database error (execute INSERT).'));
    exit;
}
$newId = mysqli_insert_id($conn);
mysqli_stmt_close($ins);

// Log the customer in immediately
$_SESSION['customer_id']    = (int)$newId;
$_SESSION['customer_name']  = $name;
$_SESSION['customer_email'] = $email;
$_SESSION['customer_user']  = $username;

// To the dashboard
header('Location: customer-dashboard.php');
exit;
