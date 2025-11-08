<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';
if (empty($_SESSION['customer_id'])) { header('Location: login.php'); exit; }

function back($m){ header('Location: book.php?error='.urlencode($m)); exit; }
function clean($s){ return trim(filter_var((string)$s, FILTER_SANITIZE_FULL_SPECIAL_CHARS)); }

// Read posted fields
$service_type_raw     = $_POST['service_type'] ?? '';
$selected_categories  = $_POST['service_categories'] ?? [];
$selected_service_ids = $_POST['service_ids'] ?? [];
$phone                = clean($_POST['phone'] ?? '');
$address              = clean($_POST['address'] ?? '');
$appliance_type       = clean($_POST['appliance_type'] ?? '');
$urgency              = clean($_POST['urgency'] ?? 'Normal');
$problem_description  = clean($_POST['problem'] ?? '');
$preferred_date_input = clean($_POST['pref_date'] ?? '');
$time_slot_input      = clean($_POST['time_slot'] ?? '');

// Map service type
$st = strtolower($service_type_raw);
if (in_array($st, ['house-to-house','house to house','house_to_house'], true)) {
  $service_type = 'house-to-house';
} elseif (in_array($st, ['in-shop','in shop','in_shop','shop'], true)) {
  $service_type = 'in-shop';
} else {
  $service_type = '';
}

// Validation
if ($service_type === '') back('Please select a service type.');
if (empty($selected_categories)) back('Please select at least one service category.');
if (empty($selected_service_ids)) back('Please select at least one specific service.');
if ($phone === '') back('Please enter your phone number.');
if ($service_type === 'house-to-house' && $address === '') back('Address is required for House-to-House service.');
if ($appliance_type === '') back('Please select an appliance type.');
if ($problem_description === '') back('Please describe the problem.');
if ($preferred_date_input === '') back('Please choose a preferred date.');
if ($time_slot_input === '') back('Please choose a preferred time slot.');

// Validate and fetch selected services with their prices
$service_ids_int = array_map('intval', $selected_service_ids);
$service_ids_int = array_filter($service_ids_int, function($id) { return $id > 0; });

if (empty($service_ids_int)) back('Invalid service selection.');

// Create placeholders for SQL IN clause
$placeholders = str_repeat('?,', count($service_ids_int) - 1) . '?';

// Fetch services and validate they belong to selected categories
$validate_query = "SELECT service_id, service_name, base_price, category 
                   FROM services 
                   WHERE service_id IN ($placeholders)";
$validate_stmt = mysqli_prepare($conn, $validate_query);

if (!$validate_stmt) back('Database error: Unable to validate services.');

// Bind parameters dynamically
$types = str_repeat('i', count($service_ids_int));
mysqli_stmt_bind_param($validate_stmt, $types, ...$service_ids_int);
mysqli_stmt_execute($validate_stmt);
$validate_result = mysqli_stmt_get_result($validate_stmt);

$valid_services = [];
$total_cost = 0.00;
$found_service_ids = [];

while ($service = mysqli_fetch_assoc($validate_result)) {
  // Verify service belongs to selected categories
  if (in_array($service['category'], $selected_categories)) {
    $valid_services[] = $service;
    $total_cost += (float)$service['base_price'];
    $found_service_ids[] = (int)$service['service_id'];
  }
}
mysqli_stmt_close($validate_stmt);

// Check if all selected services were valid
if (empty($valid_services)) {
  back('No valid services found. Please select services from the chosen categories.');
}

if (count($valid_services) !== count($service_ids_int)) {
  back('Some selected services are invalid or do not match the chosen categories.');
}

// Normalize date and time
$day = date('Y-m-d', strtotime($preferred_date_input)) ?: date('Y-m-d');

