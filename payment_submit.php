<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';
if (empty($_SESSION['customer_id'])) { 
    header('Location: login.php'); 
    exit; 
}

$cid = (int)$_SESSION['customer_id'];
$pid = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
$rid = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$amt = isset($_POST['amount_display']) ? (float)$_POST['amount_display'] : 0.00;

// Validate required fields
if ($pid <= 0 || $rid <= 0) {
    header('Location: payment.php?error=' . urlencode('Invalid payment information.'));
    exit;
}

// If amount is 0, try to get it from the database
if ($amt <= 0) {
    $amt_query = mysqli_query($conn, "SELECT amount, (SELECT cost FROM service_request WHERE request_id = payment.request_id) as cost FROM payment WHERE payment_id = $pid");
    if ($amt_query && mysqli_num_rows($amt_query) > 0) {
        $amt_row = mysqli_fetch_assoc($amt_query);
        $amt = (float)$amt_row['amount'];
        if ($amt <= 0) $amt = (float)$amt_row['cost'];
        if ($amt <= 0) $amt = 14858.96; // Default fallback
    }
}

$sender_name = isset($_POST['sender_name']) ? trim($_POST['sender_name']) : '';
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
$receiver_name = isset($_POST['receiver_name']) ? trim($_POST['receiver_name']) : '';

// Check each field individually for better error messages
$missing_fields = [];
if (empty($sender_name)) $missing_fields[] = 'Sender Name';
if (empty($payment_method)) $missing_fields[] = 'Payment Method';
if (empty($reference_number)) $missing_fields[] = 'Reference Number';
if (empty($receiver_name)) $missing_fields[] = 'Receiver Name';

if (!empty($missing_fields)) {
    $error_msg = 'Missing required fields: ' . implode(', ', $missing_fields);
    header('Location: payment.php?error=' . urlencode($error_msg));
    exit;
}

// Handle file upload
$receipt_filename = '';
$upload_path = '';
if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file_tmp = $_FILES['receipt_image']['tmp_name'];
    $file_type = $_FILES['receipt_image']['type'];
    $file_size = $_FILES['receipt_image']['size'];
    $file_name = $_FILES['receipt_image']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate file type
    if (!in_array($file_type, $allowed_types)) {
        header('Location: payment.php?error=' . urlencode('Invalid file type. Only JPG, PNG, and GIF are allowed.'));
        exit;
    }
    
    // Validate file size
    if ($file_size > $max_size) {
        header('Location: payment.php?error=' . urlencode('File size exceeds 5MB limit.'));
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $receipt_filename = 'receipt_' . $pid . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $receipt_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $upload_path)) {
        header('Location: payment.php?error=' . urlencode('Failed to upload receipt. Please try again.'));
        exit;
    }
} else {
    // Check if there was a file upload error
    if (isset($_FILES['receipt_image'])) {
        $upload_error = $_FILES['receipt_image']['error'];
        if ($upload_error === UPLOAD_ERR_NO_FILE) {
            header('Location: payment.php?error=' . urlencode('Please upload a receipt image.'));
            exit;
        } elseif ($upload_error !== UPLOAD_ERR_OK) {
            header('Location: payment.php?error=' . urlencode('File upload error. Please try again.'));
            exit;
        }
    } else {
        header('Location: payment.php?error=' . urlencode('Please upload a receipt image.'));
        exit;
    }
}

// Update payment record with submission details - set status to 'pending' for admin approval
$sql = "UPDATE payment 
        SET amount = ?,
            payment_method = ?,
            payment_status = 'pending',
            submitted_at = NOW(),
            sender_name = ?,
            reference_number = ?,
            receiver_name = ?,
            receipt_image = ?,
            submitted_at = NOW(),
            payment_report = CONCAT(IFNULL(payment_report, ''), '[customer_submitted]')
        WHERE payment_id = ? AND request_id = ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    // Delete uploaded file if database update fails
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    header('Location: payment.php?error=' . urlencode('Database error. Please try again.'));
    exit;
}

// Bind parameters: d=double, s=string, i=integer
// amount, payment_method, sender_name, reference_number, receiver_name, receipt_image, payment_id, request_id
mysqli_stmt_bind_param($stmt, 'dsssssii', 
    $amt, 
    $payment_method, 
    $sender_name, 
    $reference_number, 
    $receiver_name, 
    $receipt_filename,
    $pid, 
    $rid
);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header('Location: customer-dashboard.php?success=' . urlencode('Payment submitted successfully! Your payment is now pending admin approval.'));
} else {
    mysqli_stmt_close($stmt);
    // Delete uploaded file if database update fails
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    header('Location: payment.php?error=' . urlencode('Failed to submit payment. Please try again.'));
}

exit;