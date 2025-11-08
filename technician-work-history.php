<?php
// technician-work-history.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician' || !isset($_SESSION['tech_id'])) {
  header('Location: staff-login.php?error=' . urlencode('Please login as technician.'));
  exit;
}

require_once 'db.php';

$TECH_ID = (int)$_SESSION['tech_id'];

// Fetch technician info
$tech_query = "SELECT name, email FROM technician WHERE technician_id = ? LIMIT 1";
$tech_stmt = mysqli_prepare($conn, $tech_query);
mysqli_stmt_bind_param($tech_stmt, 'i', $TECH_ID);
mysqli_stmt_execute($tech_stmt);
$tech_result = mysqli_stmt_get_result($tech_stmt);
$tech = mysqli_fetch_assoc($tech_result);
mysqli_stmt_close($tech_stmt);

// Fetch completed jobs
$completed_query = "SELECT 
    sr.request_id,
    sr.appliance_type,
    sr.status,
    sr.scheduled_date,
    sr.created_at,
    c.name AS customer_name,
    c.phone_number AS customer_phone,
    c.address AS customer_address,
    p.payment_status,
    p.amount,
    (SELECT sl.service_type FROM service_line sl WHERE sl.request_id = sr.request_id LIMIT 1) AS service_type,
    (SELECT GROUP_CONCAT(s.service_name SEPARATOR ', ') 
     FROM service_line sl 
     INNER JOIN services s ON sl.service_id = s.service_id 
     WHERE sl.request_id = sr.request_id) AS services_list,
    (SELECT jt.updated_at FROM job_tracking jt 
     WHERE jt.request_id = sr.request_id AND jt.status = 'completed' 
     ORDER BY jt.updated_at DESC LIMIT 1) AS completion_date
  FROM service_request sr
  INNER JOIN customer c ON sr.customer_id = c.customer_id
  LEFT JOIN payment p ON sr.request_id = p.request_id
  WHERE sr.technician_id = ? AND sr.status = 'completed'
  ORDER BY sr.created_at DESC";

$completed_stmt = mysqli_prepare($conn, $completed_query);
mysqli_stmt_bind_param($completed_stmt, 'i', $TECH_ID);
mysqli_stmt_execute($completed_stmt);
$completed_result = mysqli_stmt_get_result($completed_stmt);
$completed_jobs = [];
while ($row = mysqli_fetch_assoc($completed_result)) {
  $completed_jobs[] = $row;
}
mysqli_stmt_close($completed_stmt);

// Calculate statistics
$total_completed = count($completed_jobs);
$total_revenue = 0;
$this_month_completed = 0;
$current_month = date('Y-m');

