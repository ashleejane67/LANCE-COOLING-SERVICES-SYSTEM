<?php
require_once 'db.php';
require_once 'admin-header.inc.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get filter and search parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause based on filter
$where_clause = "";
if ($filter === 'pending') {
  $where_clause = " AND r.status = 'pending'";
} elseif ($filter === 'in_progress') {
  $where_clause = " AND (r.status = 'in_progress' OR r.status = 'scheduled')";
} elseif ($filter === 'completed') {
  $where_clause = " AND r.status = 'completed'";
}

// Add search condition
if (!empty($search)) {
  $search_escaped = mysqli_real_escape_string($conn, $search);
  $where_clause .= " AND (r.request_id LIKE '%{$search_escaped}%' 
                     OR c.name LIKE '%{$search_escaped}%' 
                     OR c.email LIKE '%{$search_escaped}%'
                     OR r.appliance_type LIKE '%{$search_escaped}%')";
}

// Query to get requests
$sql = "SELECT r.request_id, r.appliance_type, r.status AS request_status, r.scheduled_date,
       r.technician_id, r.created_at, 
       c.name AS customer_name, c.email AS customer_email, c.phone_number, c.address,
       t.name AS technician_name
FROM service_request r
INNER JOIN customer c ON c.customer_id = r.customer_id
LEFT JOIN technician t ON t.technician_id = r.technician_id
WHERE 1=1 $where_clause
ORDER BY r.created_at DESC";

$rows = mysqli_query($conn, $sql);

// Check for errors
if (!$rows) {
    die("SQL Error: " . mysqli_error($conn));
}

$request_count = mysqli_num_rows($rows);

// Get requests with service details
$requests_with_services = [];
if ($request_count > 0) {
    while($r = mysqli_fetch_assoc($rows)) {
        // Get service type
        $service_query = "SELECT service_type FROM service_line WHERE request_id = " . $r['request_id'] . " LIMIT 1";
        $service_result = mysqli_query($conn, $service_query);
        $service_data = mysqli_fetch_assoc($service_result);
        
        $r['service_type'] = $service_data ? $service_data['service_type'] : '';
        
        $requests_with_services[] = $r;
    }
}

// Get success/error messages
$success_msg = isset($_GET['success']) ? $_GET['success'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>
<?php include 'head.php'; ?>
<link rel="stylesheet" href="styles.css">
<style>
  .request-row {
    background: white;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: box-shadow 0.2s ease;
  }
  .request-row:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  .request-left {
    flex: 1;
  }
  .request-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
  }
  .request-title a {
    font-size: 16px;
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
  }
  .request-title a:hover {
    text-decoration: underline;
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
  .request-meta {
    font-size: 13px;
    color: var(--muted);
  }
  .request-meta span {
    margin-right: 16px;
  }
  .filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
  }
  .filter-tab {
    padding: 8px 16px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #e5e7eb;
    color: #4b5563;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
    font-weight: 500;
  }
  .filter-tab:hover {
    background: #d1d5db;
    color: #1f2937;
    border-color: #9ca3af;
  }
  .filter-tab.active {
    background: #3b82f6 !important;
    color: white !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
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
  .search-box {
    display: flex;
    gap: 8px;
    align-items: center;
  }
  .search-box input {
    flex: 1;
    min-width: 250px;
  }
</style>

<section class="section">
  <div class="container">
    <div class="card" style="padding:16px;margin-bottom:16px;">
      <h2 style="margin:0 0 8px;">Service Requests</h2>
      <div class="small muted">Click on any request to view details and manage assignments.</div>
      <div class="btn-row" style="justify-content:flex-start;margin-top:8px;">
        <a href="admin-dashboard.php" class="btn btn-ghost">Back to Dashboard</a>
        <a href="admin-logout.php" class="btn btn-ghost">Log out</a>
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

    <div class="card" style="padding:16px;margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <!-- Filter Tabs -->
        <div class="filter-tabs" style="margin-bottom:0;">
          <a href="admin-requests.php" class="filter-tab <?= empty($filter) ? 'active' : '' ?>">All</a>
          <a href="admin-requests.php?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
          <a href="admin-requests.php?filter=in_progress" class="filter-tab <?= $filter === 'in_progress' ? 'active' : '' ?>">In Progress</a>
          <a href="admin-requests.php?filter=completed" class="filter-tab <?= $filter === 'completed' ? 'active' : '' ?>">Completed</a>
        </div>

        <!-- Search Box -->
        <form method="get" class="search-box">
          <?php if (!empty($filter)): ?>
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
          <?php endif; ?>
          <input type="text" 
                 name="search" 
                 class="input" 
                 placeholder="Search by request #, name, or appliance..." 
                 value="<?= htmlspecialchars($search) ?>"
                 style="width:300px;">
          <button type="submit" class="btn btn-primary">Search</button>
          <?php if (!empty($search)): ?>
            <a href="admin-requests.php<?= !empty($filter) ? '?filter=' . htmlspecialchars($filter) : '' ?>" 
               class="btn btn-ghost">Clear</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <?php if (!empty($search)): ?>
      <div class="card" style="padding:12px;margin-bottom:12px;background:#f0f9ff;border-color:#bae6fd;">
        <div class="small" style="color:#0c4a6e;">
          Showing results for: <strong><?= htmlspecialchars($search) ?></strong> (<?= $request_count ?> found)
        </div>
      </div>
    <?php endif; ?>

    <?php if (empty($requests_with_services)): ?>
      <div class="card" style="padding:20px;text-align:center;">
        <p class="small muted">
          <?php if (!empty($search)): ?>
            No requests found matching "<?= htmlspecialchars($search) ?>".
          <?php else: ?>
            No requests found for this filter.
          <?php endif; ?>
        </p>
      </div>
    <?php else: ?>
      <?php foreach($requests_with_services as $r): ?>
        <div class="request-row">
          <div class="request-left">
            <div class="request-title">
              <a href="admin-request-detail.php?id=<?= (int)$r['request_id'] ?>">
                Request #<?= (int)$r['request_id'] ?>
              </a>
              <span class="badge badge-<?= strtolower($r['request_status']) ?>">
                <?= ucfirst(str_replace('_', ' ', $r['request_status'])) ?>
              </span>
            </div>
            <div class="request-meta">
              <span><strong><?= htmlspecialchars($r['appliance_type']) ?></strong></span>
              <span><?= htmlspecialchars($r['customer_name']) ?></span>
              <span><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
              <?php if (!empty($r['technician_name'])): ?>
                <span>👤 <?= htmlspecialchars($r['technician_name']) ?></span>
              <?php else: ?>
                <span style="color: #ef4444;">⚠ Unassigned</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php include 'footer.php'; ?>