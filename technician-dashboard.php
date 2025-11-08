<?php
// technician-dashboard.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician' || !isset($_SESSION['tech_id'])) {
  header('Location: staff-login.php?error=' . urlencode('Please login as technician.'));
  exit;
}

require_once 'db.php';
if (!$conn) { die('Database connection error.'); }

$TECH_ID = (int)$_SESSION['tech_id'];

// Pull technician profile
$tech_query = "SELECT technician_id, username, email, name, phone_number, position, status 
               FROM technician WHERE technician_id = {$TECH_ID} LIMIT 1";
$tech_result = mysqli_query($conn, $tech_query);
$tech = mysqli_fetch_assoc($tech_result);

if (!$tech) {
  header('Location: staff-login.php?error=' . urlencode('Technician not found.'));
  exit;
}

// KPI counters
$pending_count = 0;
$scheduled_count = 0;
$in_progress_count = 0;
$completed_count = 0;
$total_assigned = 0;

$total_query = "SELECT COUNT(*) AS c FROM service_request WHERE technician_id = {$TECH_ID}";
$total_result = mysqli_query($conn, $total_query);
$total_assigned = (int)mysqli_fetch_assoc($total_result)['c'];

$pending_query = "SELECT COUNT(*) AS c FROM service_request 
                  WHERE technician_id = {$TECH_ID} AND status = 'pending'";
$pending_result = mysqli_query($conn, $pending_query);
$pending_count = (int)mysqli_fetch_assoc($pending_result)['c'];

$scheduled_query = "SELECT COUNT(*) AS c FROM service_request 
                    WHERE technician_id = {$TECH_ID} AND status = 'scheduled'";
$scheduled_result = mysqli_query($conn, $scheduled_query);
$scheduled_count = (int)mysqli_fetch_assoc($scheduled_result)['c'];

$in_progress_query = "SELECT COUNT(*) AS c FROM service_request 
                      WHERE technician_id = {$TECH_ID} AND status = 'in_progress'";
$in_progress_result = mysqli_query($conn, $in_progress_query);
$in_progress_count = (int)mysqli_fetch_assoc($in_progress_result)['c'];

$completed_query = "SELECT COUNT(*) AS c FROM service_request 
                    WHERE technician_id = {$TECH_ID} AND status = 'completed'";
$completed_result = mysqli_query($conn, $completed_query);
$completed_count = (int)mysqli_fetch_assoc($completed_result)['c'];

// Filters
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = "WHERE sr.technician_id = {$TECH_ID}";

if ($tab === 'pending') {
  $where .= " AND sr.status = 'pending'";
} elseif ($tab === 'scheduled') {
  $where .= " AND sr.status = 'scheduled'";
} elseif ($tab === 'in_progress') {
  $where .= " AND sr.status = 'in_progress'";
} elseif ($tab === 'completed') {
  $where .= " AND sr.status = 'completed'";
}

if ($search !== '') {
  $search_esc = mysqli_real_escape_string($conn, $search);
  $where .= " AND (c.name LIKE '%{$search_esc}%' 
              OR c.address LIKE '%{$search_esc}%' 
              OR sr.appliance_type LIKE '%{$search_esc}%')";
}

// Fetch jobs - simplified query
$jobs_query = "SELECT 
    sr.request_id,
    sr.status,
    sr.created_at
  FROM service_request sr
  INNER JOIN customer c ON sr.customer_id = c.customer_id
  {$where}
  ORDER BY 
    CASE 
      WHEN sr.status = 'pending' THEN 1
      WHEN sr.status = 'in_progress' THEN 2
      WHEN sr.status = 'scheduled' THEN 3
      WHEN sr.status = 'completed' THEN 4
      ELSE 5
    END,
    sr.created_at DESC
  LIMIT 50";

$jobs_result = mysqli_query($conn, $jobs_query);
$jobs = [];
while ($row = mysqli_fetch_assoc($jobs_result)) {
  $jobs[] = $row;
}
?>
<?php include 'head.php'; ?>

