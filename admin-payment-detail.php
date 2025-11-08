<?php
require_once 'db.php';
require_once 'admin-header.inc.php';

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($payment_id <= 0) {
    header('Location: admin-payments.php?error=' . urlencode('Invalid payment ID.'));
    exit;
}

// Get payment details with customer and service request info
$sql = "
SELECT p.payment_id, p.amount, p.payment_status, p.payment_method, p.payment_date, p.payment_report,
       p.sender_name, p.reference_number, p.receiver_name, p.receipt_image, p.submitted_at,
       p.approved_by, p.approved_at,
       r.request_id, r.status AS request_status, r.customer_id, r.appliance_type, r.scheduled_date,
       c.name AS customer_name, c.email AS customer_email, c.phone_number AS customer_phone, c.address AS customer_address,
       t.name AS technician_name,
       sa.name AS approved_by_name,
       (SELECT GROUP_CONCAT(s.service_name SEPARATOR ', ') 
        FROM service_line sl 
        INNER JOIN services s ON sl.service_id = s.service_id 
        WHERE sl.request_id = r.request_id) AS services_list
FROM payment p
JOIN service_request r ON r.request_id = p.request_id
JOIN customer c ON c.customer_id = r.customer_id
LEFT JOIN technician t ON t.technician_id = r.technician_id
LEFT JOIN staff_user sa ON sa.staff_id = p.approved_by
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

// Normalize payment status - handle empty/null values
$payment_status = strtolower(trim($payment['payment_status']));
if (empty($payment_status)) {
    $payment_status = 'unpaid';
}
?>
<?php include 'head.php'; ?>
<link rel="stylesheet" href="styles.css">
<style>
.detail-header {
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  color: white;
}

