<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';
if (empty($_SESSION['customer_id'])) { header('Location: login.php'); exit; }
$cid = (int)$_SESSION['customer_id'];

// Get customer info
$customer_query = "SELECT name, email FROM customer WHERE customer_id = ? LIMIT 1";
$cust_stmt = mysqli_prepare($conn, $customer_query);
mysqli_stmt_bind_param($cust_stmt, 'i', $cid);
mysqli_stmt_execute($cust_stmt);
$cust_result = mysqli_stmt_get_result($cust_stmt);
$customer = mysqli_fetch_assoc($cust_result);
mysqli_stmt_close($cust_stmt);

// Unpaid and pending payments for this customer
$sql = "
SELECT p.payment_id, p.amount, p.payment_status, p.payment_method, p.request_id,
       p.payment_term, p.sender_name, p.reference_number, p.submitted_at,
       r.appliance_type, r.status AS request_status, r.cost,
       (SELECT service_id FROM service_line WHERE request_id=r.request_id LIMIT 1) AS service_id
FROM payment p
JOIN service_request r ON r.request_id = p.request_id
WHERE r.customer_id = ? AND p.payment_status IN ('pending', 'unpaid')
ORDER BY p.payment_id DESC";
$st = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($st,'i',$cid);
mysqli_stmt_execute($st);
$rows = mysqli_stmt_get_result($st);
?>
<?php include 'head.php'; ?>

