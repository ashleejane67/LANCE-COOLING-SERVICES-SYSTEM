<?php
// auth-modal.php — reusable popup
if (session_status() === PHP_SESSION_NONE) session_start();

$open = (isset($_GET['auth']) && $_GET['auth'] === 'open');
$tab  = ($_GET['tab'] ?? 'login');
$err  = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>
<div class="auth-overlay <?php echo $open ? 'show' : '' ?>" id="authOverlay" aria-hidden="<?php echo $open ? 'false' : 'true'; ?>">
  <div class="auth-card" role="dialog" aria-modal="true" aria-labelledby="authTitle">
    <button class="auth-close" id="authClose" aria-label="Close">×</button>

    <div class="auth-header">
      <img src="assets/logo.jpg" alt="LANCE logo" class="auth-logo">
      <h3 id="authTitle">Welcome</h3>
      <p class="muted small">Sign in or create your account to book a service.</p>
    </div>

    <?php if ($err): ?>
      <div class="auth-alert"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <div class="auth-tabs">
      <button class="auth-tab <?php echo ($tab==='login' ? 'active':'') ?>" data-tab="login">Sign in</button>
      <button class="auth-tab <?php echo ($tab==='register' ? 'active':'') ?>" data-tab="register">Create account</button>
    </div>

    <div class="auth-body">
      <!-- LOGIN -->
      <form class="auth-panel <?php echo ($tab==='login' ? 'show':'') ?>" method="post" action="auth-handler.php" autocomplete="on">
        <input type="hidden" name="action" value="login">
        <label>Email</label>
        <input type="email" name="email" required placeholder="you@email.com">
        <label>Password</label>
        <input type="password" name="password" required placeholder="••••••••">
        <button class="btn btn-primary w-100">Sign in</button>
      </form>

      <!-- REGISTER -->
      <form class="auth-panel <?php echo ($tab==='register' ? 'show':'') ?>" method="post" action="auth-handler.php" autocomplete="on">
        <input type="hidden" name="action" value="register">
        <label>Full name</label>
        <input type="text" name="name" required placeholder="Juan Dela Cruz">
        <label>Email</label>
        <input type="email" name="email" required placeholder="you@email.com">
        <label>Password</label>
        <input type="password" name="password" required minlength="6" placeholder="At least 6 characters">
        <button class="btn btn-primary w-100">Create account</button>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const overlay = document.getElementById('authOverlay');
  const closeBtn = document.getElementById('authClose');
  const openers  = document.querySelectorAll('[data-auth="open"]');
  const tabs     = document.querySelectorAll('.auth-tab');
  const panels   = document.querySelectorAll('.auth-panel');

  openers.forEach(btn => btn.addEventListener('click', function(e){
    e.preventDefault();
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden','false');
  }));

  closeBtn && closeBtn.addEventListener('click', function(){
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden','true');
  });

  tabs.forEach(t => t.addEventListener('click', function(){
    tabs.forEach(x => x.classList.remove('active'));
    panels.forEach(p => p.classList.remove('show'));
    this.classList.add('active');
    document.querySelector('.auth-panel.'+this.dataset.tab)?.classList.add('show');

    // keep the URL in sync (so refresh stays on the same tab)
    const q = new URLSearchParams(location.search);
    q.set('auth','open'); q.set('tab', this.dataset.tab);
    history.replaceState(null,'', location.pathname + '?' + q.toString());
  }));

  // close on backdrop click
  overlay?.addEventListener('click', (e)=>{ if(e.target===overlay){ closeBtn.click(); }});
})();
</script>
