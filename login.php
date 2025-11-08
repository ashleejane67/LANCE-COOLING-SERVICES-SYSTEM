<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'head.php';
?>
<section class="section">
  <div class="container" style="max-width:960px;">
    <div class="card" style="max-width:480px;margin:0 auto;padding:18px;">
      <div class="center" style="margin-bottom:10px;">
        <img src="assets/logo.jpg" alt="LANCE" style="width:62px;height:62px;object-fit:contain;border-radius:50%;border:2px solid var(--b700);background:#fff;">
      </div>
      <h2 class="center" style="margin:0 0 6px;">Customer Sign In</h2>
      <p class="small muted center" style="margin-top:-2px">Welcome back to LANCE</p>

      <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="card" style="background:#fff4f4;border:1px solid #ffd1d1;color:#7a0c0c;margin:.5rem 0;padding:.6rem;border-radius:10px;">
          <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
      <?php endif; ?>

      <form method="post" action="login_process.php" class="form">
        <div class="form-row" style="display:grid;gap:8px;margin-bottom:10px;">
          <label>Email</label>
          <input class="input" type="email" name="email" required>
        </div>
        <div class="form-row" style="display:grid;gap:8px;margin-bottom:14px;">
          <label>Password</label>
          <input class="input" type="password" name="password" required>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%;">Sign In</button>
      </form>

      <p class="small muted center" style="margin-top:10px;">
        Don't have an account? <a href="register.php" class="auth">Create one</a>
      </p>
    </div>
  </div>
</section>
<?php include 'footer.php'; ?>