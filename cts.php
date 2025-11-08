
<section id="contact" class="cts">
  <div class="container center">
    <h2 class="section__title text-on-dark">Ready to Get Started?</h2>
    <p class="section__lead text-on-dark">Contact us for professional cooling services</p>

    <div class="contact-grid">
      <div class="contact-card">
        <div class="contact-title">Call Us</div>
        <?php foreach ($PHONES as $p): ?>
          <div><?= htmlspecialchars($p) ?></div>
        <?php endforeach; ?>
      </div>

      <div class="contact-card">
        <div class="contact-title">Email Us</div>
        <div><?= htmlspecialchars($EMAIL) ?></div>
      </div>

      <div class="contact-card">
        <div class="contact-title">Visit Us</div>
        <div><?= htmlspecialchars($CITY) ?></div>
      </div>
    </div>

    <a href="tel:<?= preg_replace('/\D/','',$PHONES[0]) ?>" class="btn btn-light">Book Now</a>
  </div>
</section>