// Parse start time from time slot
$time_start = '09:00';
if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)?/i', $time_slot_input, $m)) {
  $hh = (int)$m[1]; 
  $mm = (int)$m[2]; 
  $ampm = isset($m[3]) ? strtoupper($m[3]) : '';
  if ($ampm === 'PM' && $hh < 12) $hh += 12;
  if ($ampm === 'AM' && $hh === 12) $hh = 0;
  $time_start = sprintf('%02d:%02d', $hh, $mm);
}
$scheduled = $day.' '.$time_start.':00';

// Update customer contact info
$update_customer = mysqli_prepare($conn, "UPDATE customer SET phone_number=?, address=IF(?<>'', ?, address) WHERE customer_id=?");
if ($update_customer) {
  mysqli_stmt_bind_param($update_customer, 'sssi', $phone, $address, $address, $_SESSION['customer_id']);
  mysqli_stmt_execute($update_customer);
  mysqli_stmt_close($update_customer);
}

// Begin transaction for data integrity
mysqli_begin_transaction($conn);

try {
  // Insert service_request with calculated cost
  $req_sql = "INSERT INTO service_request (appliance_type, status, scheduled_date, cost, customer_id)
              VALUES (?, 'pending', ?, ?, ?)";
  $req_stmt = mysqli_prepare($conn, $req_sql);
  
  if (!$req_stmt) {
    throw new Exception('Failed to prepare service request insert.');
  }
  
  mysqli_stmt_bind_param($req_stmt, 'ssdi', $appliance_type, $scheduled, $total_cost, $_SESSION['customer_id']);
  
  if (!mysqli_stmt_execute($req_stmt)) {
    throw new Exception('Failed to insert service request.');
  }
  
  $request_id = mysqli_insert_id($conn);
  mysqli_stmt_close($req_stmt);

  // Insert service_line for each valid service
  $line_sql = "INSERT INTO service_line (request_id, service_id, service_type, problem_description, urgency) 
               VALUES (?, ?, ?, ?, ?)";
  $line_stmt = mysqli_prepare($conn, $line_sql);
  
  if (!$line_stmt) {
    throw new Exception('Failed to prepare service line insert.');
  }

  foreach ($found_service_ids as $service_id) {
    mysqli_stmt_bind_param($line_stmt, 'iisss', $request_id, $service_id, $service_type, $problem_description, $urgency);
    
    if (!mysqli_stmt_execute($line_stmt)) {
      throw new Exception('Failed to insert service line.');
    }
  }
  mysqli_stmt_close($line_stmt);

  // Create initial job tracking
  $track_sql = "INSERT INTO job_tracking (request_id, status) VALUES (?, 'queued')";
  $track_stmt = mysqli_prepare($conn, $track_sql);
  
  if ($track_stmt) {
    mysqli_stmt_bind_param($track_stmt, 'i', $request_id);
    mysqli_stmt_execute($track_stmt);
    mysqli_stmt_close($track_stmt);
  }

  // Create payment record with calculated total
  $payment_sql = "INSERT INTO payment (amount, payment_method, payment_status, request_id) 
                  VALUES (?, 'cash', 'unpaid', ?)";
  $payment_stmt = mysqli_prepare($conn, $payment_sql);
  
  if (!$payment_stmt) {
    throw new Exception('Failed to prepare payment insert.');
  }
  
  mysqli_stmt_bind_param($payment_stmt, 'di', $total_cost, $request_id);
  
  if (!mysqli_stmt_execute($payment_stmt)) {
    throw new Exception('Failed to insert payment record.');
  }
  mysqli_stmt_close($payment_stmt);

  // Commit transaction
  mysqli_commit($conn);

  // Success - redirect with details
  $success_message = 'Service request submitted successfully! Total cost: ₱' . number_format($total_cost, 2);
  header('Location: customer-dashboard.php?success=' . urlencode($success_message) . '&rid=' . $request_id);
  exit;

} catch (Exception $e) {
  // Rollback on error
  mysqli_rollback($conn);
  back('Failed to create service request: ' . $e->getMessage());
}