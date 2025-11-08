<section id="brands" class="section section--alt">
  <div class="container center">
    <h2 class="section__title">Trusted Brand Partners</h2>
    <p class="section__lead muted">We install and service leading manufacturers</p>

    <div class="pill-row">
      <?php foreach ($BRANDS as $b): ?>
        <span class="pill"><?= htmlspecialchars($b) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>
