<?php
require_once 'auto-assign-technician.php';

// After inserting the service request
$request_id = mysqli_insert_id($conn);

// Auto-assign technician
$assignment = auto_assign_technician($conn, $request_id);

if ($assignment['success']) {
    $message = "Request created! Assigned to " . $assignment['technician_name'];
} else {
    $message = "Request created! Will be assigned soon.";
}
?>