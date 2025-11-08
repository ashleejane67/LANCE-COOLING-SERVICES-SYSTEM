<?php
require_once 'db.php';
if (empty($_SESSION['customer_id'])) { header('Location: login.php'); exit; }

// Fetch main service categories
$services_query = "SELECT DISTINCT category FROM services WHERE category IN ('cleaning', 'repair', 'installation', 'maintenance') ORDER BY category";
$services_result = mysqli_query($conn, $services_query);
$categories = [];
while ($row = mysqli_fetch_assoc($services_result)) {
  $categories[] = $row['category'];
}
?>
<?php include 'head.php'; ?>

<section class="section">
  <div class="container">
    
    <div class="card" style="padding:16px;margin-bottom:12px;">
      <h2 style="margin:0 0 4px;">Book a Service</h2>
      <div class="small muted">Fill in the details below</div>
    </div>

    <?php if (isset($_GET['error'])): ?>
      <div class="card" style="background:#fee;border:1px solid #fcc;padding:10px;margin-bottom:12px;">
        ✗ <?= htmlspecialchars($_GET['error']) ?>
      </div>
    <?php endif; ?>

    <div class="card" style="padding:16px;">
      <form method="post" action="book_process.php" id="bookingForm">
        
        <div style="margin-bottom:16px;">
          <h3 style="margin:0 0 8px;">Service Category</h3>
          <p class="small muted" style="margin-bottom:8px;">Choose the type of service</p>
          <div style="display:grid;gap:6px;">
            <?php foreach ($categories as $cat): ?>
              <label class="card" style="padding:10px;cursor:pointer;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="service_categories[]" value="<?= $cat ?>" 
                       class="category-checkbox" data-category="<?= $cat ?>">
                <span><?= ucfirst($cat) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Services Selection (Dynamic) -->
        <div id="services-container" style="display:none;margin-bottom:16px;">
          <h3 style="margin:0 0 8px;">Select Services</h3>
          <div id="services-list"></div>
          <div id="selected-services" style="display:none;margin-top:10px;padding:10px;background:#f9fafb;border-radius:6px;">
            <strong class="small">Selected:</strong>
            <ul id="selected-list" style="margin:4px 0 0;padding-left:20px;"></ul>
          </div>
          <div id="total-cost" style="display:none;margin-top:10px;padding:10px;background:#447794;color:white;border-radius:6px;text-align:center;font-weight:600;">
            Total: ₱<span id="cost-amount">0.00</span>
          </div>
        </div>

        <div style="margin-bottom:16px;">
          <h3 style="margin:0 0 8px;">Service Type</h3>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;">
            <label class="card" style="padding:10px;cursor:pointer;display:flex;align-items:center;gap:8px;">
              <input type="radio" name="service_type" value="house-to-house" required id="house-to-house">
              <span>House-to-House</span>
            </label>
            <label class="card" style="padding:10px;cursor:pointer;display:flex;align-items:center;gap:8px;">
              <input type="radio" name="service_type" value="in-shop" id="in-shop">
              <span>In-Shop</span>
            </label>
          </div>
        </div>

        <div style="margin-bottom:16px;">
          <h3 style="margin:0 0 8px;">Contact Information</h3>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
            <label>
              <div class="small muted">Phone Number</div>
              <input class="input" type="text" name="phone" required placeholder="09XX XXX XXXX">
            </label>
            <label id="address-field" style="display:none;">
              <div class="small muted">Address</div>
              <input class="input" type="text" name="address" id="address-input" placeholder="Street, Barangay, City">
            </label>
          </div>
        </div>

        <div style="margin-bottom:16px;">
          <h3 style="margin:0 0 8px;">Appliance Information</h3>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:10px;">
            <label>
              <div class="small muted">Appliance Type</div>
              <select class="input" name="appliance_type" required>
                <option value="">Select appliance</option>
                <option>Air Conditioner</option>
                <option>Refrigerator</option>
                <option>Freezer</option>
                <option>Washing Machine</option>
                <option>Dryer</option>
                <option>Other</option>
              </select>
            </label>
            <label>
              <div class="small muted">Urgency Level</div>
              <select class="input" name="urgency">
                <option>Normal</option>
                <option>Emergency</option>
              </select>
            </label>
          </div>
          <label>
            <div class="small muted">Problem Description</div>
            <textarea class="input" name="problem" required placeholder="Describe the problem..." style="min-height:80px;resize:vertical;font-family:inherit;"></textarea>
          </label>
        </div>

        <div style="margin-bottom:16px;">
          <h3 style="margin:0 0 8px;">Preferred Schedule</h3>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
            <label>
              <div class="small muted">Date</div>
              <input class="input" type="date" name="pref_date" required min="<?= date('Y-m-d') ?>">
            </label>
            <label>
              <div class="small muted">Time Slot</div>
              <select class="input" name="time_slot" required>
                <option value="">Select time</option>
                <option>08:00 AM – 10:00 AM</option>
                <option>10:00 AM – 12:00 NN</option>
                <option>01:00 PM – 03:00 PM</option>
                <option>03:00 PM – 05:00 PM</option>
              </select>
            </label>
          </div>
        </div>

        <div class="btn-row" style="padding-top:12px;border-top:1px solid #ddd;">
          <button type="submit" class="btn btn-primary" id="submitBtn">Submit Request</button>
          <a class="btn btn-ghost" href="customer-dashboard.php">Cancel</a>
        </div>

      </form>
    </div>
  </div>
</section>