<section class="section">
  <div class="container">

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
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <div>
          <h2 style="margin:0;"><?php echo htmlspecialchars($tech['name']); ?></h2>
          <div class="small muted"><?php echo htmlspecialchars($tech['position'] ?? 'Technician'); ?> • <?php echo htmlspecialchars($tech['email']); ?></div>
        </div>
        <div class="btn-row">
          <a href="technician-profile.php" class="btn btn-ghost">Profile</a>
          <a href="technician-logout.php" class="btn btn-ghost">Logout</a>
        </div>
      </div>
    </div>

    <div class="card" style="padding:16px;margin-bottom:12px;">
      <h3 style="margin:0 0 10px;font-size:14px;">Statistics</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;">
        <div style="padding:10px;background:#f9fafb;border-radius:6px;text-align:center;">
          <div class="small muted">New</div>
          <div style="font-size:24px;font-weight:600;margin-top:4px;"><?php echo $pending_count; ?></div>
        </div>
        <div style="padding:10px;background:#f9fafb;border-radius:6px;text-align:center;">
          <div class="small muted">Scheduled</div>
          <div style="font-size:24px;font-weight:600;margin-top:4px;"><?php echo $scheduled_count; ?></div>
        </div>
        <div style="padding:10px;background:#f9fafb;border-radius:6px;text-align:center;">
          <div class="small muted">In Progress</div>
          <div style="font-size:24px;font-weight:600;margin-top:4px;"><?php echo $in_progress_count; ?></div>
        </div>
        <div style="padding:10px;background:#f9fafb;border-radius:6px;text-align:center;">
          <div class="small muted">Completed</div>
          <div style="font-size:24px;font-weight:600;margin-top:4px;"><?php echo $completed_count; ?></div>
        </div>
        <div style="padding:10px;background:#f9fafb;border-radius:6px;text-align:center;">
          <div class="small muted">Total</div>
          <div style="font-size:24px;font-weight:600;margin-top:4px;"><?php echo $total_assigned; ?></div>
        </div>
      </div>
    </div>

    <div class="card" style="padding:16px;margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div class="btn-row">
          <a href="?tab=all" class="btn <?php echo $tab === 'all' ? 'btn-primary' : 'btn-ghost'; ?>">All</a>
          <a href="?tab=pending" class="btn <?php echo $tab === 'pending' ? 'btn-primary' : 'btn-ghost'; ?>">New</a>
          <a href="?tab=scheduled" class="btn <?php echo $tab === 'scheduled' ? 'btn-primary' : 'btn-ghost'; ?>">Scheduled</a>
          <a href="?tab=in_progress" class="btn <?php echo $tab === 'in_progress' ? 'btn-primary' : 'btn-ghost'; ?>">Progress</a>
          <a href="?tab=completed" class="btn <?php echo $tab === 'completed' ? 'btn-primary' : 'btn-ghost'; ?>">Completed</a>
        </div>
        <form method="get" style="display:flex;gap:6px;">
          <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
          <input type="text" name="q" class="input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" style="width:200px;">
          <button type="submit" class="btn btn-primary">Search</button>
        </form>
      </div>
    </div>

    <div class="card" style="padding:16px;">
      <h3 style="margin:0 0 12px;font-size:14px;">
        <?php 
          if ($tab === 'all') echo 'All Jobs';
          elseif ($tab === 'pending') echo 'New Requests';
          elseif ($tab === 'scheduled') echo 'Scheduled Jobs';
          elseif ($tab === 'in_progress') echo 'In Progress';
          elseif ($tab === 'completed') echo 'Completed';
        ?>
      </h3>
      
      <?php if (empty($jobs)): ?>
        <p class="muted small">No jobs found.</p>
      <?php else: ?>
        <div style="display:grid;gap:10px;">
          <?php foreach ($jobs as $job): ?>
            <div class="card" style="padding:12px;display:flex;justify-content:space-between;align-items:center;gap:10px;">
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="font-weight:600;font-size:16px;">Request #<?php echo $job['request_id']; ?></div>
                <span style="padding:4px 10px;background:<?php 
                  if ($job['status'] === 'pending') echo '#fef3c7';
                  elseif ($job['status'] === 'scheduled') echo '#dbeafe';
                  elseif ($job['status'] === 'in_progress') echo '#d1fae5';
                  elseif ($job['status'] === 'completed') echo '#d1fae5';
                  else echo '#fee2e2';
                ?>;border-radius:8px;font-size:11px;font-weight:600;">
                  <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                </span>
              </div>
              
              <div class="btn-row">
                <a href="technician-job-detail.php?id=<?php echo $job['request_id']; ?>" class="btn btn-primary">
                  View
                </a>
                <?php if ($job['status'] === 'pending'): ?>
                  <a href="technician-update-status.php?id=<?php echo $job['request_id']; ?>&status=scheduled" 
                     class="btn btn-ghost"
                     onclick="return confirm('Schedule this job?');">
                     Schedule
                  </a>
                  <a href="technician-update-status.php?id=<?php echo $job['request_id']; ?>&status=in_progress" 
                     class="btn btn-ghost">
                     Start
                  </a>
                <?php endif; ?>
                <?php if ($job['status'] === 'scheduled'): ?>
                  <a href="technician-update-status.php?id=<?php echo $job['request_id']; ?>&status=in_progress" 
                     class="btn btn-ghost">
                     Start
                  </a>
                <?php endif; ?>
                <?php if ($job['status'] === 'in_progress'): ?>
                  <a href="technician-update-status.php?id=<?php echo $job['request_id']; ?>&status=completed" 
                     class="btn btn-ghost">
                     Complete
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>