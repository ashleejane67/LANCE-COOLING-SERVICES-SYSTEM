<?php
// Edit page: loads one technician by id and lets you update
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';

if (empty($_SESSION['is_logged_in']) || ($_SESSION['staff_role'] ?? '') !== 'admin') {
  header('Location: staff-login.php?error=' . urlencode('Please sign in as Admin to continue.'));
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: admin-technicians.php?msg=' . urlencode('Invalid technician id.'));
  exit;
}

$sql = "SELECT technician_id, username, name, email, phone_number, position
        FROM technician WHERE technician_id = ? LIMIT 1";
$edit = null;
if ($stmt = mysqli_prepare($conn, $sql)) {
  mysqli_stmt_bind_param($stmt, 'i', $id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  if ($res && mysqli_num_rows($res) === 1) {
    $edit = mysqli_fetch_assoc($res);
  }
  mysqli_stmt_close($stmt);
}
if (!$edit) {
  header('Location: admin-technicians.php?msg=' . urlencode('Technician not found.'));
  exit;
}

// Optional chrome
if (file_exists('head.php')) include 'head.php';
if (file_exists('navbar.php')) include 'navbar.php';
?>
<section class="section" style="padding-top:46px;padding-bottom:40px;">
  <div class="container" style="max-width:800px;margin:0 auto;">
    <div class="card" style="border:1px solid #e8eef7;border-radius:18px;padding:18px;box-shadow:0 12px 32px rgba(8,81,156,.10);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h2 style="margin:0;">Edit Technician</h2>
        <div style="display:flex;gap:10px;">
          <a class="btn" href="admin-technicians.php">Back to Technicians</a>
          <a class="btn" href="logout.php">Log out</a>
        </div>
      </div>

      <form method="post" action="admin-technicians-save.php" autocomplete="off">
        <input type="hidden" name="technician_id" value="<?= (int)$edit['technician_id'] ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <label>Name
            <input type="text" name="name" required style="width:100%;padding:8px;" value="<?= htmlspecialchars($edit['name']) ?>">
          </label>
          <label>Email
            <input type="email" name="email" style="width:100%;padding:8px;" value="<?= htmlspecialchars($edit['email']) ?>">
          </label>
          <label>Phone
            <input type="text" name="phone" style="width:100%;padding:8px;" value="<?= htmlspecialchars($edit['phone_number']) ?>">
          </label>
          <label>Position
            <input type="text" name="position" style="width:100%;padding:8px;" value="<?= htmlspecialchars($edit['position']) ?>">
          </label>
          <label>Username
            <input type="text" name="username" required style="width:100%;padding:8px;" value="<?= htmlspecialchars($edit['username']) ?>">
          </label>
          <label>New Password (leave blank to keep current)
            <input type="password" name="password" style="width:100%;padding:8px;">
          </label>
        </div>

        <div style="margin-top:12px;display:flex;gap:10px;">
          <button class="btn btn-primary" type="submit">Save Changes</button>
          <a class="btn" href="admin-technicians.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</section>
<?php if (file_exists('footer.php')) include 'footer.php'; ?>
