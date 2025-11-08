<?php
require_once 'db.php';
require_once 'admin-header.inc.php';

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id <= 0) {
  header('Location: admin-dashboard.php');
  exit;
}

// Fetch request details with customer and technician info
$query = "SELECT 
    sr.*,
    c.customer_id,
    c.name AS customer_name,
    c.email AS customer_email,
    c.phone_number AS customer_phone,
    c.address AS customer_address,
    c.created_at AS customer_since,
    t.name AS technician_name,
    t.phone_number AS technician_phone,
    t.email AS technician_email
  FROM service_request sr
  INNER JOIN customer c ON sr.customer_id = c.customer_id
  LEFT JOIN technician t ON sr.technician_id = t.technician_id
  WHERE sr.request_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);

if (!$request) {
  header('Location: admin-dashboard.php');
  exit;
}

// Fetch service lines with calculated total
$services_query = "SELECT 
    sl.*,
    s.service_name,
    s.description AS service_description,
    s.base_price
  FROM service_line sl
  INNER JOIN services s ON sl.service_id = s.service_id
  WHERE sl.request_id = ?";

$stmt2 = mysqli_prepare($conn, $services_query);
mysqli_stmt_bind_param($stmt2, "i", $request_id);
mysqli_stmt_execute($stmt2);
$services_result = mysqli_stmt_get_result($stmt2);
$services = [];
$calculated_total = 0;
while ($row = mysqli_fetch_assoc($services_result)) {
  $services[] = $row;
  $calculated_total += (float)$row['base_price'];
}

// Fetch payment info
$payment_query = "SELECT * FROM payment WHERE request_id = ?";
$stmt3 = mysqli_prepare($conn, $payment_query);
mysqli_stmt_bind_param($stmt3, "i", $request_id);
mysqli_stmt_execute($stmt3);
$payment_result = mysqli_stmt_get_result($stmt3);
$payment = mysqli_fetch_assoc($payment_result);

// If payment exists but amount is 0 or doesn't match, update it
if ($payment && ((float)$payment['amount'] == 0 || (float)$payment['amount'] != $calculated_total)) {
    $update_payment = "UPDATE payment SET amount = ? WHERE payment_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_payment);
    mysqli_stmt_bind_param($update_stmt, 'di', $calculated_total, $payment['payment_id']);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    // Refresh payment data
    $payment['amount'] = $calculated_total;
}

// Fetch all technicians for reassignment
$techs_query = "SELECT technician_id, name FROM technician ORDER BY name ASC";
$techs_result = mysqli_query($conn, $techs_query);
$technicians = [];
while ($tech = mysqli_fetch_assoc($techs_result)) {
  $technicians[] = $tech;
}

// Count total requests by this customer
$customer_requests_query = "SELECT COUNT(*) as total FROM service_request WHERE customer_id = ?";
$stmt4 = mysqli_prepare($conn, $customer_requests_query);
mysqli_stmt_bind_param($stmt4, "i", $request['customer_id']);
mysqli_stmt_execute($stmt4);
$customer_requests_result = mysqli_stmt_get_result($stmt4);
$customer_requests_row = mysqli_fetch_assoc($customer_requests_result);
$total_requests = $customer_requests_row['total'] ?? 0;

// Fetch job tracking history
$tracking_query = "SELECT * FROM job_tracking WHERE request_id = ? ORDER BY updated_at DESC";
$stmt5 = mysqli_prepare($conn, $tracking_query);
mysqli_stmt_bind_param($stmt5, "i", $request_id);
mysqli_stmt_execute($stmt5);
$tracking_result = mysqli_stmt_get_result($stmt5);
$tracking_history = [];
while ($track = mysqli_fetch_assoc($tracking_result)) {
  $tracking_history[] = $track;
}

