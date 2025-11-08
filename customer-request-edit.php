<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';
if (empty($_SESSION['customer_id'])) { header('Location: login.php'); exit; }

$cid = (int)$_SESSION['customer_id'];
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id <= 0) {
  header('Location: customer-dashboard.php');
  exit;
}

// Fetch request - must be pending to edit
$query = "SELECT sr.* FROM service_request sr
  INNER JOIN customer c ON sr.customer_id = c.customer_id
  WHERE sr.request_id = ? AND sr.customer_id = ? AND sr.status = 'pending'";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $request_id, $cid);
mysqli_stmt_execute($stmt);
$request = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$request) {
  header('Location: customer-dashboard.php?error=' . urlencode('Cannot edit this request. Only pending requests can be edited.'));
  exit;
}

// Fetch current services
$current_services_query = "SELECT service_id FROM service_line WHERE request_id = ?";
$stmt2 = mysqli_prepare($conn, $current_services_query);
mysqli_stmt_bind_param($stmt2, 'i', $request_id);
mysqli_stmt_execute($stmt2);
$current_services_result = mysqli_stmt_get_result($stmt2);
$current_service_ids = [];
while ($row = mysqli_fetch_assoc($current_services_result)) {
  $current_service_ids[] = (int)$row['service_id'];
}
mysqli_stmt_close($stmt2);

// Get all available services
$services_query = "SELECT service_id, service_name, base_price, category, appliance_category
  FROM services 
  WHERE category IN ('cleaning', 'repair', 'installation', 'maintenance')
  ORDER BY category, appliance_category, service_name";
$services_result = mysqli_query($conn, $services_query);
$all_services = [];
while ($row = mysqli_fetch_assoc($services_result)) {
  $all_services[] = $row;
}

$success_msg = isset($_GET['success']) ? $_GET['success'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>

<?php include 'head.php'; ?>
<link rel="stylesheet" href="styles.css">
<style>
  .edit-container {
    max-width: 800px;
    margin: 0 auto;
  }
  
  .edit-header {
    background: #123249;
    color: white;
    padding: 24px;
    margin-bottom: 20px;
    border-radius: 8px;
  }
  
  .edit-header h2 {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 700;
  }
  
  .edit-header p {
    margin: 0;
    color: rgba(255,255,255,0.8);
    font-size: 14px;
  }
  
  .alert {
    padding: 12px 16px;
    margin-bottom: 16px;
    border-radius: 6px;
    border-left: 4px solid;
  }
  
  .alert-success {
    background: #d1fae5;
    color: #065f46;
    border-color: #10b981;
  }
  
  .alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-color: #ef4444;
  }
  
  .edit-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
  }
  
  .edit-card-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #ddd;
    color: #123249;
  }
  
  .form-group {
    margin-bottom: 16px;
  }
  
  .form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
    font-size: 14px;
  }
  
  .form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
  }
  
  .form-control:focus {
    outline: none;
    border-color: #447794;
    box-shadow: 0 0 0 3px rgba(68, 119, 148, 0.1);
  }
  
  textarea.form-control {
    resize: vertical;
    min-height: 80px;
  }
  
  .services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 12px;
  }
  
  .service-checkbox {
    display: flex;
    align-items: center;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .service-checkbox:hover {
    border-color: #447794;
    background: #f8fafc;
  }
  
  .service-checkbox input[type="checkbox"] {
    margin-right: 10px;
    cursor: pointer;
  }
  
  .service-checkbox input[type="checkbox"]:checked ~ .service-label {
    color: #447794;
    font-weight: 600;
  }
  
  .service-label {
    cursor: pointer;
    flex: 1;
  }
  
  .service-price {
    color: #666;
    font-size: 12px;
    margin-top: 4px;
  }
  
  .form-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }
  
  .btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
  }
  
  .btn-primary {
    background: #447794;
    color: white;
  }
  
  .btn-primary:hover {
    background: #2D5B75;
  }
  
  .btn-secondary {
    background: #e5e7eb;
    color: #333;
  }
  
  .btn-secondary:hover {
    background: #d1d5db;
  }
  
  .info-box {
    background: #e8f0f8;
    border-left: 3px solid #447794;
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #123249;
  }
</style>

<section class="section">
  <div class="container edit-container">
    <?php if ($success_msg): ?>
      <div class="alert alert-success">
        ✓ <?= htmlspecialchars($success_msg) ?>
      </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
      <div class="alert alert-error">
        ✗ <?= htmlspecialchars($error_msg) ?>
      </div>
    <?php endif; ?>

    <div class="edit-header">
      <h2>Edit Request #<?= $request_id ?></h2>
      <p>You can edit this pending request. Changes will notify your assigned technician.</p>
    </div>

    <div class="info-box">
      ℹ️ <strong>Note:</strong> You can only edit requests with pending status. Once scheduled or in progress, contact support to make changes.
    </div>

    <form method="post" action="customer-request-update.php">
      <input type="hidden" name="request_id" value="<?= $request_id ?>">

      <!-- Appliance Type -->
      <div class="edit-card">
        <div class="edit-card-title">Appliance Details</div>
        
        <div class="form-group">
          <label class="form-label">Appliance Type</label>
          <input type="text" name="appliance_type" class="form-control" 
                 value="<?= htmlspecialchars($request['appliance_type'] ?? '') ?>" 
                 placeholder="e.g., Refrigerator, Air Conditioner" required>
        </div>
      </div>

      <!-- Services Selection -->
      <div class="edit-card">
        <div class="edit-card-title">Select Services</div>
        
        <div class="services-grid">
          <?php foreach ($all_services as $service): 
            $is_checked = in_array((int)$service['service_id'], $current_service_ids);
          ?>
            <label class="service-checkbox">
              <input type="checkbox" name="services[]" value="<?= (int)$service['service_id'] ?>" 
                     <?= $is_checked ? 'checked' : '' ?>>
              <div class="service-label">
                <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                <div class="service-price">₱<?= number_format((float)$service['base_price'], 2) ?></div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="edit-card">
        <div class="form-actions">
          <a href="customer-request-detail.php?id=<?= $request_id ?>" class="btn btn-secondary">
            Cancel
          </a>
          <button type="submit" class="btn btn-primary">
            Save Changes
          </button>
        </div>
      </div>
    </form>
  </div>
</section>

<?php include 'footer.php'; ?>