<?php
//cloning.. try
include 'includes/header.php';
include_once 'database/db_connection.php';

$carouselQ = $conn->query("SELECT image_path, description FROM web_settings WHERE type = 'carousel' AND status = 'visible' ORDER BY created_at DESC");
$carouselItems = $carouselQ->fetch_all(MYSQLI_ASSOC);
?>

<style>
/* 👇 Scroll Indicator */
.scroll-indicator {
  font-size: 2rem;
  color: white;
  position: absolute;
  bottom: 15px;
  left: 50%;
  transform: translateX(-50%);
  animation: bounce 1.5s infinite;
  z-index: 10;
}
@keyframes bounce {
  0%, 100% { transform: translateX(-50%) translateY(0); }
  50% { transform: translateX(-50%) translateY(10px); }
}

/* 👇 Animate Caption */
.animate-caption {
  animation: fadeInUp 1s ease-in-out both;
}
@keyframes fadeInUp {
  0% { opacity: 0; transform: translateY(20px); }
  100% { opacity: 1; transform: translateY(0); }
}

/* 👇 Adjust Image Height */
.carousel-item img {
  height: 90vh;
  object-fit: cover;
  object-position: center;
}
@media (max-width: 768px) {
  .carousel-item img {
    height: 40vh !important;
  }
}
</style>

<?php if (!empty($carouselItems)): ?>
<!-- ✅ Carousel Section with Fade -->
<div id="homepageCarousel" class="carousel slide carousel-fade mb-0" data-bs-ride="carousel" style="margin-top: 65px; position: relative;">
  <div class="carousel-inner">
    <?php foreach ($carouselItems as $index => $item): ?>
      <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
        <img src="<?= htmlspecialchars($item['image_path']) ?>" class="d-block w-100" alt="Carousel Image">
        <?php if (!empty($item['description'])): ?>
          <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded px-3 py-2 animate-caption">
            <p class="mb-0 fs-5"><?= htmlspecialchars($item['description']) ?></p>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#homepageCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#homepageCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
  </button>

  <!-- ⬇️ Scroll Down Indicator -->
  <div class="scroll-indicator text-center">
    <i class="bi bi-chevron-double-down"></i>
  </div>
</div>
<?php endif; ?>

<!-- Registration Process Section -->
<section id="features" class="features-section py-5" style="background-color: #f8f9fa; margin-top: 40px;">
    <div class="container px-3 px-md-5">
        <div class="row text-center mb-4">
            <div class="col">
                <h2 style="color: #064d00; font-weight:700;">Account Registration Process</h2>
                <p class="text-muted mb-0">Follow these steps to successfully create an account</p>
            </div>
        </div>
        <div class="row g-4 justify-content-center">
            <!-- Step 1 -->
            <div class="col-lg-4 col-md-6">
                <div class="feature-card interactive-card text-center h-100 p-4">
                    <div class="feature-icon mb-3 mx-auto">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h5 class="mb-2" style="color:#064d00; font-weight:600;">Step 1: Fill Out the Membership Form</h5>
                    <p class="mb-3">Download and complete the membership form required to proceed.</p>
                    <a href="membership.php" class="btn btn-primary btn-sm">Go to Membership Form</a>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="col-lg-4 col-md-6">
                <div class="feature-card interactive-card text-center h-100 p-4">
                    <div class="feature-icon mb-3 mx-auto">
                        <i class="bi bi-pen"></i>
                    </div>
                    <h5 class="mb-2" style="color:#064d00; font-weight:600;">Step 2: Get Signatures</h5>
                    <p class="mb-0">Print the form and have it signed by all designated signatories listed on the form.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="col-lg-4 col-md-6">
                <div class="feature-card interactive-card text-center h-100 p-4">
                    <div class="feature-icon mb-3 mx-auto">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <h5 class="mb-2" style="color:#064d00; font-weight:600;">Step 3: Register Your Account</h5>
                    <p class="mb-0">Once signed, you may now register your account in the system.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact/CTA Section -->
<section id="contact" class="py-5" style="background: #e8f6ea;">
    <div class="container text-center">
        <h3 style="color:#18BA15; font-weight:700;">Get in Touch</h3>
        <p class="mb-3">Questions? Need support? Reach out to our team for assistance.</p>
        <a href="mailto:info@swks.com" class="btn btn-primary px-4 py-2">Contact Us</a>
    </div>
</section>


<?php include 'includes/footer.php'; ?>