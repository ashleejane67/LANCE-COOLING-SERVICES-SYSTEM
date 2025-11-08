<?php
require_once 'db.php';
require_once 'admin-header.inc.php';

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$where_conditions = ["1=1"];
$params = [];
$types = '';

if ($filter === 'pending') {
    $where_conditions[] = "p.payment_status = 'pending'";
} elseif ($filter === 'paid') {
    $where_conditions[] = "p.payment_status = 'paid'";
} elseif ($filter === 'unpaid') {
    $where_conditions[] = "p.payment_status = 'unpaid'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get payments
$sql = "
SELECT 
    p.payment_id,
    p.amount,
    p.payment_method,
    p.payment_status,
    p.payment_date,
    p.reference_number,
    p.sender_name,
    p.receiver_name,
    p.receipt_image,
    p.submitted_at,
    p.paypal_order_id,
    sr.request_id,
    sr.appliance_type,
    sr.scheduled_date,
    sr.status AS request_status,
    c.customer_id,
    c.name AS customer_name,
    c.email AS customer_email,
    c.phone_number AS customer_phone,
    (SELECT GROUP_CONCAT(s.service_name SEPARATOR ', ') 
     FROM service_line sl 
     INNER JOIN services s ON sl.service_id = s.service_id 
     WHERE sl.request_id = sr.request_id) AS services_list
FROM payment p
JOIN service_request sr ON p.request_id = sr.request_id
JOIN customer c ON sr.customer_id = c.customer_id
WHERE $where_clause
ORDER BY 
    CASE WHEN p.payment_status = 'pending' THEN 1 ELSE 2 END,
    p.submitted_at DESC,
    p.payment_id DESC
LIMIT 100";

// Get ALL payments for stats (regardless of filter)
$stats_sql = "
SELECT 
    p.payment_status,
    COUNT(*) as count,
    SUM(p.amount) as total_amount
FROM payment p
JOIN service_request sr ON p.request_id = sr.request_id
JOIN customer c ON sr.customer_id = c.customer_id
GROUP BY p.payment_status";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = [
    'total' => 0,
    'pending' => 0,
    'paid' => 0,
    'unpaid' => 0,
    'total_amount' => 0,
    'pending_amount' => 0
];

while ($stat_row = mysqli_fetch_assoc($stats_result)) {
    $status = $stat_row['payment_status'];
    $count = (int)$stat_row['count'];
    $amount = (float)$stat_row['total_amount'];
    
    $stats['total'] += $count;
    $stats['total_amount'] += $amount;
    
    if ($status === 'pending') {
        $stats['pending'] = $count;
        $stats['pending_amount'] = $amount;
    } elseif ($status === 'paid') {
        $stats['paid'] = $count;
    } elseif ($status === 'unpaid') {
        $stats['unpaid'] = $count;
    }
}

// Get filtered payments for display
$result = mysqli_query($conn, $sql);
$payments = [];

while ($row = mysqli_fetch_assoc($result)) {
    $payments[] = $row;
}
?>

<?php include 'head.php'; ?>

