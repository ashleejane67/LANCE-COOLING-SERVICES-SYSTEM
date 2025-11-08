<?php
// technician-job-detail.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician' || !isset($_SESSION['tech_id'])) {
  header('Location: staff-login.php?error=' . urlencode('Please login as technician.'));
  exit;
}

require_once 'db.php';
if (!$conn) { die('Database connection error.'); }

$TECH_ID = (int)$_SESSION['tech_id'];
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$request_id) {
  header('Location: technician-dashboard.php?error=' . urlencode('Invalid job ID.'));
  exit;
}

// Fetch job details with customer info
$job_query = "SELECT 
    sr.*,
    c.customer_id,
    c.name AS customer_name,
    c.email AS customer_email,
    c.phone_number AS customer_phone,
    c.address AS customer_address,
    p.payment_id,
    p.amount,
    p.payment_method,
    p.payment_status,
    p.payment_date,
    jt.status AS tracking_status,
    jt.updated_at AS tracking_updated
  FROM service_request sr
  INNER JOIN customer c ON sr.customer_id = c.customer_id
  LEFT JOIN payment p ON sr.request_id = p.request_id
  LEFT JOIN job_tracking jt ON sr.request_id = jt.request_id
  WHERE sr.request_id = {$request_id} AND sr.technician_id = {$TECH_ID}
  LIMIT 1";

$job_result = mysqli_query($conn, $job_query);
$job = mysqli_fetch_assoc($job_result);

if (!$job) {
  header('Location: technician-dashboard.php?error=' . urlencode('Job not found or not assigned to you.'));
  exit;
}

// Fetch services
$services_query = "SELECT 
                      s.service_id, 
                      s.service_name, 
                      s.description, 
                      s.base_price,
                      sl.service_type,
                      sl.problem_description,
                      sl.urgency
                   FROM service_line sl
                   INNER JOIN services s ON sl.service_id = s.service_id
                   WHERE sl.request_id = {$request_id}";
$services_result = mysqli_query($conn, $services_query);
$linked_services = [];
$service_type = 'Not specified';
$problem_description = 'No description provided';
$urgency = 'Normal';

while ($row = mysqli_fetch_assoc($services_result)) {
  $linked_services[] = $row;
  if ($service_type === 'Not specified' && !empty($row['service_type'])) {
    $service_type = $row['service_type'] === 'house-to-house' ? 'House-to-House' : 'In-Shop';
  }
  if (!empty($row['problem_description'])) {
    $problem_description = $row['problem_description'];
  }
  if (!empty($row['urgency'])) {
    $urgency = $row['urgency'];
  }
}

// Fetch job tracking history
$tracking_query = "SELECT * FROM job_tracking 
                   WHERE request_id = {$request_id} 
                   ORDER BY updated_at DESC";
$tracking_result = mysqli_query($conn, $tracking_query);
$tracking_history = [];
while ($row = mysqli_fetch_assoc($tracking_result)) {
  $tracking_history[] = $row;
}

// Define available status transitions based on current status
$available_statuses = [];
$current_status = trim($job['status']);

if (empty($current_status)) {
  $current_status = 'pending';
}

switch ($current_status) {
  case '':
  case 'pending':
    $available_statuses = [
      'scheduled' => 'Schedule',
      'in_progress' => 'Start Job',
      'cancelled' => 'Cancel'
    ];
    break;
  case 'scheduled':
    $available_statuses = [
      'in_progress' => 'Start Job',
      'cancelled' => 'Cancel'
    ];
    break;
  case 'in_progress':
    $available_statuses = [
      'completed' => 'Mark Complete',
      'cancelled' => 'Cancel'
    ];
    break;
  case 'completed':
  case 'cancelled':
    break;
}
?>
<?php include 'head.php'; ?>

