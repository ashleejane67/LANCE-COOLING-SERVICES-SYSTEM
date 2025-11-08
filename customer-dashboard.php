<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';
if (empty($_SESSION['customer_id'])) { header('Location: login.php'); exit; }

$cid = (int)$_SESSION['customer_id'];
$success_msg = isset($_GET['success']) ? $_GET['success'] : '';
$view = isset($_GET['view']) ? $_GET['view'] : 'services';

// Get customer info
$customer_query = "SELECT name, email FROM customer WHERE customer_id = ? LIMIT 1";
$cust_stmt = mysqli_prepare($conn, $customer_query);
mysqli_stmt_bind_param($cust_stmt, 'i', $cid);
mysqli_stmt_execute($cust_stmt);
$cust_result = mysqli_stmt_get_result($cust_stmt);
$customer = mysqli_fetch_assoc($cust_result);
mysqli_stmt_close($cust_stmt);

// Get services by category
$services_by_category = [];
$category_counts = ['cleaning' => 0, 'repair' => 0, 'installation' => 0, 'maintenance' => 0];
$svc_query = "SELECT service_id, service_name, base_price, description, category, appliance_category 
              FROM services 
              WHERE category IN ('cleaning', 'repair', 'installation', 'maintenance') 
              ORDER BY category, appliance_category, service_name";
$svc_result = mysqli_query($conn, $svc_query);
while ($row = mysqli_fetch_assoc($svc_result)) {
  $services_by_category[$row['category']][] = $row;
  if (isset($category_counts[$row['category']])) {
    $category_counts[$row['category']]++;
  }
}

// Get service requests
$sql = "
SELECT r.request_id, r.appliance_type, r.status AS request_status, r.scheduled_date,
       r.technician_id, r.created_at,
       (SELECT sl.service_type FROM service_line sl WHERE sl.request_id = r.request_id LIMIT 1) AS service_type,
       (SELECT sl.problem_description FROM service_line sl WHERE sl.request_id = r.request_id LIMIT 1) AS problem_description,
       (SELECT sl.urgency FROM service_line sl WHERE sl.request_id = r.request_id LIMIT 1) AS urgency,
       (SELECT GROUP_CONCAT(s.service_name SEPARATOR ', ') 
        FROM service_line sl 
        INNER JOIN services s ON sl.service_id = s.service_id 
        WHERE sl.request_id = r.request_id) AS services_list,
       p.payment_id, p.payment_status, p.amount
FROM service_request r
LEFT JOIN payment p ON p.request_id = r.request_id
WHERE r.customer_id = ?
ORDER BY r.created_at DESC";
$stm = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stm, 'i', $cid);
mysqli_stmt_execute($stm);
$res = mysqli_stmt_get_result($stm);

$active = []; $history = [];
while ($r = mysqli_fetch_assoc($res)) {
  if (in_array($r['request_status'], ['pending','scheduled','in_progress'], true)) $active[] = $r;
  else $history[] = $r;
}
mysqli_stmt_close($stm);

function tech_name($conn, $id) {
  if (!$id) return '';
  $q = mysqli_query($conn, "SELECT name, phone_number FROM technician WHERE technician_id=".(int)$id." LIMIT 1");
  if ($q && mysqli_num_rows($q)) {
    $t = mysqli_fetch_assoc($q);
    return $t['name'] . (!empty($t['phone_number']) ? ' • '.$t['phone_number'] : '');
  }
  return '';
}
?>
<?php include 'head.php'; ?>
<?php include 'navbar.php'; ?>

