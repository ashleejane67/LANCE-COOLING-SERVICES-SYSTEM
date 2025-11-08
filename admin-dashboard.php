<?php
require_once 'db.php';
require_once 'admin-header.inc.php';

// Quick counts
$tot_req = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM service_request"))[0] ?? 0;
$pending = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM service_request WHERE status='pending'"))[0] ?? 0;
$inprog  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM service_request WHERE status='in_progress' OR status='scheduled'"))[0] ?? 0;
$done    = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM service_request WHERE status='completed'"))[0] ?? 0;
$techs   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM technician"))[0] ?? 0;
$pending_payments = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM payment WHERE payment_status='pending'"))[0] ?? 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard - LANCE</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body {
      background: var(--b50);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .logo-container {
      text-align: center;
      margin-bottom: 32px;
    }
    
    .logo-container img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      background: #fff;
      border: 3px solid var(--b700);
      margin: 0 auto 12px;
    }
    
    .logo-container h1 {
      font-size: 24px;
      font-weight: 700;
      color: var(--ink);
      margin: 0 0 4px;
    }
    
    .logo-container p {
      font-size: 14px;
      color: var(--muted);
      margin: 0;
    }
    
    .dashboard-container {
      max-width: 1100px;
      width: 100%;
    }
    
    .dashboard-header {
      background: white;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 24px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .dashboard-header h2 {
      margin: 0 0 16px;
      font-size: 28px;
      font-weight: 800;
      color: var(--ink);
    }
    
    .btn-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    
    .btn {
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.2s ease;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-primary {
      background: var(--b700);
      color: white;
      border: none;
    }
    
    .btn-primary:hover {
      background: var(--b500);
      transform: translateY(-1px);
    }
    
    .btn-ghost {
      background: transparent;
      color: var(--b700);
      border: 2px solid var(--b700);
    }
    
    .btn-ghost:hover {
      background: var(--b100);
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }
    
    .stat-card {
      background: white;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 24px;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
      color: inherit;
      display: block;
    }
    
    .stat-card:hover {
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
      transform: translateY(-4px);
    }
    
    .stat-card h4 {
      margin: 0 0 12px;
      font-size: 14px;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .stat-value {
      font-size: 48px;
      font-weight: 800;
      line-height: 1;
      margin: 0;
    }
    
    .stat-value { color: var(--ink); }
    
    .notification-badge {
      background: #ef4444;
      color: white;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 700;
      margin-left: 6px;
    }
    
    @media (max-width: 960px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    
    @media (max-width: 600px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .btn-row {
        flex-direction: column;
      }
      
      .btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>

  <div class="dashboard-container">
    <div class="dashboard-header">
      <h2>Admin Dashboard</h2>
      <div class="btn-row">
        <a class="btn btn-primary" href="admin-requests.php">Requests</a>
        <a class="btn btn-ghost" href="admin-technicians.php">Technicians</a>
        <a class="btn btn-ghost" href="admin-payments.php">
          Payments
          <?php if ($pending_payments > 0): ?>
            <span class="notification-badge"><?= $pending_payments ?></span>
          <?php endif; ?>
        </a>
        <a class="btn btn-ghost" href="admin-logout.php">Log out</a>
      </div>
    </div>

    <div class="stats-grid">
      <a href="admin-requests.php" class="stat-card">
        <h4>Total Requests</h4>
        <div class="stat-value"><?= (int)$tot_req ?></div>
      </a>

      <a href="admin-requests.php?filter=pending" class="stat-card">
        <h4>Pending</h4>
        <div class="stat-value"><?= (int)$pending ?></div>
      </a>

      <a href="admin-requests.php?filter=in_progress" class="stat-card">
        <h4>In Progress / Scheduled</h4>
        <div class="stat-value"><?= (int)$inprog ?></div>
      </a>

      <a href="admin-requests.php?filter=completed" class="stat-card">
        <h4>Completed</h4>
        <div class="stat-value"><?= (int)$done ?></div>
      </a>

      <a href="admin-technicians.php" class="stat-card">
        <h4>Technicians</h4>
        <div class="stat-value"><?= (int)$techs ?></div>
      </a>

      <a href="admin-payments.php?filter=pending" class="stat-card">
        <h4>Pending Payments</h4>
        <div class="stat-value">
          <?= (int)$pending_payments ?>
          <?php if ($pending_payments > 0): ?>
            <span class="notification-badge" style="font-size:12px;margin-left:8px;">NEW</span>
          <?php endif; ?>
        </div>
      </a>
    </div>
  </div>

</body>
</html>