.status-badge {
  display: inline-block;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 600;
  text-transform: capitalize;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-unpaid {
  background: #fee2e2;
  color: #991b1b;
}

.status-paid {
  background: #d1fae5;
  color: #065f46;
}

.info-section {
  background: white;
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
}

.info-section h3 {
  margin: 0 0 16px;
  font-size: 18px;
  color: #1e293b;
  border-bottom: 2px solid var(--line);
  padding-bottom: 8px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-label {
  font-size: 12px;
  color: var(--muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-value {
  font-size: 15px;
  color: #000000;
  font-weight: 500;
}

.receipt-section {
  background: white;
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
}

.receipt-image {
  max-width: 400px;
  width: 100%;
  height: auto;
  border-radius: 8px;
  border: 1px solid var(--line);
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  cursor: pointer;
  transition: transform 0.2s;
}

.receipt-image:hover {
  transform: scale(1.02);
}

.action-buttons {
  display: flex;
  gap: 12px;
  margin-top: 24px;
  flex-wrap: wrap;
}

.btn-approve {
  background: #10b981;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 15px;
  transition: all 0.2s;
  flex: 1;
  min-width: 200px;
}

.btn-approve:hover {
  background: #059669;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-reject {
  background: #ef4444;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 15px;
  transition: all 0.2s;
  flex: 1;
  min-width: 200px;
}

.btn-reject:hover {
  background: #dc2626;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-received {
  background: #06b6d4;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 15px;
  transition: all 0.2s;
  flex: 1;
  min-width: 200px;
}

.btn-received:hover {
  background: #0891b2;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
}

.alert-warning {
  background: #fef3c7;
  border-left: 4px solid #f59e0b;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  color: #92400e;
}

.approved-info {
  background: #d1fae5;
  border-left: 4px solid #10b981;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  color: #065f46;
}

.unpaid-info {
  background: #fee2e2;
  border-left: 4px solid #ef4444;
  padding: 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  color: #991b1b;
}

@media (max-width: 768px) {
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .action-buttons {
    flex-direction: column;
  }
}
</style>
<?php include 'navbar.php'; ?>

<section class="section">
  <div class="container">
    
    <div class="detail-header">
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:12px;">
        <div>
          <h1 style="margin:0 0 8px;font-size:24px;">Payment #<?= (int)$payment['payment_id'] ?></h1>
          <p style="margin:0;opacity:0.9;font-size:14px;">Service Request #<?= (int)$payment['request_id'] ?></p>
        </div>
        <span class="status-badge status-<?= $payment_status ?>">
          <?= ucfirst($payment_status) ?>
        </span>
      </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div style="background:#d1fae5;color:#065f46;padding:14px 18px;border-radius:8px;margin-bottom:20px;border-left:4px solid #10b981;">
        ✓ <?= htmlspecialchars($_GET['success']) ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div style="background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:8px;margin-bottom:20px;border-left:4px solid #dc2626;">
        ✗ <?= htmlspecialchars($_GET['error']) ?>
      </div>
    <?php endif; ?>

    <?php if ($payment_status === 'pending'): ?>
      <div class="alert-warning">
        <div style="font-weight:600;margin-bottom:4px;">⏳ Payment Pending Approval</div>
        <div style="font-size:14px;">This payment has been submitted by the customer and is waiting for your review and approval.</div>
      </div>
    <?php elseif ($payment_status === 'unpaid' && empty($payment['submitted_at'])): ?>
      <div class="unpaid-info">
        <div style="font-weight:600;margin-bottom:4px;">❌ Payment Not Yet Submitted</div>
        <div style="font-size:14px;">Customer has not submitted payment details yet. Waiting for customer to upload receipt and payment information.</div>
      </div>
    <?php elseif ($payment_status === 'unpaid' && !empty($payment['submitted_at'])): ?>
      <div class="alert-warning">
        <div style="font-weight:600;margin-bottom:4px;">⏳ Payment Submitted - Needs Review</div>
        <div style="font-size:14px;">Customer submitted payment on <?= date('M d, Y g:i A', strtotime($payment['submitted_at'])) ?>. Please review and approve or reject.</div>
      </div>
    <?php endif; ?>

    <?php if ($payment_status === 'paid' && !empty($payment['approved_at'])): ?>
      <div class="approved-info">
        <div style="font-weight:600;margin-bottom:4px;">✓ Payment Approved</div>
        <div style="font-size:14px;">
          Approved by: <strong><?= htmlspecialchars($payment['approved_by_name'] ?: 'Admin') ?></strong> 
          on <?= date('M d, Y g:i A', strtotime($payment['approved_at'])) ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="info-section">
      <h3>💰 Payment Information</h3>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Amount</div>
          <div class="info-value" style="font-size:24px;color:#10b981;font-weight:700;">
            ₱<?= number_format((float)$payment['amount'], 2) ?>
          </div>
        </div>
        <div class="info-item">
          <div class="info-label">Payment Method</div>
          <div class="info-value"><?= $payment['payment_method'] ? htmlspecialchars($payment['payment_method']) : 'Not specified' ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Reference Number</div>
          <div class="info-value"><?= $payment['reference_number'] ? htmlspecialchars($payment['reference_number']) : 'N/A' ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Submitted On</div>
          <div class="info-value">
            <?= $payment['submitted_at'] ? date('M d, Y g:i A', strtotime($payment['submitted_at'])) : 'Not submitted' ?>
          </div>
        </div>
        <div class="info-item">
          <div class="info-label">Sender Name</div>
          <div class="info-value"><?= $payment['sender_name'] ? htmlspecialchars($payment['sender_name']) : 'N/A' ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Receiver Name</div>
          <div class="info-value"><?= $payment['receiver_name'] ? htmlspecialchars($payment['receiver_name']) : 'N/A' ?></div>
        </div>
      </div>
    </div>

    <div class="info-section">
      <h3>👤 Customer Information</h3>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Customer Name</div>
          <div class="info-value"><?= htmlspecialchars($payment['customer_name']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Email</div>
          <div class="info-value"><?= htmlspecialchars($payment['customer_email']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Phone Number</div>
          <div class="info-value"><?= htmlspecialchars($payment['customer_phone'] ?: 'N/A') ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Address</div>
          <div class="info-value"><?= htmlspecialchars($payment['customer_address'] ?: 'N/A') ?></div>
        </div>
      </div>
    </div>

    <div class="info-section">
      <h3>🔧 Service Request Details</h3>
      <div class="info-grid">
        <div class="info-item">
          <div class="info-label">Appliance Type</div>
          <div class="info-value"><?= htmlspecialchars($payment['appliance_type']) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Request Status</div>
          <div class="info-value"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($payment['request_status']))) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Scheduled Date</div>
          <div class="info-value">
            <?= $payment['scheduled_date'] ? date('M d, Y g:i A', strtotime($payment['scheduled_date'])) : 'Not scheduled' ?>
          </div>
        </div>
        <div class="info-item">
          <div class="info-label">Assigned Technician</div>
          <div class="info-value"><?= htmlspecialchars($payment['technician_name'] ?: 'Unassigned') ?></div>
        </div>
      </div>
      <?php if ($payment['services_list']): ?>
        <div style="margin-top:16px;padding:12px;background:#f8fafc;border-radius:6px;">
          <div class="info-label">Services Requested</div>
          <div class="info-value"><?= htmlspecialchars($payment['services_list']) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($payment['receipt_image'])): ?>
      <div class="receipt-section">
        <h3 style="margin:0 0 16px;">📷 Payment Receipt</h3>
        <div style="text-align:center;">
          <a href="uploads/receipts/<?= htmlspecialchars($payment['receipt_image']) ?>" target="_blank" title="Click to view full size">
            <img src="uploads/receipts/<?= htmlspecialchars($payment['receipt_image']) ?>" 
                 class="receipt-image" 
                 alt="Payment Receipt"
                 loading="lazy">
          </a>
          <p style="margin-top:12px;font-size:13px;color:var(--muted);">
            <strong>💡 Tip:</strong> Click image to view full size in new tab
          </p>
        </div>
      </div>
    <?php endif; ?>

    <!-- Action Buttons - Show for pending or submitted unpaid payments -->
    <?php if (($payment_status === 'pending') || ($payment_status === 'unpaid' && !empty($payment['submitted_at']))): ?>
      <div class="action-buttons">
        <form action="admin-payment-received.php" method="post" style="flex:1;">
          <input type="hidden" name="payment_id" value="<?= (int)$payment['payment_id'] ?>">
          <input type="hidden" name="return_url" value="admin-payment-detail.php?id=<?= (int)$payment['payment_id'] ?>">
          <button type="submit" class="btn-received" 
                  onclick="return confirm('Send payment received notification to customer?\n\nCustomer: <?= htmlspecialchars($payment['customer_name']) ?>\nAmount: ₱<?= number_format($payment['amount'], 2) ?>')">
            📧 Payment Received
          </button>
        </form>
        <form action="admin-payment-approve.php" method="post" style="flex:1;">
          <input type="hidden" name="payment_id" value="<?= (int)$payment['payment_id'] ?>">
          <input type="hidden" name="return_url" value="admin-payment-detail.php?id=<?= (int)$payment['payment_id'] ?>">
          <button type="submit" name="action" value="approve" class="btn-approve" 
                  onclick="return confirm('Are you sure you want to APPROVE this payment?\n\nThis will:\n• Mark the payment as PAID\n• Record your approval\n• Send confirmation to customer')">
            ✓ Approve Payment
          </button>
        </form>
        <form action="admin-payment-approve.php" method="post" style="flex:1;">
          <input type="hidden" name="payment_id" value="<?= (int)$payment['payment_id'] ?>">
          <input type="hidden" name="return_url" value="admin-payment-detail.php?id=<?= (int)$payment['payment_id'] ?>">
          <button type="submit" name="action" value="reject" class="btn-reject" 
                  onclick="return confirm('Are you sure you want to REJECT this payment?\n\nThis will:\n• Set payment back to UNPAID\n• Customer will need to resubmit\n• Previous receipt will be cleared')">
            ✗ Reject Payment
          </button>
        </form>
      </div>
    <?php endif; ?>
    
    <!-- Show notification button for paid payments -->
    <?php if ($payment_status === 'paid'): ?>
      <div class="action-buttons">
        <form action="admin-payment-received.php" method="post" style="flex:1;">
          <input type="hidden" name="payment_id" value="<?= (int)$payment['payment_id'] ?>">
          <input type="hidden" name="return_url" value="admin-payment-detail.php?id=<?= (int)$payment['payment_id'] ?>">
          <button type="submit" class="btn-received" 
                  onclick="return confirm('Send payment received notification to customer?\n\nCustomer: <?= htmlspecialchars($payment['customer_name']) ?>\nAmount: ₱<?= number_format($payment['amount'], 2) ?>')">
            📧 Send Payment Received Notification
          </button>
        </form>
      </div>
    <?php endif; ?>

    <div style="margin-top:24px;text-align:center;">
      <a href="admin-payments.php" class="btn btn-ghost">← Back to Payments</a>
    </div>

  </div>
</section>

<?php include 'footer.php'; ?>