// Get success/error messages
$success_msg = isset($_GET['success']) ? $_GET['success'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>

<?php include 'head.php'; ?>
<link rel="stylesheet" href="styles.css">
<style>
  .detail-section {
    background: white;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
  }
  .detail-section h3 {
    margin: 0 0 16px;
    font-size: 18px;
    border-bottom: 2px solid var(--primary);
    padding-bottom: 8px;
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
    color: var(--muted);
    text-transform: uppercase;
    margin-bottom: 4px;
  }
  .detail-value {
    font-size: 14px;
    color: var(--ink);
  }
  .badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: capitalize;
  }
  .badge-pending {
    background: #fef3c7;
    color: #92400e;
  }
  .badge-scheduled {
    background: #dbeafe;
    color: #1e40af;
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
  .badge-paid {
    background: #d1fae5;
    color: #065f46;
  }
  .badge-unpaid, .badge-pending-payment {
    background: #fee2e2;
    color: #991b1b;
  }
  .badge-queued {
    background: #e0e7ff;
    color: #3730a3;
  }
  .badge-dispatched {
    background: #dbeafe;
    color: #1e40af;
  }
  .badge-on_the_way {
    background: #fef3c7;
    color: #92400e;
  }
  .badge-working {
    background: #d1fae5;
    color: #065f46;
  }
  .badge-waiting_parts {
    background: #ffedd5;
    color: #9a3412;
  }
  .service-item {
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 8px;
    border-left: 3px solid var(--primary);
  }
  .service-item-header {
    font-weight: 600;
    margin-bottom: 4px;
  }
  .customer-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 600;
    margin-right: 16px;
  }
  .customer-header {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
  }
  .customer-info {
    flex: 1;
  }
  .customer-name {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 4px;
  }
  .customer-meta {
    font-size: 13px;
    color: var(--muted);
  }
  .info-box {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
    padding: 12px;
    margin-bottom: 12px;
    border-radius: 4px;
    font-size: 13px;
  }
  .tracking-item {
    padding: 10px;
    background: #f8fafc;
    border-radius: 6px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
    font-size: 14px;
  }
  .alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
  }
  .alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
  }
  .payment-summary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 16px;
    border-radius: 8px;
    margin-top: 12px;
  }
  .payment-breakdown {
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 12px;
    border-left: 3px solid #10b981;
  }
  @media (max-width: 768px) {
    .main-grid {
      grid-template-columns: 1fr !important;
    }
  }
</style>
<?php include 'navbar.php'; ?>

