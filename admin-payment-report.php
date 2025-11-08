<?php
// admin-payment-approve.php - Handle payment approval/rejection
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';
require_once 'admin-header.inc.php';

// Get form data
$payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$return_url = isset($_POST['return_url']) ? $_POST['return_url'] : 'admin-payments.php';

if ($payment_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    header('Location: admin-payments.php?error=' . urlencode('Invalid request.'));
    exit;
}

// Get payment details
$sql = "
SELECT p.payment_id, p.request_id, p.amount, p.payment_status, 
       r.customer_id, c.name AS customer_name, c.email AS customer_email,
       r.appliance_type
FROM payment p
JOIN service_request r ON r.request_id = p.request_id
JOIN customer c ON c.customer_id = r.customer_id
WHERE p.payment_id = ?
LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $payment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$payment) {
    header('Location: admin-payments.php?error=' . urlencode('Payment not found.'));
    exit;
}

// Get admin/staff ID
$admin_id = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 1);

if ($action === 'approve') {
    // Approve payment - mark as paid
    $update_sql = "
    UPDATE payment 
    SET payment_status = 'paid', 
        payment_date = NOW(), 
        approved_by = ?,
        approved_at = NOW()
    WHERE payment_id = ? 
    LIMIT 1";
    
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, 'ii', $admin_id, $payment_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        mysqli_stmt_close($update_stmt);
        
        // Send notification to customer
        $notif_sql = "
        INSERT INTO notifications (user_type, user_id, title, message, request_id, created_at) 
        VALUES ('customer', ?, 'Payment Approved', ?, ?, NOW())";
        
        $notif_message = "Your payment of ₱" . number_format($payment['amount'], 2) . " for " . $payment['appliance_type'] . " service has been approved and confirmed.";
        
        $notif_stmt = mysqli_prepare($conn, $notif_sql);
        mysqli_stmt_bind_param($notif_stmt, 'isi', $payment['customer_id'], $notif_message, $payment['request_id']);
        mysqli_stmt_execute($notif_stmt);
        mysqli_stmt_close($notif_stmt);
        
        // Send notification to admin
        $admin_notif_sql = "
        INSERT INTO notifications (user_type, user_id, title, message, request_id, created_at) 
        VALUES ('admin', 1, 'Payment Approved', ?, ?, NOW())";
        
        $admin_message = "Payment #" . $payment_id . " from " . $payment['customer_name'] . " has been approved. Amount: ₱" . number_format($payment['amount'], 2);
        
        $admin_stmt = mysqli_prepare($conn, $admin_notif_sql);
        mysqli_stmt_bind_param($admin_stmt, 'si', $admin_message, $payment['request_id']);
        mysqli_stmt_execute($admin_stmt);
        mysqli_stmt_close($admin_stmt);
        
        header('Location: ' . $return_url . '?success=' . urlencode('Payment approved successfully!'));
        exit;
    } else {
        header('Location: ' . $return_url . '?error=' . urlencode('Failed to approve payment: ' . mysqli_error($conn)));
        exit;
    }
    
} elseif ($action === 'reject') {
    // Reject payment - set back to unpaid and clear receipt
    $update_sql = "
    UPDATE payment 
    SET payment_status = 'unpaid',
        payment_method = NULL,
        reference_number = NULL,
        sender_name = NULL,
        receiver_name = NULL,
        receipt_image = NULL,
        submitted_at = NULL,
        approved_by = NULL,
        approved_at = NULL
    WHERE payment_id = ? 
    LIMIT 1";
    
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, 'i', $payment_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        mysqli_stmt_close($update_stmt);
        
        // Send notification to customer
        $notif_sql = "
        INSERT INTO notifications (user_type, user_id, title, message, request_id, created_at) 
        VALUES ('customer', ?, 'Payment Rejected', ?, ?, NOW())";
        
        $notif_message = "Your payment submission for " . $payment['appliance_type'] . " service has been rejected. Please resubmit with correct payment details and receipt.";
        
        $notif_stmt = mysqli_prepare($conn, $notif_sql);
        mysqli_stmt_bind_param($notif_stmt, 'isi', $payment['customer_id'], $notif_message, $payment['request_id']);
        mysqli_stmt_execute($notif_stmt);
        mysqli_stmt_close($notif_stmt);
        
        header('Location: ' . $return_url . '?success=' . urlencode('Payment rejected. Customer will need to resubmit.'));
        exit;
    } else {
        header('Location: ' . $return_url . '?error=' . urlencode('Failed to reject payment: ' . mysqli_error($conn)));
        exit;
    }
}

// Fallback
header('Location: admin-payments.php');
exit;