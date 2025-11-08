<?php
// Keep sessions lightweight + available for the auth links
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Get the URL fragment/hash if accessing via anchor
$current_section = isset($_GET['section']) ? $_GET['section'] : '';

// Function to check if link is active
function isActive($page, $section = '') {
    global $current_page, $current_section;
    
    // Check if we're on the specific page
    if ($page === $current_page) {
        // If no section specified, it's active
        if (empty($section)) return true;
        // If section matches, it's active
        if ($section === $current_section) return true;
    }
    
    return false;
}
?>
<nav class="nav">
  <div class="container">
    <div class="nav__row">

      <!-- Brand (logo left; your CSS already makes it flush-left) -->
      <a href="index.php" class="brand" aria-label="LANCE Home">
        <img src="assets/logo.jpg" alt="LANCE" class="brand__img">
        <span class="brand__text">LANCE Refrigeration &amp; Airconditioning</span>
      </a>

      <!-- Centered menu (anchors stay on this landing page) -->
      <ul class="menu" role="menubar">
        <li role="none">
          <a role="menuitem" 
             href="index.php#home" 
             class="<?= isActive('index.php') || $current_page === 'index.php' ? 'active' : '' ?>"
             data-section="home">
            Home
          </a>
        </li>
        <li role="none">
          <a role="menuitem" 
             href="index.php#services" 
             class="<?= $current_section === 'services' ? 'active' : '' ?>"
             data-section="services">
            Services
          </a>
        </li>
        <li role="none">
          <a role="menuitem" 
             href="index.php#why" 
             class="<?= $current_section === 'why' ? 'active' : '' ?>"
             data-section="why">
            Why Us
          </a>
        </li>

        <?php if (empty($_SESSION['customer_id'])): ?>
          <li role="none">
            <a role="menuitem" 
               href="login.php"
               class="<?= isActive('login.php') || isActive('register.php') ? 'active' : '' ?>">
              Log In / Register
            </a>
          </li>
        <?php else: ?>
          <li role="none">
            <a role="menuitem" 
               href="customer-dashboard.php"
               class="<?= isActive('customer-dashboard.php') ? 'active' : '' ?>">
              Dashboard
            </a>
          </li>
        <?php endif; ?>

        <li role="none"><span class="dot"></span></li>
        
        <li role="none">
          <a role="menuitem" 
             href="staff-login.php"
             class="<?= isActive('staff-login.php') || isActive('staff-dashboard.php') ? 'active' : '' ?>">
            Staff Login
          </a>
        </li>
        <li role="none">
          <a role="menuitem" 
             href="index.php#contact" 
             class="<?= $current_section === 'contact' ? 'active' : '' ?>"
             data-section="contact">
            Contact
          </a>
        </li>
      </ul>

      <!-- Right side (call button) -->
      <div class="nav__right">
        <a class="call" href="tel:2250448">Call Us</a>
      </div>

      <!-- (Optional) mobile hamburger if you already use it in your HTML/CSS -->
      <!--
      <input type="checkbox" id="menu-toggle" hidden>
      <label for="menu-toggle" class="hamburger" aria-label="Open menu">â˜°</label>
      -->
    </div>
  </div>
</nav>

<!-- JavaScript for handling hash/section highlighting (only on index.php) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on index.php
    const currentPage = window.location.pathname.split('/').pop();
    const isIndexPage = !currentPage || currentPage === 'index.php' || currentPage === '';
    
    // Only run section-based logic on index.php
    if (isIndexPage) {
        // Function to update active state based on scroll position or hash
        function updateActiveLink() {
            const hash = window.location.hash.substring(1); // Remove the #
            
            // Remove active class from all menu links
            document.querySelectorAll('.menu a').forEach(link => {
                link.classList.remove('active');
            });
            
            // Add active class to matching link
            if (hash) {
                const activeLink = document.querySelector(`.menu a[data-section="${hash}"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                }
            } else {
                // If no hash, activate Home
                const homeLink = document.querySelector('.menu a[data-section="home"]');
                if (homeLink) {
                    homeLink.classList.add('active');
                }
            }
        }
        
        // Update on page load
        updateActiveLink();
        
        // Update when hash changes
        window.addEventListener('hashchange', updateActiveLink);
        
        // Update based on scroll position (intersection observer)
        const sections = document.querySelectorAll('section[id]');
        
        if (sections.length > 0) {
            const observerOptions = {
                root: null,
                rootMargin: '-50% 0px -50% 0px',
                threshold: 0
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const sectionId = entry.target.getAttribute('id');
                        
                        // Update URL hash without scrolling
                        if (window.location.hash !== '#' + sectionId) {
                            history.replaceState(null, null, '#' + sectionId);
                            updateActiveLink();
                        }
                    }
                });
            }, observerOptions);
            
            sections.forEach(section => observer.observe(section));
        }
        
        // Handle clicks on menu links (section anchors)
        document.querySelectorAll('.menu a[data-section]').forEach(link => {
            link.addEventListener('click', function(e) {
                const section = this.getAttribute('data-section');
                
                // Remove active from all
                document.querySelectorAll('.menu a').forEach(l => {
                    l.classList.remove('active');
                });
                
                // Add active to clicked
                this.classList.add('active');
                
                // Smooth scroll to section
                const targetSection = document.getElementById(section);
                if (targetSection) {
                    e.preventDefault();
                    targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    // Update hash after scroll
                    setTimeout(() => {
                        window.location.hash = section;
                    }, 100);
                }
            });
        });
    }
    // On other pages (login, staff-login, dashboard), let all links work normally
    // The PHP active class will handle the highlighting
});
</script>