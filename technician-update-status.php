<?php
// technician-update-status.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician' || !isset($_SESSION['tech_id'])) {
  header('Location: staff-login.php?error=' . urlencode('Please login as technician.'));
  exit;
}

require_once 'db.php';

$TECH_ID = (int)$_SESSION['tech_id'];
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$new_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$return_to = isset($_GET['return']) ? $_GET['return'] : 'dashboard';

// Validate inputs
if (!$request_id || !$new_status) {
  header('Location: technician-dashboard.php?error=' . urlencode('Invalid request.'));
  exit;
}

// Validate status
$valid_statuses = ['pending', 'scheduled', 'in_progress', 'completed', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
  header('Location: technician-dashboard.php?error=' . urlencode('Invalid status.'));
  exit;
}

// Verify this job is assigned to this technician
$check_query = "SELECT request_id, status FROM service_request 
                WHERE request_id = ? AND technician_id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, 'ii', $request_id, $TECH_ID);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$job = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

if (!$job) {
  header('Location: technician-dashboard.php?error=' . urlencode('Job not found or not assigned to you.'));
  exit;
}

// Update service request status
$update_query = "UPDATE service_request SET status = ? WHERE request_id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, 'si', $new_status, $request_id);
$update_success = mysqli_stmt_execute($update_stmt);
mysqli_stmt_close($update_stmt);

if ($update_success) {
  // Insert into job_tracking for history
  $tracking_query = "INSERT INTO job_tracking (request_id, status, updated_at) VALUES (?, ?, NOW())";
  $tracking_stmt = mysqli_prepare($conn, $tracking_query);
  mysqli_stmt_bind_param($tracking_stmt, 'is', $request_id, $new_status);
  mysqli_stmt_execute($tracking_stmt);
  mysqli_stmt_close($tracking_stmt);
  
  // Create notification for customer
  $customer_query = "SELECT customer_id FROM service_request WHERE request_id = ?";
  $customer_stmt = mysqli_prepare($conn, $customer_query);
  mysqli_stmt_bind_param($customer_stmt, 'i', $request_id);
  mysqli_stmt_execute($customer_stmt);
  $customer_result = mysqli_stmt_get_result($customer_stmt);
  $customer = mysqli_fetch_assoc($customer_result);
  mysqli_stmt_close($customer_stmt);
  
  if ($customer) {
    $status_label = ucfirst(str_replace('_', ' ', $new_status));
    $notification_title = "Service Request Updated";
    $notification_message = "Your service request #$request_id status has been updated to: $status_label";
    
    $notif_query = "INSERT INTO notifications (user_type, user_id, title, message, request_id, created_at) 
                    VALUES ('customer', ?, ?, ?, ?, NOW())";
    $notif_stmt = mysqli_prepare($conn, $notif_query);
    mysqli_stmt_bind_param($notif_stmt, 'issi', $customer['customer_id'], $notification_title, $notification_message, $request_id);
    mysqli_stmt_execute($notif_stmt);
    mysqli_stmt_close($notif_stmt);
  }
  
  // Create notification for admin
  $admin_notification_title = "Technician Status Update";
  $admin_notification_message = "Request #$request_id status updated to: " . ucfirst(str_replace('_', ' ', $new_status));
  
  $admin_notif_query = "INSERT INTO notifications (user_type, user_id, title, message, request_id, created_at) 
                        VALUES ('admin', 1, ?, ?, ?, NOW())";
  $admin_notif_stmt = mysqli_prepare($conn, $admin_notif_query);
  mysqli_stmt_bind_param($admin_notif_stmt, 'ssi', $admin_notification_title, $admin_notification_message, $request_id);
  mysqli_stmt_execute($admin_notif_stmt);
  mysqli_stmt_close($admin_notif_stmt);
  
  $success_msg = "Job status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . " successfully!";
  
  // Redirect based on return parameter and status
  if ($new_status === 'completed') {
    // If completed, redirect to work history
    header('Location: technician-work-history.php?success=' . urlencode($success_msg));
  } elseif ($return_to === 'detail') {
    header('Location: technician-job-detail.php?id=' . $request_id . '&success=' . urlencode($success_msg));
  } else {
    header('Location: technician-dashboard.php?success=' . urlencode($success_msg));
  }
} else {
  $error_msg = "Failed to update job status. Please try again.";
  if ($return_to === 'detail') {
    header('Location: technician-job-detail.php?id=' . $request_id . '&error=' . urlencode($error_msg));
  } else {
    header('Location: technician-dashboard.php?error=' . urlencode($error_msg));
  }
}
exit;
?>