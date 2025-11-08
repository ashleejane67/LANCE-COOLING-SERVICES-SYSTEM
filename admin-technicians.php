<?php
// --- minimal session/compat shim (no changes to your logic/UI) ---
if (session_status() !== PHP_SESSION_ACTIVE) {
  // Make sure the cookie works site-wide
  if (function_exists('session_set_cookie_params')) {
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
  }
  session_start();
}

// Ensure DB connection is available (safe if already included elsewhere)
if (!isset($conn)) { @include_once 'db.php'; }

/*
  Some parts of your app set role as 'staff_role', others as 'role'.
  Without altering your guard, map the value ONCE if needed so your
  existing check sees admin and won't redirect.
*/
if (!isset($_SESSION['staff_role']) && isset($_SESSION['role'])) {
  $_SESSION['staff_role'] = $_SESSION['role'];
}

// If your login stored a truthy flag like is_admin, bridge it to staff_role
if (!isset($_SESSION['staff_role']) && !empty($_SESSION['is_admin'])) {
  $_SESSION['staff_role'] = 'admin';
}
// --- end shim ---

// Initialize variables
$msg = '';
$edit = null;
$rows = [];

// Check for messages from save/delete operations
if (isset($_GET['msg'])) {
  $msg = $_GET['msg'];
}

// Check if editing a specific technician
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
  $tech_id = (int)$_GET['id'];
  $stmt = mysqli_prepare($conn, "SELECT * FROM technician WHERE technician_id = ?");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tech_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
  }
}

// Fetch all technicians
$query = "SELECT * FROM technician ORDER BY name ASC";
$result = mysqli_query($conn, $query);
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }
}
?>

<?php include 'head.php'; ?>

<section class="section">
  <div class="container">
    <?php if ($msg): ?>
      <div class="card" style="background:#eafcf3;border:1px solid #bde7cf;padding:10px;border-radius:12px;margin-bottom:12px;">
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <div class="card" style="padding:16px;margin-bottom:16px;">
      <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;">
        <h2 style="margin:0;">Technicians</h2>
        <div class="btn-row">
          <a class="btn btn-ghost" href="admin-dashboard.php">Back to Dashboard</a>
          <a class="btn btn-ghost" href="logout.php">Log out</a>
        </div>
      </div>
    </div>

    <div class="card" style="padding:16px;margin-bottom:16px;">
      <h3 style="margin:0 0 8px;">Add / Edit Technician</h3>
      <form method="post" action="admin-technicians-save.php" class="form" style="display:grid;gap:10px;">
        <input type="hidden" name="technician_id" value="<?= $edit ? (int)$edit['technician_id'] : 0 ?>">

        <div style="display:grid;gap:6px;grid-template-columns:repeat(2,1fr);">
          <label>
            <div class="small muted">Name</div>
            <input class="input" type="text" name="name" required
                   value="<?= $edit ? htmlspecialchars($edit['name']) : '' ?>">
          </label>

          <label>
            <div class="small muted">Email</div>
            <input class="input" type="email" name="email"
                   value="<?= $edit ? htmlspecialchars($edit['email']) : '' ?>">
          </label>

          <label>
            <div class="small muted">Phone</div>
            <input class="input" type="text" name="phone_number"
                   value="<?= $edit ? htmlspecialchars($edit['phone_number']) : '' ?>">
          </label>

          <label>
            <div class="small muted">Position</div>
            <input class="input" type="text" name="position" placeholder="e.g., Senior Tech"
                   value="<?= $edit ? htmlspecialchars($edit['position']) : '' ?>">
          </label>

          <label>
            <div class="small muted">Username</div>
            <input class="input" type="text" name="username" required
                   value="<?= $edit ? htmlspecialchars($edit['username']) : '' ?>">
          </label>

          <label>
            <div class="small muted">
              Password <?= $edit ? '<span class="muted small">(leave blank to keep existing)</span>' : '' ?>
            </div>
            <input class="input" type="password" name="password" <?= $edit ? '' : 'required' ?>>
          </label>
        </div>

        <div class="btn-row" style="margin-top:6px;">
          <button class="btn btn-primary" type="submit">Save</button>
          <a class="btn btn-ghost" href="admin-technicians.php">Clear</a>
        </div>
      </form>
    </div>

    <div class="card" style="padding:16px;">
      <h3 style="margin:0 0 8px;">All Technicians</h3>

      <?php if (empty($rows)): ?>
        <p class="muted small">No technicians yet.</p>
      <?php else: ?>
        <div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px;">
          <?php foreach ($rows as $t): ?>
            <div class="card" style="padding:12px;">
              <div class="small muted">ID: #<?= (int)$t['technician_id'] ?></div>
              <h4 style="margin:.25rem 0 .35rem;"><?= htmlspecialchars($t['name']) ?></h4>
              <div class="small">Position: <?= htmlspecialchars($t['position'] ?? 'N/A') ?></div>
              <div class="small">User: <?= htmlspecialchars($t['username']) ?></div>
              <?php if (!empty($t['email'])): ?><div class="small">Email: <?= htmlspecialchars($t['email']) ?></div><?php endif; ?>
              <?php if (!empty($t['phone_number'])): ?><div class="small">Phone: <?= htmlspecialchars($t['phone_number']) ?></div><?php endif; ?>
              <div class="small muted">Status: <?= htmlspecialchars($t['status'] ?? 'available') ?></div>
              <div class="btn-row" style="margin-top:8px;">
                <a class="btn btn-ghost" href="admin-technicians.php?id=<?= (int)$t['technician_id'] ?>">Edit</a>
                <a class="btn btn-ghost" href="admin-technicians-delete.php?id=<?= (int)$t['technician_id'] ?>"
                   onclick="return confirm('Delete this technician?');">Delete</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>