<section class="section">
  <div class="container">
    
    <div class="card" style="padding:16px;margin-bottom:12px;">
      <h2 style="margin:0 0 4px;">Payment Center</h2>
      <div class="small muted">Submit payment and upload receipt</div>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="card" style="background:#eafcf3;border:1px solid #bde7cf;padding:10px;margin-bottom:12px;">
        ✓ <?= htmlspecialchars($_GET['success']) ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="card" style="background:#fee;border:1px solid #fcc;padding:10px;margin-bottom:12px;">
        ✗ <?= htmlspecialchars($_GET['error']) ?>
      </div>
    <?php endif; ?>

    <div class="card" style="padding:16px;margin-bottom:12px;background:#e0f2fe;border-left:3px solid #447794;">
      <h3 style="margin:0 0 8px;font-size:14px;">Payment Process</h3>
      <ol style="margin:0;padding-left:20px;font-size:13px;line-height:1.6;">
        <li>Pay through remittance centers to LANCE PAYPAL Account</li>
        <li>Keep your receipt as proof of payment</li>
        <li>Wait for admin approval (within 24 hours)</li>
      </ol>
    </div>

    <div class="card" style="padding:16px;margin-bottom:12px;background:#06b6d4;color:white;">
      <p style="margin:0 0 10px;font-size:13px;">
        Pay via Online Banking, Palawan Express, MLhuillier, Cebuana, G-Cash or other remittance centers:
      </p>
      <div style="font-size:14px;line-height:1.6;">
        <div><strong>Bank:</strong> PAYPAL</div>
        <div><strong>Account Number:</strong> 002840542222</div>
        <div><strong>Account Name:</strong> LANCE COOLING SERVICES</div>
      </div>
    </div>

    <?php if (!$rows || mysqli_num_rows($rows) === 0): ?>
      <div class="card" style="padding:16px;">
        <p class="muted small">No outstanding payments.</p>
      </div>
    <?php else: ?>
      <?php while($r = mysqli_fetch_assoc($rows)): ?>
        <div class="card" style="padding:16px;">
          <div style="display:inline-block;padding:4px 10px;background:#fef3c7;border-radius:12px;margin-bottom:10px;">
            <span class="small" style="font-weight:600;">Outstanding Balance</span>
          </div>
          
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
            <div>
              <div class="small muted">Request #<?= (int)$r['request_id'] ?></div>
              <h3 style="margin:4px 0 0;font-size:16px;"><?= htmlspecialchars($r['appliance_type']) ?></h3>
            </div>
            <div style="text-align:right;">
              <?php
                $amount = (float)$r['amount'];
                if ($amount <= 0 && !is_null($r['cost'])) $amount = (float)$r['cost'];
                if ($amount <= 0) $amount = 14858.96;
              ?>
              <div style="font-size:20px;font-weight:600;">₱<?= number_format($amount, 2) ?></div>
              <div class="small muted">Total</div>
            </div>
          </div>

          <?php if ($r['payment_status'] === 'pending' && !empty($r['submitted_at'])): ?>
            <div style="background:#fef3c7;padding:10px;border-radius:6px;">
              <div class="small" style="font-weight:600;margin-bottom:6px;">Pending Admin Approval</div>
              <div class="small">
                <div>Payment: <?= htmlspecialchars($r['payment_term'] ?: 'N/A') ?></div>
                <div>Sender: <?= htmlspecialchars($r['sender_name']) ?></div>
                <div>Reference: <?= htmlspecialchars($r['reference_number']) ?></div>
                <div>Submitted: <?= date('M d, Y', strtotime($r['submitted_at'])) ?></div>
              </div>
            </div>
          <?php else: ?>
            <form action="payment_submit.php" method="post" enctype="multipart/form-data" style="margin-top:12px;">
              <input type="hidden" name="payment_id" value="<?= (int)$r['payment_id'] ?>">
              <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">

              <h4 style="margin:0 0 10px;font-size:14px;">Payment Details</h4>

              <div style="margin-bottom:10px;">
                <label>
                  <div class="small muted">Sender Name</div>
                  <input type="text" name="sender_name" class="input" value="<?= htmlspecialchars($customer['name']) ?>" required>
                </label>
              </div>

              <div style="margin-bottom:10px;">
                <div class="small muted" style="margin-bottom:4px;">Payment Method</div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                  <label style="padding:6px 10px;border:1px solid #ddd;cursor:pointer;display:flex;align-items:center;gap:4px;border-radius:6px;">
                    <input type="radio" name="payment_method" value="PayPal" required>
                    <span class="small">PayPal</span>
                  </label>
                  <label style="padding:6px 10px;border:1px solid #ddd;cursor:pointer;display:flex;align-items:center;gap:4px;border-radius:6px;">
                    <input type="radio" name="payment_method" value="BDO" required>
                    <span class="small">BDO</span>
                  </label>
                  <label style="padding:6px 10px;border:1px solid #ddd;cursor:pointer;display:flex;align-items:center;gap:4px;border-radius:6px;">
                    <input type="radio" name="payment_method" value="GCash" required>
                    <span class="small">GCash</span>
                  </label>
                  <label style="padding:6px 10px;border:1px solid #ddd;cursor:pointer;display:flex;align-items:center;gap:4px;border-radius:6px;">
                    <input type="radio" name="payment_method" value="Cebuana" required>
                    <span class="small">Cebuana</span>
                  </label>
                  <label style="padding:6px 10px;border:1px solid #ddd;cursor:pointer;display:flex;align-items:center;gap:4px;border-radius:6px;">
                    <input type="radio" name="payment_method" value="MLhuillier" required>
                    <span class="small">MLhuillier</span>
                  </label>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:10px;">
                <label>
                  <div class="small muted">Reference Number</div>
                  <input type="text" name="reference_number" class="input" placeholder="Transaction ref" required>
                </label>
                <label>
                  <div class="small muted">Amount</div>
                  <input type="number" step="0.01" name="amount_display" class="input" value="<?= $amount ?>" required>
                </label>
              </div>

              <div style="margin-bottom:10px;">
                <label>
                  <div class="small muted">Receiver Name</div>
                  <input type="text" name="receiver_name" class="input" value="LANCE COOLING SERVICES" required>
                </label>
              </div>

              <div style="margin-bottom:10px;">
                <div class="small muted" style="margin-bottom:4px;">Receipt Image</div>
                <div style="border:2px dashed #ddd;padding:16px;text-align:center;cursor:pointer;border-radius:6px;">
                  <input type="file" name="receipt_image" accept="image/*" required style="display:none;" id="file-<?= $r['payment_id'] ?>">
                  <label for="file-<?= $r['payment_id'] ?>" style="cursor:pointer;">
                    <div style="font-size:32px;">📷</div>
                    <div class="small" style="font-weight:600;margin-top:4px;">Click to upload</div>
                    <div class="small muted">JPG, PNG (Max 5MB)</div>
                  </label>
                </div>
                <div id="preview-<?= $r['payment_id'] ?>" style="margin-top:8px;display:none;">
                  <img id="preview-img-<?= $r['payment_id'] ?>" style="max-width:150px;border-radius:6px;border:1px solid #ddd;">
                </div>
              </div>

              <div style="text-align:center;margin-top:12px;">
                <button type="submit" class="btn btn-primary">Submit Payment</button>
              </div>
            </form>

            <script>
            document.getElementById('file-<?= $r['payment_id'] ?>').addEventListener('change', function(e) {
              if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                  document.getElementById('preview-<?= $r['payment_id'] ?>').style.display = 'block';
                  document.getElementById('preview-img-<?= $r['payment_id'] ?>').src = e.target.result;
                };
                reader.readAsDataURL(e.target.files[0]);
              }
            });
            </script>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>

    <div style="text-align:center;margin-top:12px;">
      <a href="customer-dashboard.php" class="btn btn-ghost">Back to Dashboard</a>
    </div>

  </div>
</section>

<style>
input[type="radio"]:checked + span {
  font-weight: 600;
}
label:has(input[type="radio"]:checked) {
  background: #f0f4f8;
  border-color: #447794;
}
@media (max-width: 768px) {
  div[style*="grid-template-columns:repeat(2,1fr)"] {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php include 'footer.php'; ?>