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

// Fetch request details
$query = "SELECT sr.*, c.name, c.email
  FROM service_request sr
  INNER JOIN customer c ON sr.customer_id = c.customer_id
  WHERE sr.request_id = ? AND sr.customer_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $request_id, $cid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$request) {
  header('Location: customer-dashboard.php');
  exit;
}

// Fetch services
$services_query = "SELECT sl.*, s.service_name, s.base_price
  FROM service_line sl
  INNER JOIN services s ON sl.service_id = s.service_id
  WHERE sl.request_id = ?";

$stmt2 = mysqli_prepare($conn, $services_query);
mysqli_stmt_bind_param($stmt2, 'i', $request_id);
mysqli_stmt_execute($stmt2);
$services_result = mysqli_stmt_get_result($stmt2);
$services = [];
$total = 0;
while ($row = mysqli_fetch_assoc($services_result)) {
  $services[] = $row;
  $total += (float)$row['base_price'];
}
mysqli_stmt_close($stmt2);

// Fetch payment
$payment_query = "SELECT * FROM payment WHERE request_id = ?";
$stmt3 = mysqli_prepare($conn, $payment_query);
mysqli_stmt_bind_param($stmt3, 'i', $request_id);
mysqli_stmt_execute($stmt3);
$payment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3));
mysqli_stmt_close($stmt3);

// Get technician info if assigned
$tech_info = '';
if ($request['technician_id']) {
  $tech_query = "SELECT name, phone_number FROM technician WHERE technician_id = ?";
  $stmt4 = mysqli_prepare($conn, $tech_query);
  mysqli_stmt_bind_param($stmt4, 'i', $request['technician_id']);
  mysqli_stmt_execute($stmt4);
  $tech_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt4));
  mysqli_stmt_close($stmt4);
  if ($tech_result) {
    $tech_info = $tech_result['name'] . ($tech_result['phone_number'] ? ' • ' . $tech_result['phone_number'] : '');
  }
}

$success_msg = isset($_GET['success']) ? $_GET['success'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';

// Check if technician has updated the request status
$tracking_query = "SELECT COUNT(*) as update_count FROM job_tracking 
                   WHERE request_id = ? AND status != 'queued'";
$tracking_stmt = mysqli_prepare($conn, $tracking_query);
mysqli_stmt_bind_param($tracking_stmt, 'i', $request_id);
mysqli_stmt_execute($tracking_stmt);
$tracking_result = mysqli_fetch_assoc(mysqli_stmt_get_result($tracking_stmt));
mysqli_stmt_close($tracking_stmt);

$tech_has_updated = (int)$tracking_result['update_count'] > 0;

// Check if request can be cancelled (only pending or scheduled status)
$can_cancel = in_array($request['status'], ['pending', 'scheduled']);
// Check if request can be edited (only if technician hasn't updated it)
$can_edit = !$tech_has_updated;
?>

<?php include 'head.php'; ?>
<link rel="stylesheet" href="styles.css">
<style>
  .detail-container {
    max-width: 900px;
    margin: 0 auto;
  }
  
  .detail-header {
    background: #123249;
    color: white;
    padding: 24px;
    margin-bottom: 20px;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: start;
    gap: 16px;
    flex-wrap: wrap;
  }
  
  .detail-header-content h2 {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 700;
  }
  
  .detail-header-content p {
    margin: 0;
    color: rgba(255,255,255,0.8);
    font-size: 14px;
  }
  
  .detail-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  
  .btn-detail {
    padding: 10px 16px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
  }
  
  .btn-edit {
    background: #447794;
    color: white;
  }
  
  .btn-edit:hover {
    background: #2D5B75;
  }
  
  .btn-cancel {
    background: #dc2626;
    color: white;
  }
  
  .btn-cancel:hover {
    background: #b91c1c;
  }
  
  .btn-back {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
  }
  
  .btn-back:hover {
    background: rgba(255,255,255,0.3);
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
  
  .detail-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
  }
  
  .detail-card-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #ddd;
    color: #123249;
  }
  
  .detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 12px;
  }
  
  .detail-item {
    display: flex;
    flex-direction: column;
  }
  
  .detail-label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 6px;
  }
  
  .detail-value {
    font-size: 15px;
    color: #333;
    font-weight: 500;
  }
  
  .badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: capitalize;
    width: fit-content;
  }
  
  .badge-pending {
    background: #fef3c7;
    color: #92400e;
  }
  
  .badge-scheduled {
    background: #dbeafe;
    color: #123249;
  }
  
  .badge-in_progress {
    background: #d1fae5;
    color: #065f46;
  }
  
  .badge-completed {
    background: #d1fae5;
    color: #065f46;
  }
  
  .badge-cancelled {
    background: #fee2e2;
    color: #991b1b;
  }
  
  .service-item {
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 8px;
    border-left: 3px solid #447794;
    display: flex;
    justify-content: space-between;
  }
  
  .service-name {
    font-weight: 600;
    color: #333;
  }
  
  .service-price {
    color: #447794;
    font-weight: 600;
  }
  
  .info-box {
    background: #f0f4f8;
    padding: 12px;
    border-radius: 6px;
    margin-top: 8px;
    border-left: 3px solid #447794;
    font-size: 14px;
  }
  
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
  }
  
  .modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 24px;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }
  
  .modal-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 16px;
    color: #123249;
  }
  
  .modal-description {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.6;
  }
  
  .modal-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
  }
  
  .modal-btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .modal-btn-confirm {
    background: #dc2626;
    color: white;
  }
  
  .modal-btn-confirm:hover {
    background: #b91c1c;
  }
  
  .modal-btn-cancel {
    background: #e5e7eb;
    color: #333;
  }
  
  .modal-btn-cancel:hover {
    background: #d1d5db;
  }
