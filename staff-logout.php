<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
session_unset();
session_destroy();
header('Location: staff-login.php?success='.urlencode('You are logged out.'));
exit;