foreach ($completed_jobs as $job) {
  $total_revenue += (float)$job['amount'];
  if ($job['completion_date'] && strpos($job['completion_date'], $current_month) === 0) {
    $this_month_completed++;
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Work History - Technician Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --coastal-light: #447794;
      --coastal-mid: #2D5B75;
      --coastal-dark: #123249;
      --coastal-midnight: #061222;
      --bg: #f5f7fa;
      --card: #ffffff;
      --line: #e2e8f0;
      --ink: #1a202c;
      --muted: #64748b;
      --success: #10b981;
    }
    
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
      color: var(--ink);
      line-height: 1.6;
      min-height: 100vh;
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .header {
      background: linear-gradient(135deg, var(--coastal-midnight) 0%, var(--coastal-dark) 100%);
      border-radius: 12px;
      padding: 28px;
      margin-bottom: 24px;
      box-shadow: 0 4px 12px rgba(6, 18, 34, 0.15);
      color: white;
    }
    
    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }
    
    .header-title {
      font-size: 28px;
      font-weight: 700;
      color: white;
    }
    
    .header-subtitle {
      color: rgba(255, 255, 255, 0.8);
      font-size: 14px;
      margin-top: 6px;
    }
    
    .btn {
      display: inline-block;
      padding: 10px 20px;
      border-radius: 8px;
      border: none;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s;
    }
    
    .btn-outline {
      background: rgba(255, 255, 255, 0.1);
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .btn-outline:hover {
      background: rgba(255, 255, 255, 0.2);
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    
    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      border-left: 4px solid var(--coastal-light);
    }
    
    .stat-label {
      color: var(--muted);
      font-size: 13px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 10px;
    }
    
    .stat-value {
      font-size: 36px;
      font-weight: 700;
      background: linear-gradient(135deg, var(--coastal-light) 0%, var(--coastal-mid) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .content-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }
    
    .content-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--coastal-midnight);
    }
    
    .job-card {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 18px;
      margin-bottom: 14px;
      transition: all 0.2s;
      background: white;
    }
    
    .job-card:hover {
      box-shadow: 0 4px 12px rgba(68, 119, 148, 0.12);
      border-color: var(--coastal-light);
    }
    
    .job-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      gap: 12px;
      margin-bottom: 14px;
    }
    
    .job-title {
      font-size: 17px;
      font-weight: 600;
      color: var(--coastal-midnight);
    }
    
    .job-id {
      color: var(--muted);
      font-size: 13px;
      margin-top: 2px;
    }
    
    .job-badges {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    
    .badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .badge-completed {
      background: #d1fae5;
      color: #065f46;
    }
    
    .badge-house {
      background: linear-gradient(135deg, var(--coastal-light) 0%, var(--coastal-mid) 100%);
      color: white;
    }
    
    .badge-shop {
      background: #ffedd5;
      color: #9a3412;
    }
    
    .job-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 14px;
      padding: 14px;
      background: #f8fafc;
      border-radius: 6px;
      margin-bottom: 14px;
    }
    
    .detail-item {
      font-size: 14px;
    }
    
    .detail-label {
      color: var(--muted);
      font-weight: 600;
      display: block;
      margin-bottom: 3px;
      font-size: 12px;
    }
    
    .detail-value {
      color: var(--ink);
    }
    
    .services-info {
      background: linear-gradient(135deg, #f0f4f8 0%, #e8eef5 100%);
      padding: 12px;
      border-radius: 6px;
      font-size: 14px;
      color: var(--ink);
      margin-bottom: 14px;
      border-left: 3px solid var(--coastal-light);
    }
    
    .job-actions {
      display: flex;
      gap: 8px;
    }
    
    .btn-sm {
      padding: 8px 16px;
      font-size: 13px;
    }
    
    .btn-primary {
      background: var(--coastal-light);
      color: white;
    }
    
    .btn-primary:hover {
      background: var(--coastal-mid);
      transform: translateY(-1px);
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
    }
    
    .empty-state::before {
      content: "✅";
      display: block;
      font-size: 48px;
      margin-bottom: 16px;
      opacity: 0.5;
    }
    
    .alert {
      padding: 14px 18px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      border-left: 4px solid;
    }
    
    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border-color: #10b981;
    }
    
    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .job-details {
        grid-template-columns: 1fr;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        ✓ <?php echo htmlspecialchars($_GET['success']); ?>
      </div>
    <?php endif; ?>
    
    <div class="header">
      <div class="header-content">
        <div>
          <h1 class="header-title">Work History 📋</h1>
          <div class="header-subtitle">Your completed jobs and performance metrics</div>
        </div>
        <a href="technician-dashboard.php" class="btn btn-outline">← Back to Dashboard</a>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Completed</div>
        <div class="stat-value"><?php echo $total_completed; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">This Month</div>
        <div class="stat-value"><?php echo $this_month_completed; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value">₱<?php echo number_format($total_revenue, 0); ?></div>
      </div>
    </div>

    <div class="content-card">
      <h2 class="content-title">Completed Jobs</h2>
      
      <?php if (empty($completed_jobs)): ?>
        <div class="empty-state">
          <p>No completed jobs yet. Keep up the great work!</p>
        </div>
      <?php else: ?>
        <?php foreach ($completed_jobs as $job): ?>
          <div class="job-card">
            <div class="job-header">
              <div>
                <div class="job-title"><?php echo htmlspecialchars($job['appliance_type']); ?></div>
                <div class="job-id">Request #<?php echo $job['request_id']; ?></div>
              </div>
              <div class="job-badges">
                <span class="badge badge-completed">✓ Completed</span>
                <?php if (!empty($job['service_type'])): ?>
                  <span class="badge badge-<?php echo $job['service_type'] === 'house-to-house' ? 'house' : 'shop'; ?>">
                    <?php echo $job['service_type'] === 'house-to-house' ? 'House-to-House' : 'In-Shop'; ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
            
            <div class="job-details">
              <div class="detail-item">
                <span class="detail-label">Customer</span>
                <span class="detail-value"><?php echo htmlspecialchars($job['customer_name']); ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?php echo htmlspecialchars($job['customer_phone'] ?? 'N/A'); ?></span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Completed On</span>
                <span class="detail-value">
                  <?php echo $job['completion_date'] ? date('M d, Y', strtotime($job['completion_date'])) : date('M d, Y', strtotime($job['created_at'])); ?>
                </span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Amount</span>
                <span class="detail-value">₱<?php echo number_format($job['amount'], 2); ?></span>
              </div>
            </div>
            
            <?php if ($job['services_list']): ?>
              <div class="services-info">
                <strong>Services:</strong> <?php echo htmlspecialchars($job['services_list']); ?>
              </div>
            <?php endif; ?>
            
            <div class="job-actions">
              <a href="technician-job-detail.php?id=<?php echo $job['request_id']; ?>" class="btn btn-primary btn-sm">
                View Details
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>