<style>
input[type="radio"]:checked + span,
input[type="checkbox"]:checked + span {
  font-weight: 600;
}
label:has(input[type="radio"]:checked),
label:has(input[type="checkbox"]:checked) {
  background: #f0f4f8;
  border-color: #447794;
}
.service-item-card {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border: 1px solid #ddd;
  margin-bottom: 4px;
  background: #fff;
  cursor: pointer;
}
.service-item-card:has(input[type="checkbox"]:checked) {
  background: #f0f4f8;
  border-color: #447794;
}
.service-item-info {
  flex: 1;
}
.service-item-name {
  font-weight: 500;
  font-size: 14px;
}
.service-item-desc {
  font-size: 12px;
  color: #666;
}
.service-item-price {
  font-weight: 600;
  white-space: nowrap;
  margin-left: 10px;
}
.category-group-title {
  font-size: 13px;
  font-weight: 600;
  color: #447794;
  margin: 10px 0 6px;
  padding-bottom: 4px;
  border-bottom: 1px solid #ddd;
}
@media (max-width: 768px) {
  div[style*="grid-template-columns:repeat(2,1fr)"] {
    grid-template-columns: 1fr !important;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const houseToHouseRadio = document.getElementById('house-to-house');
  const inShopRadio = document.getElementById('in-shop');
  const addressField = document.getElementById('address-field');
  const addressInput = document.getElementById('address-input');
  const form = document.getElementById('bookingForm');

  function toggleAddressField() {
    if (houseToHouseRadio.checked) {
      addressField.style.display = 'block';
      addressInput.required = true;
    } else {
      addressField.style.display = 'none';
      addressInput.required = false;
      addressInput.value = '';
    }
  }

  houseToHouseRadio.addEventListener('change', toggleAddressField);
  inShopRadio.addEventListener('change', toggleAddressField);

  const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
  const servicesContainer = document.getElementById('services-container');
  const servicesList = document.getElementById('services-list');
  const selectedServices = document.getElementById('selected-services');
  const selectedList = document.getElementById('selected-list');
  const totalCostDiv = document.getElementById('total-cost');
  const costAmountSpan = document.getElementById('cost-amount');

  const allServices = {};

  // Populate service data
  <?php
  foreach ($categories as $cat) {
    $cat_services_query = "SELECT service_id, service_name, base_price, description, appliance_category FROM services 
                           WHERE category = '" . mysqli_real_escape_string($conn, $cat) . "' 
                           ORDER BY appliance_category, service_name";
    $cat_result = mysqli_query($conn, $cat_services_query);
    echo "allServices['" . $cat . "'] = [];";
    while ($row = mysqli_fetch_assoc($cat_result)) {
      echo "allServices['" . $cat . "'].push(" . json_encode($row) . ");";
    }
  }
  ?>

  function updateServicesList() {
    const selectedCategories = Array.from(categoryCheckboxes)
      .filter(cb => cb.checked)
      .map(cb => cb.dataset.category);

    if (selectedCategories.length === 0) {
      servicesContainer.style.display = 'none';
      servicesList.innerHTML = '';
      selectedServices.style.display = 'none';
      totalCostDiv.style.display = 'none';
      return;
    }

    servicesContainer.style.display = 'block';

    const servicesToShow = {};
    selectedCategories.forEach(cat => {
      if (allServices[cat]) {
        allServices[cat].forEach(service => {
          const appCat = service.appliance_category || 'General';
          if (!servicesToShow[appCat]) servicesToShow[appCat] = [];
          servicesToShow[appCat].push(service);
        });
      }
    });

    let html = '';
    Object.keys(servicesToShow).forEach(appCat => {
      html += `<div class="category-group-title">${appCat}</div>`;
      servicesToShow[appCat].forEach(service => {
        html += `<label class="service-item-card">
                   <input type="checkbox" name="service_ids[]" value="${service.service_id}" data-price="${service.base_price}" data-name="${service.service_name}">
                   <div class="service-item-info">
                     <div class="service-item-name">${service.service_name}</div>
                     ${service.description ? `<div class="service-item-desc">${service.description}</div>` : ''}
                   </div>
                   <div class="service-item-price">₱${parseFloat(service.base_price).toFixed(2)}</div>
                 </label>`;
      });
    });

    servicesList.innerHTML = html;

    const serviceCheckboxes = servicesList.querySelectorAll('input[type="checkbox"]');
    serviceCheckboxes.forEach(cb => {
      cb.addEventListener('change', updateSelectedList);
    });

    updateSelectedList();
  }

  function updateSelectedList() {
    const serviceCheckboxes = Array.from(servicesList.querySelectorAll('input[type="checkbox"]:checked'));
    
    if (serviceCheckboxes.length === 0) {
      selectedServices.style.display = 'none';
      totalCostDiv.style.display = 'none';
      return;
    }

    selectedServices.style.display = 'block';
    totalCostDiv.style.display = 'block';
    
    let listHtml = '';
    let totalCost = 0;
    
    serviceCheckboxes.forEach(cb => {
      const name = cb.dataset.name;
      const price = parseFloat(cb.dataset.price);
      totalCost += price;
      listHtml += `<li class="small">${name} - ₱${price.toFixed(2)}</li>`;
    });
    
    selectedList.innerHTML = listHtml;
    costAmountSpan.textContent = totalCost.toFixed(2);
  }

  categoryCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateServicesList);
  });

  // Form validation
  form.addEventListener('submit', function(e) {
    const selectedServiceIds = Array.from(servicesList.querySelectorAll('input[name="service_ids[]"]:checked'));
    
    if (selectedServiceIds.length === 0) {
      e.preventDefault();
      alert('Please select at least one service before submitting.');
      return false;
    }
  });
});
</script>

<?php include 'footer.php'; ?>