<section class="section">
  <div class="container">

    <?php if ($success_msg): ?>
      <div class="card" style="background:#eafcf3;border:1px solid #bde7cf;padding:10px;border-radius:12px;margin-bottom:12px;">
        ✓ <?= htmlspecialchars($success_msg) ?>
      </div>
    <?php endif; ?>

    <div class="card" style="padding:16px;margin-bottom:16px;">
      <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;flex-wrap:wrap;">
        <div>
          <h2 style="margin:0 0 4px;">Hello, <?= htmlspecialchars($customer['name']) ?></h2>
          <div class="small muted"><?= htmlspecialchars($customer['email']) ?></div>
        </div>
        <div class="btn-row">
          <a href="book.php" class="btn btn-primary">Book Service</a>
          <a href="payment.php" class="btn btn-ghost">Payment</a>
          <a href="logout.php" class="btn btn-ghost">Log out</a>
        </div>
      </div>
    </div>

    <div class="card" style="padding:16px;margin-bottom:16px;">
      <div class="btn-row">
        <a href="?view=services" class="btn <?= $view === 'services' ? 'btn-primary' : 'btn-ghost' ?>">
          Services
        </a>
        <a href="?view=active" class="btn <?= $view === 'active' ? 'btn-primary' : 'btn-ghost' ?>">
          Active Requests
        </a>
        <a href="?view=history" class="btn <?= $view === 'history' ? 'btn-primary' : 'btn-ghost' ?>">
          History
        </a>
      </div>
    </div>

    <?php if ($view === 'services'): ?>
      <div class="card" style="padding:16px;">
        <h3 style="margin:0 0 8px;">Lance Services</h3>
        <p class="small muted" style="margin-bottom:16px;">Explore our complete range of professional appliance services</p>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px;">
          <div class="card" style="padding:12px;text-align:center;">
            <div style="font-size:32px;margin-bottom:8px;">🧹</div>
            <div style="font-weight:600;margin-bottom:4px;">Cleaning</div>
            <div class="small muted" style="margin-bottom:10px;"><?= $category_counts['cleaning'] ?> services</div>
            <a href="?view=cleaning" class="btn btn-ghost" style="width:100%;">View</a>
          </div>
          
          <div class="card" style="padding:12px;text-align:center;">
            <div style="font-size:32px;margin-bottom:8px;">🔧</div>
            <div style="font-weight:600;margin-bottom:4px;">Repair</div>
            <div class="small muted" style="margin-bottom:10px;"><?= $category_counts['repair'] ?> services</div>
            <a href="?view=repair" class="btn btn-ghost" style="width:100%;">View</a>
          </div>
          
          <div class="card" style="padding:12px;text-align:center;">
            <div style="font-size:32px;margin-bottom:8px;">⚙️</div>
            <div style="font-weight:600;margin-bottom:4px;">Installation</div>
            <div class="small muted" style="margin-bottom:10px;"><?= $category_counts['installation'] ?> services</div>
            <a href="?view=installation" class="btn btn-ghost" style="width:100%;">View</a>
          </div>
          
          <div class="card" style="padding:12px;text-align:center;">
            <div style="font-size:32px;margin-bottom:8px;">🔍</div>
            <div style="font-weight:600;margin-bottom:4px;">Maintenance</div>
            <div class="small muted" style="margin-bottom:10px;"><?= $category_counts['maintenance'] ?> services</div>
            <a href="?view=maintenance" class="btn btn-ghost" style="width:100%;">View</a>
          </div>
        </div>
        
        <div style="text-align:center;padding-top:12px;border-top:1px solid #ddd;">
          <a href="book.php" class="btn btn-primary">Book a Service Now</a>
        </div>
      </div>

    <?php elseif ($view === 'active'): ?>
      <div class="card" style="padding:16px;">
        <h3 style="margin:0 0 12px;">Active Service Requests</h3>
        <?php if (empty($active)): ?>
          <div style="text-align:center;padding:40px 20px;">
            <p class="muted">No active requests at the moment.</p>
            <a href="book.php" class="btn btn-primary" style="margin-top:12px;">Book a New Service</a>
          </div>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;">
            <?php foreach ($active as $r): ?>
              <a href="customer-request-detail.php?id=<?= (int)$r['request_id'] ?>" class="card" style="padding:12px;text-decoration:none;color:inherit;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                  <div class="small muted">Request #<?= (int)$r['request_id'] ?></div>
                  <?php if (!empty($r['service_type'])): ?>
                    <span class="small" style="padding:4px 8px;border-radius:8px;
                      <?= $r['service_type'] === 'house-to-house' ? 'background:#447794;color:white;' : 'background:#ffedd5;' ?>">
                      <?= $r['service_type'] === 'house-to-house' ? 'House' : 'Shop' ?>
                    </span>
                  <?php endif; ?>
                </div>
                
                <h4 style="margin:0 0 8px;font-weight:600;">
                  <?= htmlspecialchars($r['appliance_type'] ?: 'Appliance') ?>
                </h4>
                
                <?php if (!empty($r['services_list'])): ?>
                  <div style="background:#f9fafb;padding:6px 8px;margin-bottom:8px;border-radius:6px;">
                    <div class="small"><strong>Services:</strong> <?= htmlspecialchars($r['services_list']) ?></div>
                  </div>
                <?php endif; ?>
                
                <?php if (!empty($r['problem_description'])): ?>
                  <div style="background:#f9fafb;padding:6px 8px;margin-bottom:8px;border-radius:6px;">
                    <div class="small"><strong>Problem:</strong> <?= htmlspecialchars(mb_substr($r['problem_description'], 0, 80)) ?><?= mb_strlen($r['problem_description']) > 80 ? '...' : '' ?></div>
                  </div>
                <?php endif; ?>
                
                <div class="small" style="margin-top:8px;">
                  <strong>Status:</strong> 
                  <span style="padding:3px 8px;border-radius:8px;margin-left:4px;
                    <?php if ($r['request_status'] === 'pending'): ?>background:#fef3c7;
                    <?php elseif ($r['request_status'] === 'scheduled'): ?>background:#dbeafe;
                    <?php elseif ($r['request_status'] === 'in_progress'): ?>background:#d1fae5;
                    <?php else: ?>background:#f3f4f6;<?php endif; ?>">
                    <?= htmlspecialchars(str_replace('_',' ', $r['request_status'])) ?>
                  </span>
                </div>
                
                <div class="small" style="margin-top:6px;">
                  <strong>Schedule:</strong>
                  <?= $r['scheduled_date'] ? date('M d, Y g:i A', strtotime($r['scheduled_date'])) : 'TBD' ?>
                </div>
                
                <?php if ($r['technician_id']): ?>
                  <div class="small" style="margin-top:6px;">
                    <strong>Technician:</strong> <?= htmlspecialchars(tech_name($conn, (int)$r['technician_id'])) ?>
                  </div>
                <?php endif; ?>

                <?php if ($r['payment_id']): ?>
                  <div style="margin-top:8px;padding:6px 8px;border-radius:8px;font-size:12px;font-weight:600;
                    <?php if ($r['payment_status'] === 'paid'): ?>background:#d1fae5;
                    <?php elseif ($r['payment_status'] === 'pending'): ?>background:#fef3c7;
                    <?php else: ?>background:#fee2e2;<?php endif; ?>">
                    <?php if ($r['payment_status'] === 'paid'): ?>
                      ✓ Paid
                    <?php elseif ($r['payment_status'] === 'pending'): ?>
                      Payment Pending
                    <?php else: ?>
                      Payment Required
                    <?php endif; ?>
                    <?php if ($r['amount'] > 0): ?>
                      - ₱<?= number_format($r['amount'], 2) ?>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div style="color:#447794;font-weight:600;font-size:13px;margin-top:10px;">
                  View Details →
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($view === 'history'): ?>
      <div class="card" style="padding:16px;">
        <h3 style="margin:0 0 12px;">Service History</h3>
        <?php if (empty($history)): ?>
          <p class="muted small">No past services yet.</p>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;">
            <?php foreach ($history as $r): ?>
              <a href="customer-request-detail.php?id=<?= (int)$r['request_id'] ?>" class="card" style="padding:12px;text-decoration:none;color:inherit;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                  <div class="small muted">Request #<?= (int)$r['request_id'] ?></div>
                  <?php if (!empty($r['service_type'])): ?>
                    <span class="small" style="padding:4px 8px;border-radius:8px;
                      <?= $r['service_type'] === 'house-to-house' ? 'background:#447794;color:white;' : 'background:#ffedd5;' ?>">
                      <?= $r['service_type'] === 'house-to-house' ? 'House' : 'Shop' ?>
                    </span>
                  <?php endif; ?>
                </div>
                
                <h4 style="margin:0 0 8px;font-weight:600;">
                  <?= htmlspecialchars($r['appliance_type'] ?: 'Appliance') ?>
                </h4>
                
                <?php if (!empty($r['services_list'])): ?>
                  <div style="background:#f9fafb;padding:6px 8px;margin-bottom:8px;border-radius:6px;">
                    <div class="small"><?= htmlspecialchars($r['services_list']) ?></div>
                  </div>
                <?php endif; ?>
                
                <div class="small" style="margin-top:8px;">
                  <strong>Status:</strong> 
                  <span style="padding:3px 8px;border-radius:8px;margin-left:4px;background:#d1fae5;">
                    <?= htmlspecialchars(str_replace('_',' ', $r['request_status'])) ?>
                  </span>
                </div>
                
                <div class="small" style="margin-top:6px;">
                  <strong>Completed:</strong> <?= $r['scheduled_date'] ? date('M d, Y', strtotime($r['scheduled_date'])) : '—' ?>
                </div>

                <?php if ($r['payment_id']): ?>
                  <div style="margin-top:8px;padding:6px 8px;border-radius:8px;font-size:12px;font-weight:600;
                    <?php if ($r['payment_status'] === 'paid'): ?>background:#d1fae5;<?php else: ?>background:#fee2e2;<?php endif; ?>">
                    <?php if ($r['payment_status'] === 'paid'): ?>
                      ✓ Paid
                    <?php else: ?>
                      Payment: <?= ucfirst($r['payment_status']) ?>
                    <?php endif; ?>
                    <?php if ($r['amount'] > 0): ?>
                      - ₱<?= number_format($r['amount'], 2) ?>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div style="color:#447794;font-weight:600;font-size:13px;margin-top:10px;">
                  View Details →
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif (in_array($view, ['cleaning', 'repair', 'installation', 'maintenance'])): ?>
      <div class="card" style="padding:16px;">
        <h3 style="margin:0 0 12px;"><?= ucfirst($view) ?> Services</h3>
        
        <?php if (!empty($services_by_category[$view])): ?>
          <?php 
          $grouped_services = [];
          foreach ($services_by_category[$view] as $service) {
            $app_cat = $service['appliance_category'] ?: 'General';
            $grouped_services[$app_cat][] = $service;
          }
          ?>
          
          <?php foreach ($grouped_services as $app_category => $services): ?>
            <div style="margin-bottom:16px;">
              <h4 style="margin:0 0 8px;font-size:14px;font-weight:600;color:#447794;">
                <?= htmlspecialchars($app_category) ?>
              </h4>
              <?php foreach ($services as $service): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;border:1px solid #e5e7eb;margin-bottom:4px;background:#fafafa;">
                  <div>
                    <div style="font-weight:500;"><?= htmlspecialchars($service['service_name']) ?></div>
                    <?php if (!empty($service['description'])): ?>
                      <div class="small muted" style="margin-top:2px;">
                        <?= htmlspecialchars($service['description']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div style="font-weight:600;color:#123249;white-space:nowrap;margin-left:12px;">
                    ₱<?= number_format($service['base_price'], 2) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          
          <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid #ddd;">
            <a href="book.php" class="btn btn-primary">Book This Service</a>
          </div>
        <?php else: ?>
          <p class="muted small">No services available in this category.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php include 'footer.php'; ?>