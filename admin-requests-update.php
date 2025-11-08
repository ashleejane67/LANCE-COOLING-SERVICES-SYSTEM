<?php
require_once 'db.php';
require_once 'admin-header.inc.php';

$rid = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$tid = isset($_POST['technician_id']) && $_POST['technician_id']!=='' ? (int)$_POST['technician_id'] : null;

if ($rid <= 0) { 
  header('Location: admin-requests.php?error=' . urlencode('Invalid request ID.')); 
  exit; 
}

// Get current assignment to check if it's changing
$check_query = "SELECT technician_id FROM service_request WHERE request_id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, 'i', $rid);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$current = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

$is_new_assignment = ($current['technician_id'] != $tid);

// Update assignment with timestamp
if ($tid !== null) {
  if ($is_new_assignment) {
    // New assignment or reassignment - update assigned_at timestamp
    $admin_id = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : 1;
    $u = mysqli_prepare($conn, "UPDATE service_request SET technician_id=?, assigned_at=NOW(), assigned_by=? WHERE request_id=?");
    mysqli_stmt_bind_param($u, 'iii', $tid, $admin_id, $rid);
  } else {
    // Same technician, don't change assigned_at
    $u = mysqli_prepare($conn, "UPDATE service_request SET technician_id=? WHERE request_id=?");
    mysqli_stmt_bind_param($u, 'ii', $tid, $rid);
  }
} else {
  // Unassigning technician - clear assigned_at
  $u = mysqli_prepare($conn, "UPDATE service_request SET technician_id=NULL, assigned_at=NULL, assigned_by=NULL WHERE request_id=?");
  mysqli_stmt_bind_param($u, 'i', $rid);
}

$success = mysqli_stmt_execute($u);
mysqli_stmt_close($u);

if ($success) {
  // Only log to job_tracking if there was an actual assignment change
  if ($is_new_assignment && $tid !== null) {
    $t = mysqli_prepare($conn, "INSERT INTO job_tracking (request_id, status, updated_at) VALUES (?, 'pending', NOW())");
    if ($t) {
      mysqli_stmt_bind_param($t, 'i', $rid);
      mysqli_stmt_execute($t);
      mysqli_stmt_close($t);
    }
    
    // Create notification for technician
    $notif_query = "INSERT INTO notifications (user_type, user_id, title, message, request_id, created_at) 
                    VALUES ('technician', ?, 'New Job Assignment', ?, ?, NOW())";
    $notif_stmt = mysqli_prepare($conn, $notif_query);
    $notif_msg = "You have been assigned to service request #$rid";
    mysqli_stmt_bind_param($notif_stmt, 'isi', $tid, $notif_msg, $rid);
    mysqli_stmt_execute($notif_stmt);
    mysqli_stmt_close($notif_stmt);
  }
  
  header('Location: admin-requests.php?success=' . urlencode('Technician assignment updated successfully.'));
} else {
  header('Location: admin-requests.php?error=' . urlencode('Failed to update assignment.'));
}
exit;