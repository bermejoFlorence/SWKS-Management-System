<?php
include 'includes/header.php';
include_once 'database/db_connection.php';

// Fetch About info (status visible or default to latest if no status)
$aboutQ = $conn->query("SELECT * FROM web_settings WHERE type='about' AND status='visible' LIMIT 1");
$about = $aboutQ->fetch_assoc();
?>

<style>
/* Responsive & pleasant UI */
.about-section {
  max-width: 680px;
  margin: 60px auto 50px auto;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 4px 32px 0 rgba(30,90,40,.10);
  padding: 32px 24px 32px 24px;
}
@media (max-width: 768px) {
  .about-section { padding: 18px 8px; }
}
.profile-pic {
  width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 3px solid #e3f0de;
  box-shadow: 0 2px 12px rgba(30,90,40,0.06);
}
.org-chart-img {
  max-width: 98%; max-height: 290px; border-radius: 14px; border:1.5px solid #e3f0de; margin-top:10px;
  box-shadow: 0 2px 12px rgba(30,90,40,0.07);
}
.section-title {
  font-size: 2.1rem; font-weight: 700; color: #18BA15; margin-bottom: 22px;
}
.about-label {
  font-weight: 600; color: #145508; margin-top: 10px;
}
</style>

<div class="about-section shadow">
  <div class="text-center mb-4">
    <div class="section-title">
      <i class="bi bi-info-circle-fill me-2"></i> About Sentro ng Wika, Kultura at Sining
    </div>
  </div>
  <?php if ($about): ?>
    <?php if (!empty($about['head_profile'])): ?>
      <div class="text-center mb-3">
        <img src="<?= htmlspecialchars($about['head_profile']) ?>" alt="ACA Coordinator" class="profile-pic mb-2">
      </div>
    <?php endif; ?>
    <?php if (!empty($about['department_head'])): ?>
      <div class="about-label text-center">Department Head or ACA Coordinator</div>
      <div class="text-center mb-3"><?= htmlspecialchars($about['department_head']) ?></div>
    <?php endif; ?>
    <?php if (!empty($about['description'])): ?>
      <div class="about-label">About SWKS</div>
      <div style="text-align:justify" class="mb-3"><?= nl2br(htmlspecialchars($about['description'])) ?></div>
    <?php endif; ?>
    <?php if (!empty($about['org_chart'])): ?>
      <div class="about-label">Organizational Chart</div>
      <div class="text-center">
        <img src="<?= htmlspecialchars($about['org_chart']) ?>" alt="Organizational Chart" class="org-chart-img">
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="alert alert-warning text-center">No About information available yet. Please check back soon.</div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