<section class="section">
  <div class="container">
    
    <?php if (isset($_GET['success'])): ?>
      <div class="card" style="background:#eafcf3;border:1px solid #bde7cf;padding:10px;border-radius:12px;margin-bottom:12px;">
        ✓ <?= htmlspecialchars($_GET['success']) ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="card" style="background:#fee;border:1px solid #fcc;padding:10px;border-radius:12px;margin-bottom:12px;">
        ✗ <?= htmlspecialchars($_GET['error']) ?>
      </div>
    <?php endif; ?>

    <div class="card" style="padding:16px;margin-bottom:16px;">
      <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;flex-wrap:wrap;">
        <h2 style="margin:0;">💳 Payment Management</h2>
        <div class="btn-row">
          <a class="btn btn-ghost" href="admin-dashboard.php">Back to Dashboard</a>
          <a class="btn btn-ghost" href="logout.php">Log out</a>
        </div>
      </div>
    </div>

    <!-- Statistics -->
    <div class="card" style="padding:16px;margin-bottom:16px;">
      <h3 style="margin:0 0 12px;">Statistics</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
        <div style="padding:10px;background:#f9fafb;border-radius:8px;">
          <div class="small muted">Total Payments</div>
          <div style="font-size:20px;font-weight:600;margin-top:4px;"><?= number_format($stats['total']) ?></div>
        </div>
        <div style="padding:10px;background:#fef3c7;border-radius:8px;">
          <div class="small muted">Pending</div>
          <div style="font-size:20px;font-weight:600;margin-top:4px;"><?= number_format($stats['pending']) ?></div>
        </div>
        <div style="padding:10px;background:#d1fae5;border-radius:8px;">
          <div class="small muted">Paid</div>
          <div style="font-size:20px;font-weight:600;margin-top:4px;"><?= number_format($stats['paid']) ?></div>
        </div>
        <div style="padding:10px;background:#f9fafb;border-radius:8px;">
          <div class="small muted">Total Revenue</div>
          <div style="font-size:20px;font-weight:600;margin-top:4px;">₱<?= number_format($stats['total_amount'], 2) ?></div>
        </div>
      </div>
    </div>

    <!-- Filter Tabs -->
    <div class="card" style="padding:16px;margin-bottom:16px;">
      <h3 style="margin:0 0 10px;">Filter</h3>
      <div class="btn-row">
        <a href="admin-payments.php" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-ghost' ?>">
          All (<?= $stats['total'] ?>)
        </a>
        <a href="admin-payments.php?filter=pending" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-ghost' ?>">
          Pending (<?= $stats['pending'] ?>)
        </a>
        <a href="admin-payments.php?filter=paid" class="btn <?= $filter === 'paid' ? 'btn-primary' : 'btn-ghost' ?>">
          Paid (<?= $stats['paid'] ?>)
        </a>
        <a href="admin-payments.php?filter=unpaid" class="btn <?= $filter === 'unpaid' ? 'btn-primary' : 'btn-ghost' ?>">
          Unpaid (<?= $stats['unpaid'] ?>)
        </a>
      </div>
    </div>

    <!-- Payment List -->
    <div class="card" style="padding:16px;">
      <h3 style="margin:0 0 10px;">Payments</h3>

      <?php if (empty($payments)): ?>
        <p class="muted small">No payments found.</p>
      <?php else: ?>
        <div style="display:grid;gap:10px;">
          <?php foreach ($payments as $p): ?>
            <div class="card" style="padding:12px;<?= $p['payment_status'] === 'pending' ? 'border-left:3px solid #d97706;' : '' ?>">
              
              <!-- Header -->
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px;">
                <div>
                  <div class="small muted">Payment #<?= $p['payment_id'] ?> • Request #<?= $p['request_id'] ?></div>
                  <h4 style="margin:.25rem 0;"><?= htmlspecialchars($p['appliance_type']) ?></h4>
                  <div class="small"><?= htmlspecialchars($p['customer_name']) ?></div>
                </div>
                <div style="text-align:right;">
                  <div style="font-size:18px;font-weight:600;">₱<?= number_format($p['amount'], 2) ?></div>
                  <span class="small" style="display:inline-block;padding:4px 8px;border-radius:8px;margin-top:4px;
                    <?php if ($p['payment_status'] === 'pending'): ?>background:#fcd34d;
                    <?php elseif ($p['payment_status'] === 'paid'): ?>background:#86efac;
                    <?php else: ?>background:#fca5a5;<?php endif; ?>">
                    <?= htmlspecialchars($p['payment_status']) ?>
                  </span>
                </div>
              </div>

              <?php if ($p['payment_status'] === 'pending' && $p['submitted_at']): ?>
                <!-- Pending Alert -->
                <div style="background:#fef3c7;padding:8px;border-radius:6px;margin-bottom:8px;">
                  <span style="font-size:13px;">
                    Submitted <?= date('M d, Y g:i A', strtotime($p['submitted_at'])) ?>
                  </span>
                </div>

                <!-- Payment Details -->
                <div style="background:#f9fafb;padding:8px;border-radius:6px;margin-bottom:8px;">
                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;">
                    <div>
                      <div class="small muted">Method</div>
                      <div class="small"><?= htmlspecialchars($p['payment_method']) ?></div>
                    </div>
                    <div>
                      <div class="small muted">Reference</div>
                      <div class="small"><?= htmlspecialchars($p['reference_number']) ?></div>
                    </div>
                    <div>
                      <div class="small muted">Sender</div>
                      <div class="small"><?= htmlspecialchars($p['sender_name']) ?></div>
                    </div>
                    <div>
                      <div class="small muted">Receiver</div>
                      <div class="small"><?= htmlspecialchars($p['receiver_name']) ?></div>
                    </div>
                  </div>
                </div>

                <!-- Receipt -->
                <?php if ($p['receipt_image']): ?>
                  <div style="margin-bottom:8px;">
                    <div class="small muted">Receipt</div>
                    <a href="uploads/receipts/<?= htmlspecialchars($p['receipt_image']) ?>" target="_blank">
                      <img src="uploads/receipts/<?= htmlspecialchars($p['receipt_image']) ?>" 
                           style="max-width:120px;border-radius:6px;border:1px solid #ddd;margin-top:4px;"
                           alt="Receipt">
                    </a>
                  </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="btn-row">
                  <a href="admin-payment-detail.php?id=<?= $p['payment_id'] ?>" class="btn btn-primary">
                    Details
                  </a>
                  <form action="admin-payment-received.php" method="post" style="display:inline;">
                    <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                    <input type="hidden" name="return_url" value="admin-payments.php?filter=<?= urlencode($filter) ?>">
                    <button type="submit" class="btn btn-ghost"
                            onclick="return confirm('Send received notification?')">
                      Received
                    </button>
                  </form>
                  <form action="admin-payment-approve.php" method="post" style="display:inline;">
                    <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                    <input type="hidden" name="return_url" value="admin-payments.php?filter=<?= urlencode($filter) ?>">
                    <button type="submit" name="action" value="approve" class="btn btn-primary">
                      Approve
                    </button>
                  </form>
                  <form action="admin-payment-approve.php" method="post" style="display:inline;">
                    <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                    <input type="hidden" name="return_url" value="admin-payments.php?filter=<?= urlencode($filter) ?>">
                    <button type="submit" name="action" value="reject" class="btn btn-ghost"
                            onclick="return confirm('Reject this payment?')">
                      Reject
                    </button>
                  </form>
                </div>

              <?php else: ?>
                <!-- Non-Pending Payment Details -->
                <div style="background:#f9fafb;padding:8px;border-radius:6px;margin-bottom:8px;">
                  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;">
                    <div>
                      <div class="small muted">Status</div>
                      <div class="small"><?= ucfirst($p['payment_status']) ?></div>
                    </div>
                    <?php if ($p['payment_date']): ?>
                      <div>
                        <div class="small muted">Date</div>
                        <div class="small"><?= date('M d, Y', strtotime($p['payment_date'])) ?></div>
                      </div>
                    <?php endif; ?>
                    <?php if ($p['payment_method']): ?>
                      <div>
                        <div class="small muted">Method</div>
                        <div class="small"><?= htmlspecialchars($p['payment_method']) ?></div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Actions -->
                <div class="btn-row">
                  <a href="admin-payment-detail.php?id=<?= $p['payment_id'] ?>" class="btn btn-ghost">
                    Details
                  </a>
                  <?php if ($p['payment_status'] === 'paid'): ?>
                    <form action="admin-payment-received.php" method="post" style="display:inline;">
                      <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                      <input type="hidden" name="return_url" value="admin-payments.php?filter=<?= urlencode($filter) ?>">
                      <button type="submit" class="btn btn-ghost"
                              onclick="return confirm('Send received notification?')">
                        Notify Received
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>

<?php include 'footer.php'; ?>