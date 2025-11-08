<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';

if (empty($_SESSION['customer_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: login.php');
  exit;
}

$cid = (int)$_SESSION['customer_id'];
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

if ($request_id <= 0) {
  header('Location: customer-dashboard.php?error=' . urlencode('Invalid request.'));
  exit;
}

// Verify request exists and belongs to customer and can be cancelled
$verify_query = "SELECT sr.request_id, sr.status, p.payment_status
                 FROM service_request sr
                 LEFT JOIN payment p ON sr.request_id = p.request_id
                 WHERE sr.request_id = ? AND sr.customer_id = ? 
                 AND sr.status IN ('pending', 'scheduled')";

$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, 'ii', $request_id, $cid);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_fetch_assoc(mysqli_stmt_get_result($verify_stmt));
mysqli_stmt_close($verify_stmt);

if (!$verify_result) {
  header('Location: customer-dashboard.php?error=' . urlencode('This request cannot be cancelled. Only pending and scheduled requests can be cancelled.'));
  exit;
}

// Check if payment has been made
if ($verify_result['payment_status'] === 'paid') {
  header('Location: customer-request-detail.php?id=' . $request_id . '&error=' . urlencode('Cannot cancel a request with paid payment. Please contact support.'));
  exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
  // Update request status to cancelled
  $cancel_query = "UPDATE service_request SET status = 'cancelled', updated_at = NOW() WHERE request_id = ?";
  $cancel_stmt = mysqli_prepare($conn, $cancel_query);
  mysqli_stmt_bind_param($cancel_stmt, 'i', $request_id);
  if (!mysqli_stmt_execute($cancel_stmt)) {
    throw new Exception('Failed to cancel request');
  }
  mysqli_stmt_close($cancel_stmt);

  // Log the cancellation in job_tracking
  $tracking_query = "INSERT INTO job_tracking (request_id, status, updated_at) VALUES (?, 'cancelled', NOW())";
  $tracking_stmt = mysqli_prepare($conn, $tracking_query);
  mysqli_stmt_bind_param($tracking_stmt, 'i', $request_id);
  mysqli_stmt_execute($tracking_stmt);
  mysqli_stmt_close($tracking_stmt);

  // Commit transaction
  mysqli_commit($conn);

  header('Location: customer-dashboard.php?success=' . urlencode('Request #' . $request_id . ' has been cancelled successfully.'));
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  header('Location: customer-request-detail.php?id=' . $request_id . '&error=' . urlencode('An error occurred while cancelling the request.'));
  exit;
}
?>