<?php
require_once 'db.php';

// Don’t assume field names: accept common ones or detect by regex.
function post_pick(array $candidates, $default = '') {
    foreach ($candidates as $c) {
        if (is_string($c) && isset($_POST[$c]) && trim($_POST[$c]) !== '') {
            return trim($_POST[$c]);
        }
        if (is_array($c) && isset($c['re'])) {
            foreach ($_POST as $k => $v) {
                if (preg_match($c['re'], $k) && trim($v) !== '') {
                    return trim($v);
                }
            }
        }
    }
    return $default;
}

$loginId = post_pick(['login_id','login','email','username',['re'=>'/(email|user(name)?)/i']]);
$passRaw = post_pick(['password','pass','pwd',['re'=>'/pass(word)?/i']]);

if ($loginId === '' || $passRaw === '') {
    header('Location: login.php?error=' . urlencode('Please fill in all fields.'));
    exit;
}

// Fetch by email or username
$sql = "SELECT customer_id, name, email, username, password_hash
        FROM customer
        WHERE email = ? OR username = ?
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    header('Location: login.php?error=' . urlencode('Database error (prepare).'));
    exit;
}
mysqli_stmt_bind_param($stmt, 'ss', $loginId, $loginId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$row || !password_verify($passRaw, $row['password_hash'])) {
    header('Location: login.php?error=' . urlencode('Invalid credentials.'));
    exit;
}

// ok
$_SESSION['customer_id']    = (int)$row['customer_id'];
$_SESSION['customer_name']  = $row['name'];
$_SESSION['customer_email'] = $row['email'];
$_SESSION['customer_user']  = $row['username'];

header('Location: customer-dashboard.php');
exit;