</style>

<section class="section">
  <div class="container detail-container">
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

    <div class="detail-header">
      <div class="detail-header-content">
        <h2>Request #<?= $request_id ?></h2>
        <p>Created <?= date('M d, Y g:i A', strtotime($request['created_at'])) ?></p>
      </div>
      <div class="detail-actions">
        <a href="customer-dashboard.php" class="btn-detail btn-back">← Back to Dashboard</a>
        <?php if ($can_edit): ?>
          <a href="customer-request-edit.php?id=<?= $request_id ?>" class="btn-detail btn-edit">✎ Edit Request</a>
        <?php endif; ?>
        <?php if ($can_cancel): ?>
          <button onclick="showCancelModal()" class="btn-detail btn-cancel">✕ Cancel Request</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Request Status -->
    <div class="detail-card">
      <div class="detail-card-title">Request Status</div>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Status</div>
          <div class="detail-value">
            <span class="badge badge-<?= strtolower($request['status']) ?>">
              <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
            </span>
          </div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Appliance Type</div>
          <div class="detail-value"><?= htmlspecialchars($request['appliance_type'] ?: 'Not specified') ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Scheduled Date</div>
          <div class="detail-value">
            <?= $request['scheduled_date'] ? date('M d, Y g:i A', strtotime($request['scheduled_date'])) : 'Not scheduled' ?>
          </div>
        </div>
        <?php if ($tech_info): ?>
          <div class="detail-item">
            <div class="detail-label">Assigned Technician</div>
            <div class="detail-value"><?= htmlspecialchars($tech_info) ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Services -->
    <div class="detail-card">
      <div class="detail-card-title">Services Requested</div>
      <?php if (!empty($services)): ?>
        <?php foreach ($services as $service): ?>
          <div class="service-item">
            <span class="service-name"><?= htmlspecialchars($service['service_name']) ?></span>
            <span class="service-price">₱<?= number_format((float)$service['base_price'], 2) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="info-box" style="margin-top: 16px;">
          <strong>Total Amount:</strong> ₱<?= number_format($total, 2) ?>
        </div>
      <?php else: ?>
        <p style="color: #666;">No services listed for this request.</p>
      <?php endif; ?>
    </div>

    <!-- Payment Info -->
    <?php if ($payment): ?>
      <div class="detail-card">
        <div class="detail-card-title">Payment Information</div>
        <div class="detail-grid">
          <div class="detail-item">
            <div class="detail-label">Amount</div>
            <div class="detail-value">₱<?= number_format((float)$payment['amount'], 2) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Status</div>
            <div class="detail-value">
              <span class="badge badge-<?= strtolower($payment['payment_status']) ?>">
                <?= ucfirst(str_replace('_', ' ', $payment['payment_status'])) ?>
              </span>
            </div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Payment Method</div>
            <div class="detail-value"><?= htmlspecialchars($payment['payment_method'] ?? 'Not specified') ?></div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Cancel Modal -->
<div id="cancelModal" class="modal">
  <div class="modal-content">
    <div class="modal-title">Cancel Request?</div>
    <div class="modal-description">
      Are you sure you want to cancel this request? This action cannot be undone. If you've already made a payment, please contact support.
    </div>
    <div class="modal-actions">
      <button onclick="hideCancelModal()" class="modal-btn modal-btn-cancel">No, Keep It</button>
      <form method="post" action="customer-request-cancel.php" style="display:inline;">
        <input type="hidden" name="request_id" value="<?= $request_id ?>">
        <button type="submit" class="modal-btn modal-btn-confirm">Yes, Cancel Request</button>
      </form>
    </div>
  </div>
</div>

<script>
function showCancelModal() {
  document.getElementById('cancelModal').style.display = 'block';
}

function hideCancelModal() {
  document.getElementById('cancelModal').style.display = 'none';
}

window.onclick = function(event) {
  const modal = document.getElementById('cancelModal');
  if (event.target === modal) {
    modal.style.display = 'none';
  }
}
</script>

<?php include 'footer.php'; ?>