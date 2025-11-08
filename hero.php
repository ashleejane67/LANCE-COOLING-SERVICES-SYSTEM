<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<section id="home" class="hero">
  <div class="container">
    <div class="hero-row">

      <!-- Left column: copy -->
      <div class="hero-copy">
        <span class="badge">Trusted • Iligan City</span>
        <h1>LANCE | Cooling Solutions</h1>
        <p>“Be cool, stay chill — let Lance handle the drill.”<br>
           Trusted refrigeration &amp; air conditioning in Iligan City for 20+ years.</p>

        <div class="btn-row">
          <?php if (empty($_SESSION['customer_id'])): ?>
            <!-- Not signed in → go to login/register before booking -->
            <a href="login.php" class="btn btn-primary">Book Service</a>
          <?php else: ?>
            <!-- Signed in → go straight to booking form -->
            <a href="book.php" class="btn btn-primary">Book Service</a>
          <?php endif; ?>

          <!-- Keep “View Services” on this landing page -->
          <a href="index.php#services" class="btn btn-ghost">View Services</a>
        </div>
      </div>

      <!-- Right column: hero image (your CSS sizes this) -->
      <div class="hero-media">
        <!-- Use your actual image path; this is just a placeholder -->
        <img src="assets/first-page.jpg" alt="Technician servicing air conditioner">
      </div>

    </div>
  </div>
</section>