<section class="section">
  <div class="container">

    <div class="card" style="padding:16px;margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
        <div>
          <h2 style="margin:0 0 4px;">Request #<?= $request_id ?></h2>
          <div class="small muted">Service Request Details</div>
        </div>
        <a href="admin-requests.php" class="btn btn-ghost">&larr; Back to Requests</a>
      </div>
    </div>

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

    <div class="info-box">
      ℹ️ <strong>Admin Note:</strong> You can reassign technicians but cannot change request status. Status updates are managed by the assigned technician.
    </div>

    <div class="main-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">
      
      <!-- Left Column: Request & Services -->
      <div>
        <!-- Request Details -->
        <div class="detail-section">
          <h3>Request Information</h3>
          
          <div class="detail-grid">
            <div class="detail-item">
              <div class="detail-label">Status (Read-Only)</div>
              <div class="detail-value">
                <span class="badge badge-<?= strtolower(str_replace(' ', '_', $request['status'])) ?>">
                  <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                </span>
                <div class="small muted" style="margin-top:4px;">
                  ⚠️ Updated by technician only
                </div>
              </div>
            </div>
            
            <div class="detail-item">
              <div class="detail-label">Appliance Type</div>
              <div class="detail-value"><?= htmlspecialchars($request['appliance_type']) ?></div>
            </div>
            
            <div class="detail-item">
              <div class="detail-label">Service Type</div>
              <div class="detail-value">
                <?php
                $service_type = !empty($services) ? $services[0]['service_type'] : 'N/A';
                echo $service_type === 'house-to-house' ? 'House-to-House' : ($service_type === 'in-shop' ? 'In-Shop' : 'N/A');
                ?>
              </div>
            </div>
            
            <div class="detail-item">
              <div class="detail-label">Scheduled Date</div>
              <div class="detail-value">
                <?= $request['scheduled_date'] ? date('M d, Y h:i A', strtotime($request['scheduled_date'])) : 'Not scheduled' ?>
              </div>
            </div>
            
            <div class="detail-item">
              <div class="detail-label">Created</div>
              <div class="detail-value"><?= date('M d, Y h:i A', strtotime($request['created_at'])) ?></div>
            </div>
          </div>

          <!-- Technician Reassignment -->
          <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line);">
            <div class="detail-label">Assigned Technician</div>
            <?php if ($request['technician_name']): ?>
              <div class="detail-value" style="margin-top:8px;margin-bottom:12px;">
                <strong><?= htmlspecialchars($request['technician_name']) ?></strong>
                <?php if ($request['technician_phone']): ?>
                  <br><small>📞 <?= htmlspecialchars($request['technician_phone']) ?></small>
                <?php endif; ?>
                <?php if ($request['technician_email']): ?>
                  <br><small>📧 <?= htmlspecialchars($request['technician_email']) ?></small>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div style="padding:12px;background:#fef3c7;border-radius:6px;margin-top:8px;margin-bottom:12px;">
                ⚠️ <strong>No technician assigned yet</strong>
              </div>
            <?php endif; ?>
            
            <form action="admin-requests-update.php" method="post">
              <input type="hidden" name="request_id" value="<?= $request_id ?>">
              <label class="small muted" style="display:block;margin-bottom:6px;">Reassign Technician</label>
              <div style="display:flex;gap:8px;align-items:end;">
                <div style="flex:1;">
                  <select class="select" name="technician_id">
                    <option value="">-- Unassign --</option>
                    <?php foreach ($technicians as $tech): ?>
                      <option value="<?= (int)$tech['technician_id'] ?>" 
                              <?= (int)$request['technician_id'] === (int)$tech['technician_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tech['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Assignment</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Services -->
        <div class="detail-section">
          <h3>Services Requested</h3>
          
          <?php if (empty($services)): ?>
            <p class="small muted">No services listed</p>
          <?php else: ?>
            <?php foreach ($services as $service): ?>
              <div class="service-item">
                <div class="service-item-header"><?= htmlspecialchars($service['service_name']) ?></div>
                <?php if (!empty($service['service_description'])): ?>
                  <div class="small muted"><?= htmlspecialchars($service['service_description']) ?></div>
                <?php endif; ?>
                <?php if (!empty($service['problem_description'])): ?>
                  <div class="small" style="margin-top:6px;">
                    <strong>Problem:</strong> <?= htmlspecialchars($service['problem_description']) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($service['urgency'])): ?>
                  <div class="small" style="margin-top:6px;">
                    <strong>Urgency:</strong> 
                    <span class="badge badge-<?= strtolower($service['urgency']) ?>"><?= htmlspecialchars($service['urgency']) ?></span>
                  </div>
                <?php endif; ?>
                <div style="margin-top:6px;color:var(--primary);font-weight:600;">
                  Base Price: ₱<?= number_format((float)$service['base_price'], 2) ?>
                </div>
              </div>
            <?php endforeach; ?>
            
            <!-- Payment Breakdown -->
            <div class="payment-breakdown">
              <div style="font-weight:600;margin-bottom:8px;">💰 Payment Breakdown</div>
              <?php foreach ($services as $service): ?>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px;">
                  <span><?= htmlspecialchars($service['service_name']) ?></span>
                  <span>₱<?= number_format((float)$service['base_price'], 2) ?></span>
                </div>
              <?php endforeach; ?>
              <div style="border-top:2px solid #10b981;margin-top:8px;padding-top:8px;display:flex;justify-content:space-between;font-weight:700;font-size:16px;color:#10b981;">
                <span>TOTAL:</span>
                <span>₱<?= number_format($calculated_total, 2) ?></span>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Job Tracking History -->
        <?php if (!empty($tracking_history)): ?>
          <div class="detail-section">
            <h3>Job Tracking History</h3>
            <p class="small muted" style="margin-bottom:12px;">Status updates logged by the technician</p>
            <?php foreach ($tracking_history as $track): ?>
              <div class="tracking-item">
                <div>
                  <span class="badge badge-<?= strtolower($track['status']) ?>">
                    <?= ucfirst(str_replace('_', ' ', $track['status'])) ?>
                  </span>
                </div>
                <div class="small muted">
                  <?= date('M d, Y h:i A', strtotime($track['updated_at'])) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Payment Information -->
        <?php if ($payment): ?>
          <div class="detail-section">
            <h3>Payment Information</h3>
            
            <div class="detail-grid">
              <div class="detail-item">
                <div class="detail-label">Amount (Calculated from Services)</div>
                <div class="detail-value" style="font-size:24px;font-weight:700;color:#10b981;">
                  ₱<?= number_format($calculated_total, 2) ?>
                </div>
                <div class="small muted" style="margin-top:4px;">
                  Auto-calculated from selected services
                </div>
              </div>
              
              <div class="detail-item">
                <div class="detail-label">Payment Status</div>
                <div class="detail-value">
                  <span class="badge badge-<?= strtolower(str_replace(' ', '_', $payment['payment_status'])) ?>">
                    <?= ucfirst(str_replace('_', ' ', $payment['payment_status'])) ?>
                  </span>
                </div>
              </div>
              
              <div class="detail-item">
                <div class="detail-label">Payment Method</div>
                <div class="detail-value"><?= htmlspecialchars($payment['payment_method'] ?? 'Not specified') ?></div>
              </div>
              
              <div class="detail-item">
                <div class="detail-label">Payment Date</div>
                <div class="detail-value">
                  <?= !empty($payment['payment_date']) ? date('M d, Y h:i A', strtotime($payment['payment_date'])) : 'Not paid' ?>
                </div>
              </div>
            </div>
            
            <?php if (!empty($payment['payment_report'])): ?>
              <div style="margin-top:12px;">
                <div class="detail-label">Payment Notes</div>
                <div class="detail-value"><?= htmlspecialchars($payment['payment_report']) ?></div>
              </div>
            <?php endif; ?>
            
            <div style="margin-top:16px;">
              <a href="admin-payment-detail.php?id=<?= $payment['payment_id'] ?>" class="btn btn-primary">
                View Full Payment Details →
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Right Column: Customer Details -->
      <div>
        <div class="detail-section">
          <h3>Customer Details</h3>
          
          <div class="customer-header">
            <div class="customer-avatar">
              <?= strtoupper(substr($request['customer_name'], 0, 1)) ?>
            </div>
            <div class="customer-info">
              <h4 class="customer-name"><?= htmlspecialchars($request['customer_name']) ?></h4>
              <div class="customer-meta">
                Customer since <?= date('M Y', strtotime($request['customer_since'])) ?>
              </div>
            </div>
          </div>

          <div class="detail-item" style="margin-bottom:12px;">
            <div class="detail-label">Email</div>
            <div class="detail-value">
              <?php if (!empty($request['customer_email'])): ?>
                <a href="mailto:<?= htmlspecialchars($request['customer_email']) ?>" style="color:var(--primary);">
                  <?= htmlspecialchars($request['customer_email']) ?>
                </a>
              <?php else: ?>
                N/A
              <?php endif; ?>
            </div>
          </div>

          <div class="detail-item" style="margin-bottom:12px;">
            <div class="detail-label">Phone Number</div>
            <div class="detail-value">
              <?php if (!empty($request['customer_phone'])): ?>
                <a href="tel:<?= htmlspecialchars($request['customer_phone']) ?>" style="color:var(--primary);">
                  <?= htmlspecialchars($request['customer_phone']) ?>
                </a>
              <?php else: ?>
                N/A
              <?php endif; ?>
            </div>
          </div>

          <div class="detail-item" style="margin-bottom:12px;">
            <div class="detail-label">Address</div>
            <div class="detail-value"><?= htmlspecialchars($request['customer_address'] ?? 'N/A') ?></div>
          </div>

          <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line);">
            <div class="detail-item">
              <div class="detail-label">Total Requests by Customer</div>
              <div class="detail-value" style="font-size:24px;font-weight:600;color:var(--primary);">
                <?= $total_requests ?>
              </div>
              <div class="small muted" style="margin-top:4px;">
                <a href="admin-requests.php" style="color:var(--primary);">View all customer requests →</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="detail-section">
          <h3>Quick Actions</h3>
          
          <div class="btn-row" style="flex-direction:column;gap:8px;">
            <?php if ($payment): ?>
              <a href="admin-payment-detail.php?id=<?= $payment['payment_id'] ?>" class="btn btn-primary" style="width:100%;">
                💵 View Payment Details
              </a>
            <?php endif; ?>
            <a href="admin-requests.php" class="btn btn-ghost" style="width:100%;">
              📋 Back to All Requests
            </a>
            <a href="admin-dashboard.php" class="btn btn-ghost" style="width:100%;">
              🏠 Back to Dashboard
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<?php include 'footer.php'; ?>