<section class="section">
  <div class="container">
    
    <div style="margin-bottom:12px;">
      <a href="technician-dashboard.php" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:6px;">
        ← Back to Dashboard
      </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="card" style="background:#eafcf3;border:1px solid #bde7cf;padding:10px;margin-bottom:12px;">
        ✓ <?php echo htmlspecialchars($_GET['success']); ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
      <div class="card" style="background:#fee;border:1px solid #fcc;padding:10px;margin-bottom:12px;">
        ✗ <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>

    <div class="card" style="padding:16px;margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:10px;">
        <div>
          <div class="small muted">Request #<?php echo $request_id; ?></div>
          <h2 style="margin:4px 0 8px;"><?php echo htmlspecialchars($job['appliance_type']); ?></h2>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <span style="padding:3px 8px;background:<?php 
              if ($job['status'] === 'pending') echo '#fef3c7';
              elseif ($job['status'] === 'scheduled') echo '#dbeafe';
              elseif ($job['status'] === 'in_progress') echo '#d1fae5';
              elseif ($job['status'] === 'completed') echo '#d1fae5';
              else echo '#fee2e2';
            ?>;border-radius:8px;font-size:11px;font-weight:600;">
              <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
            </span>
            <span style="padding:3px 8px;background:#f0f0f0;border-radius:8px;font-size:11px;font-weight:600;">
              <?php echo $service_type; ?>
            </span>
          </div>
        </div>
        <div class="small muted">
          Created: <?php echo date('M d, Y h:i A', strtotime($job['created_at'])); ?>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px;">
      
      <div>
        <div class="card" style="padding:16px;margin-bottom:12px;">
          <h3 style="margin:0 0 12px;font-size:14px;font-weight:600;">Job Details</h3>
          
          <div style="margin-bottom:12px;">
            <div class="small muted" style="margin-bottom:4px;">Problem Description</div>
            <div style="background:#f9fafb;padding:10px;border-radius:6px;border-left:3px solid #ddd;font-size:13px;">
              <?php echo htmlspecialchars($problem_description); ?>
            </div>
          </div>

          <?php if (!empty($linked_services)): ?>
          <div style="margin-bottom:12px;">
            <div class="small muted" style="margin-bottom:6px;">Services Requested</div>
            <div style="display:grid;gap:6px;">
              <?php foreach ($linked_services as $service): ?>
                <div style="background:#f9fafb;padding:10px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;font-size:13px;">
                  <span><?php echo htmlspecialchars($service['service_name']); ?></span>
                  <?php if ($service['base_price'] > 0): ?>
                    <span style="font-weight:600;">₱<?php echo number_format($service['base_price'], 2); ?></span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <div>
              <div class="small muted" style="margin-bottom:4px;">Scheduled Date</div>
              <div style="font-size:13px;">
                <?php 
                  if ($job['scheduled_date']) {
                    echo date('M d, Y h:i A', strtotime($job['scheduled_date']));
                  } else {
                    echo 'Not scheduled yet';
                  }
                ?>
              </div>
            </div>
            
            <?php if ($job['cost']): ?>
            <div>
              <div class="small muted" style="margin-bottom:4px;">Estimated Cost</div>
              <div style="font-size:16px;font-weight:600;">₱<?php echo number_format($job['cost'], 2); ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="padding:16px;margin-bottom:12px;">
          <h3 style="margin:0 0 12px;font-size:14px;font-weight:600;">Customer Information</h3>
          
          <div style="display:grid;gap:12px;">
            <div>
              <div class="small muted" style="margin-bottom:4px;">Name</div>
              <div style="font-size:15px;font-weight:600;"><?php echo htmlspecialchars($job['customer_name']); ?></div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
              <div>
                <div class="small muted" style="margin-bottom:4px;">Email</div>
                <div style="font-size:13px;">
                  <a href="mailto:<?php echo htmlspecialchars($job['customer_email']); ?>" style="color:#2563eb;">
                    <?php echo htmlspecialchars($job['customer_email']); ?>
                  </a>
                </div>
              </div>
              
              <div>
                <div class="small muted" style="margin-bottom:4px;">Phone</div>
                <div style="font-size:13px;">
                  <?php if ($job['customer_phone']): ?>
                    <a href="tel:<?php echo htmlspecialchars($job['customer_phone']); ?>" style="color:#2563eb;">
                      <?php echo htmlspecialchars($job['customer_phone']); ?>
                    </a>
                  <?php else: ?>
                    Not provided
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div>
              <div class="small muted" style="margin-bottom:4px;">Address</div>
              <div style="font-size:13px;"><?php echo htmlspecialchars($job['customer_address'] ?? 'Not provided'); ?></div>
            </div>
          </div>
        </div>

        <?php if ($job['payment_id']): ?>
        <div class="card" style="padding:16px;">
          <h3 style="margin:0 0 12px;font-size:14px;font-weight:600;">Payment Information</h3>
          
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
            <div>
              <div class="small muted" style="margin-bottom:4px;">Amount</div>
              <div style="font-size:16px;font-weight:600;">₱<?php echo number_format($job['amount'], 2); ?></div>
            </div>
            
            <div>
              <div class="small muted" style="margin-bottom:4px;">Payment Method</div>
              <div style="font-size:13px;"><?php echo ucfirst($job['payment_method']); ?></div>
            </div>
            
            <div>
              <div class="small muted" style="margin-bottom:4px;">Status</div>
              <div>
                <span style="padding:3px 8px;background:<?php 
                  echo $job['payment_status'] === 'paid' ? '#d1fae5' : '#fef3c7';
                ?>;border-radius:8px;font-size:11px;font-weight:600;">
                  <?php echo ucfirst($job['payment_status']); ?>
                </span>
              </div>
            </div>
            
            <?php if ($job['payment_date']): ?>
            <div>
              <div class="small muted" style="margin-bottom:4px;">Payment Date</div>
              <div style="font-size:13px;">
                <?php echo date('M d, Y h:i A', strtotime($job['payment_date'])); ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div>
        <div class="card" style="padding:16px;margin-bottom:12px;">
          <h3 style="margin:0 0 12px;font-size:14px;font-weight:600;">Quick Actions</h3>
          
          <div style="display:grid;gap:8px;">
            <?php if ($job['customer_phone']): ?>
              <a href="tel:<?php echo htmlspecialchars($job['customer_phone']); ?>" class="btn btn-ghost">
                📞 Call Customer
              </a>
            <?php endif; ?>
            
            <a href="mailto:<?php echo htmlspecialchars($job['customer_email']); ?>" class="btn btn-ghost">
              ✉️ Email Customer
            </a>
          </div>

          <?php if (!empty($available_statuses)): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;">
              <div class="small" style="margin-bottom:8px;font-weight:600;">Update Status</div>
              <div class="small muted" style="margin-bottom:10px;">
                Current: <strong><?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></strong>
              </div>
              
              <div style="display:grid;gap:8px;">
                <?php foreach ($available_statuses as $status => $label): ?>
                  <a href="technician-update-status.php?id=<?php echo $request_id; ?>&status=<?php echo $status; ?>&return=detail" 
                     class="btn <?php 
                       echo $status === 'completed' ? 'btn-primary' : 
                            ($status === 'cancelled' ? 'btn-ghost' : 'btn-ghost'); 
                     ?>"
                     onclick="return confirm('Change status to <?php echo ucfirst(str_replace('_', ' ', $status)); ?>?');">
                    <?php echo $label; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($tracking_history)): ?>
        <div class="card" style="padding:16px;">
          <h3 style="margin:0 0 12px;font-size:14px;font-weight:600;">Status History</h3>
          
          <div style="position:relative;padding-left:20px;">
            <?php foreach ($tracking_history as $i => $track): ?>
              <?php if (!empty($track['status'])): ?>
              <div style="position:relative;padding-bottom:<?php echo $i < count($tracking_history) - 1 ? '16px' : '0'; ?>;">
                <div style="position:absolute;left:-16px;top:4px;width:8px;height:8px;border-radius:50%;background:#3b82f6;border:2px solid white;box-shadow:0 0 0 1px #e5e7eb;"></div>
                <?php if ($i < count($tracking_history) - 1): ?>
                  <div style="position:absolute;left:-13px;top:12px;width:2px;height:calc(100% - 8px);background:#e5e7eb;"></div>
                <?php endif; ?>
                <div style="font-size:13px;font-weight:600;margin-bottom:2px;">
                  <?php echo ucfirst(str_replace('_', ' ', $track['status'])); ?>
                </div>
                <div class="small muted">
                  <?php echo date('M d, Y h:i A', strtotime($track['updated_at'])); ?>
                </div>
              </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</section>

<?php include 'footer.php'; ?>