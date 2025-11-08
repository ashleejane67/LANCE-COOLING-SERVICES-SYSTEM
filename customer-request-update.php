<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';

if (empty($_SESSION['customer_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: login.php');
  exit;
}

$cid = (int)$_SESSION['customer_id'];
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$appliance_type = isset($_POST['appliance_type']) ? trim($_POST['appliance_type']) : '';
$services = isset($_POST['services']) ? (array)$_POST['services'] : [];

// Validate input
if ($request_id <= 0 || empty($appliance_type) || empty($services)) {
  header('Location: customer-request-edit.php?id=' . $request_id . '&error=' . urlencode('Please fill in all required fields.'));
  exit;
}

// Verify request exists and belongs to customer and is pending
$verify_query = "SELECT request_id, status FROM service_request 
                 WHERE request_id = ? AND customer_id = ? AND status = 'pending'";
$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, 'ii', $request_id, $cid);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_fetch_assoc(mysqli_stmt_get_result($verify_stmt));
mysqli_stmt_close($verify_stmt);

if (!$verify_result) {
  header('Location: customer-dashboard.php?error=' . urlencode('Cannot update this request.'));
  exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
  // Update appliance type
  $update_query = "UPDATE service_request SET appliance_type = ?, updated_at = NOW() WHERE request_id = ?";
  $update_stmt = mysqli_prepare($conn, $update_query);
  mysqli_stmt_bind_param($update_stmt, 'si', $appliance_type, $request_id);
  if (!mysqli_stmt_execute($update_stmt)) {
    throw new Exception('Failed to update request');
  }
  mysqli_stmt_close($update_stmt);

  // Delete old service lines
  $delete_query = "DELETE FROM service_line WHERE request_id = ?";
  $delete_stmt = mysqli_prepare($conn, $delete_query);
  mysqli_stmt_bind_param($delete_stmt, 'i', $request_id);
  if (!mysqli_stmt_execute($delete_stmt)) {
    throw new Exception('Failed to update services');
  }
  mysqli_stmt_close($delete_stmt);

  // Insert new service lines
  $service_insert_query = "INSERT INTO service_line (request_id, service_id, service_type, problem_description, urgency) 
                           VALUES (?, ?, 'house-to-house', '', 'normal')";
  
  foreach ($services as $service_id) {
    $service_id = (int)$service_id;
    $insert_stmt = mysqli_prepare($conn, $service_insert_query);
    mysqli_stmt_bind_param($insert_stmt, 'ii', $request_id, $service_id);
    if (!mysqli_stmt_execute($insert_stmt)) {
      throw new Exception('Failed to add service');
    }
    mysqli_stmt_close($insert_stmt);
  }

  // Commit transaction
  mysqli_commit($conn);

  header('Location: customer-request-detail.php?id=' . $request_id . '&success=' . urlencode('Request updated successfully! Your technician will be notified of the changes.'));
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  header('Location: customer-request-edit.php?id=' . $request_id . '&error=' . urlencode('An error occurred: ' . $e->getMessage()));
  exit;
}
?>