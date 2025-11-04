<?php
// adviser/member_view.php — same layout as print_membership.php, view-only, with Back button
include_once 'includes/auth_adviser.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_GET['id'])) {
    die("No member ID provided.");
}

$id = (int)$_GET['id'];

/* --- fetch member row --- */
$stmt = $conn->prepare("SELECT * FROM `member_details` WHERE `member_id` = ?");
if (!$stmt) { http_response_code(500); die("SQL prepare failed: " . $conn->error); }
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) { http_response_code(404); die("Member not found."); }

/* --- org list from DB --- */
$orgs = [];
$sql = "SELECT `org_id`, `org_name` FROM `organization` ORDER BY `org_name` ASC";
$res = $conn->query($sql);
if ($res) {
  while ($row_org = $res->fetch_assoc()) { $orgs[] = $row_org; }
}
/* --- compute School ID (supports school_id or student_id) --- */
$schoolId = $row['school_id'] ?? $row['student_id'] ?? '';
/* --- Active signatories (org-specific with global fallback) --- */
$recSig = $appSig = [];
$org_id = (int)($_SESSION['org_id'] ?? 0);

$fetchRole = function(string $role) use ($conn, $org_id) {
  // 1) Try ORG-SPECIFIC
  if ($org_id > 0) {
    if ($stmt = $conn->prepare("
      SELECT name, title
      FROM signatories
      WHERE org_id = ? AND role = ? AND is_active = 1
      ORDER BY started_on DESC, signatory_id DESC
      LIMIT 1
    ")) {
      $stmt->bind_param("is", $org_id, $role);
      if ($stmt->execute()) {
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!empty($row)) return $row; // ✅ found org-specific
      } else {
        $stmt->close();
      }
    }
  }
  // 2) Fallback to GLOBAL
  if ($stmt = $conn->prepare("
    SELECT name, title
    FROM signatories
    WHERE org_id IS NULL AND role = ? AND is_active = 1
    ORDER BY started_on DESC, signatory_id DESC
    LIMIT 1
  ")) {
    $stmt->bind_param("s", $role);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $stmt->close();
      return $row ?: [];
    } else {
      $stmt->close();
    }
  }
  return [];
};

$recSig = $fetchRole('recommending_approval');
$appSig = $fetchRole('approved');

/* --- helpers --- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Member Details</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    width: 210mm;
    margin: 5mm auto 20mm; /* ⬅️ same as your print layout */
    font-family: Arial, sans-serif;
    font-size: 11.5px;
}

/* simple back pill */
.top-actions {
  display:flex; justify-content:center; margin:8px 0 10px;
}
.btn-back {
  display:inline-flex; align-items:center; gap:6px;
  border:2px solid #116c3d; color:#116c3d; background:#fff;
  border-radius:999px; padding:.45rem .95rem; font-weight:700; text-decoration:none;
}
.btn-back:hover { background:#116c3d; color:#fff; }

/* ===== your original styles (unaltered) ===== */
.print-header {
    margin-top: 0px; /* ⬅️ remove vertical gap */
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #000;
    padding-bottom: 6px;
    margin-bottom: 6px;
}
.left-logos { display: flex; gap: 8px; }
.left-logos img { height: 58px; }
.center-text { flex: 1; text-align: center; font-size: 12px; line-height: 1.4; }
.center-text a { color: blue; text-decoration: underline; }
.iso-logo img { height: 60px; }

.membership-form-title { display: flex; align-items: center; justify-content: center; gap: 10px; }
.membership-form-title img { height: 30px; }
.membership-form-title h2 { font-size: 16px; font-weight: bold; margin: 0; text-transform: uppercase; }

.checkbox-group { display: flex; justify-content: space-between; margin-top: 8px; }
.checkbox-column { display: flex; flex-direction: column; gap: 4px; }
.checkbox-column label { display: flex; align-items: center; gap: 6px; font-size: 11.5px; }
.photo-box {
    width: 144px; height: 144px; border: 1.5px solid #000; float: right; margin-top: -35px;
}

.checkbox-group { display: flex; justify-content: space-between; margin-top: 10px; }
.checkbox-column { display: flex; flex-direction: column; gap: 4px; }
.checkbox-column label { display: flex; align-items: center; gap: 6px; font-size: 11.5px; }

.title-layout {
    display: flex; align-items: center; justify-content: center; gap: 20px;
    margin-bottom: 50px; font-family: Arial, sans-serif;
}
.title-text-block { text-align: center; line-height: 1.3; }
.title-text-block h4 { font-size: 15px; font-weight: bold; text-transform: uppercase; margin: 0; }
.title-text-block h2 { font-size: 17px; font-weight: bold; text-transform: uppercase; margin: 0; }
.title-layout img { height: 60px; }

.preferred-org-section {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-top: 10px; margin-left: 30px; margin-right: 20px;
    font-family: Arial, sans-serif; font-size: 12px; margin-bottom: 10px;
}
.org-checkboxes { flex: 1; }
.org-checkboxes .section-title { font-size: 12px; font-weight: bold; margin-bottom: 6px; }
.checkbox-columns { display: flex; gap: 32px; margin-left: 10px; }
.checkbox-column { display: flex; flex-direction: column; gap: 4px; }
.checkbox-column label { display: flex; align-items: center; gap: 6px; }

/* Adjust the "Others" input line  (keeping for parity if ever needed) */
.others-input { display: flex; align-items: center; gap: 6px; }
.others-line { display: inline-block; border-bottom: 1px solid #000; width: 80px; margin-left: 4px; }

.info-section {
    display: flex; justify-content: space-between; margin: 30px;
    font-family: Arial, sans-serif; font-size: 13px; margin-top: 10px;
}
.info-col { width: 48%; }
.info-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
.info-row label { width: 45%; }
.info-row span {
    font-weight: bold; border-bottom: 1px solid #000; display: inline-block; width: 80%;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.gender-options { display: flex; gap: 40px; align-items: center; font-size: 13px; margin-right: 50px; }
.gender-options label { display: flex; align-items: center; font-weight: normal; }

.certification { margin: 30px; font-size: 12px; font-family: Arial, sans-serif; }
.certification p { margin-bottom: 25px; text-align: justify; }

.signature-section { display: flex; justify-content: space-between; margin-bottom: 30px; gap: 50px; }
.signature-block { width: 45%; font-size: 12px; }
.signature-block .line { border-bottom: 1px solid #000; width: 230px; margin-bottom: 2px; }

.approval-section { display: flex; justify-content: space-between; margin-bottom: 25px; font-size: 12px; margin-top: 20px; }
.approval-box { width: 45%; line-height: 1.4; }

.footer-note {
    font-size: 11px; border-top: 1px solid #000; padding-top: 6px; margin-top: 40px;
    display: flex; justify-content: space-between;
}
</style>
</head>
<body>

<!-- Back button (replaces print/update/home controls) -->
<div class="top-actions">
  <a href="javascript:history.back()" class="btn-back">
    <i class="bi bi-arrow-left"></i><span>Back</span>
  </a>
</div>

<!-- HEADER -->
<div class="print-header">
    <div class="left-logos">
        <img src="../assets/cbsua_logo.png" alt="CBSUA Logo">
        <img src="../assets/bagong_pilipinas_logo.png" alt="Bagong Pilipinas Logo">
    </div>
    <div class="center-text">
        <strong>Republic of the Philippines</strong><br>
        <strong>CENTRAL BICOL STATE UNIVERSITY OF AGRICULTURE</strong><br>
        San Jose, Pili, Camarines Sur 4418<br>
        Website: <a href="https://www.cbsua.edu.ph">www.cbsua.edu.ph</a><br>
        Email: <a href="mailto:oc@cbsua.edu.ph">oc@cbsua.edu.ph</a> | <a href="mailto:president@cbsua.edu.ph">president@cbsua.edu.ph</a><br>
        Trunkline: (054) 871-5531 / 871-5533
    </div>
    <div class="iso-logo">
        <img src="../assets/iso_logo.png" alt="ISO Certified Logo">
    </div>
</div>

<!-- SENTRO NG WIKA Title Block -->
<div class="title-layout">
    <img src="../assets/swks.png" alt="Left Logo">
    <div class="title-text-block">
        <h4>Sentro ng Wika, Sining at Kultura</h4>
        <h2>Membership Form</h2>
    </div>
    <img src="../assets/aca.jpg" alt="Right Logo">
</div>

<div class="preferred-org-section">
    <div class="org-checkboxes">
        <div class="section-title">
            Preferred Organization (Please check the box)
        </div>
        <div class="checkbox-columns" style="display: flex; gap: 32px;">
            <?php
            // Compute split for two columns
            $orgs_count = count($orgs);
            $per_col = $orgs_count ? ceil($orgs_count / 2) : 0;

            // First column
            echo '<div class="checkbox-column">';
            for ($i = 0; $i < $per_col; $i++) {
                if (!isset($orgs[$i])) break;
                $checked = ((string)$row['preferred_org'] === (string)$orgs[$i]['org_id']) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" '.$checked.' disabled> '.h($orgs[$i]['org_name']).'
                </label>';
            }
            echo '</div>';

            // Second column
            echo '<div class="checkbox-column">';
            for ($i = $per_col; $i < $orgs_count; $i++) {
                $checked = ((string)$row['preferred_org'] === (string)$orgs[$i]['org_id']) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" '.$checked.' disabled> '.h($orgs[$i]['org_name']).'
                </label>';
            }
            echo '</div>';
            ?>
        </div>
    </div>
    <!-- Photo section as is... -->
    <div class="photo-box">
        <img src="../uploads/<?= h($row['profile_picture']) ?>"
             alt="Profile Picture"
             style="width: 144px; height: 144px; object-fit: cover;">
    </div>
</div>

<div class="info-section">
    <!-- Left Column -->
    <div class="info-col">
        <div class="info-row"><label>Name:</label><span><?= h($row['full_name']) ?></span></div>
        <div class="info-row"><label>Nickname:</label><span><?= h($row['nickname']) ?></span></div>
        <div class="info-row"><label>Course:</label><span><?= h($row['course']) ?></span></div>
        <div class="info-row"><label>Birthdate:</label><span><?= h($row['birthdate']) ?></span></div>
        <div class="info-row"><label>Address:</label><span><?= h($row['address']) ?></span></div>
        <div class="info-row"><label>Contact Number:</label><span><?= h($row['contact_number']) ?></span></div>
        <div class="info-row"><label>Mother's Name:</label><span><?= h($row['mother_name']) ?></span></div>
        <div class="info-row"><label>Father's Name:</label><span><?= h($row['father_name']) ?></span></div>
        <div class="info-row"><label>Guardian:</label><span><?= h($row['guardian']) ?></span></div>
        <div class="info-row"><label>Address:</label><span><?= h($row['guardian_address']) ?></span></div>
    </div>

    <!-- Right Column -->
    <div class="info-col">
        <div class="info-row"><label>AY:</label><span><?= h($row['ay']) ?></span></div>
        <div class="info-row">
            <div class="info-label">Gender:</div>
            <div class="gender-options">
                <label><input type="checkbox" <?= ($row['gender'] == 'Female' ? 'checked' : '') ?> disabled> Female</label>
                <label><input type="checkbox" <?= ($row['gender'] == 'Male' ? 'checked' : '') ?> disabled> Male</label>
            </div>
        </div>

        <div class="info-row"><label>Year Level:</label><span><?= h($row['year_level']) ?></span></div>
        <div class="info-row"><label>School ID:</label><span><?= h($schoolId) ?></span></div>
        <div class="info-row"><label>Age:</label><span><?= h($row['age']) ?></span></div>
        <div class="info-row"><label>Email Address:</label><span><?= h($row['email']) ?></span></div>
        <div class="info-row"><label>Occupation (Mother):</label><span><?= h($row['mother_occupation']) ?></span></div>
        <div class="info-row"><label>Occupation (Father):</label><span><?= h($row['father_occupation']) ?></span></div>
    </div>
</div>

<div class="certification">
    <p style="font-style: italic; font-weight: bold; margin-bottom: 50px;">
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I hereby certify that all facts and information indicated herein are true and correct to the best of my knowledge.
        I further declare that I will follow the rules and regulation imposed under the constitution and by-laws of the organization.
    </p>

    <!-- Applicant Signature Section -->
    <div class="signature-section" style="margin-bottom: 10px;">
        <div class="signature-block">
            <div style="border-bottom: 1px solid #000; width: 230px;"></div>
            <div>Applicant Signature</div>
            <div>Date: <span style="border-bottom: 1px solid #000; width: 120px; display: inline-block;"></span></div>
        </div>
    </div>

    <!-- Certified by Organization Adviser -->
    <div class="signature-block" style="margin-top: 30px;">
        <div><strong>Certified:</strong></div>
        <br>
        <div style="border-bottom: 1px solid #000; width: 230px; margin-bottom: 2px; margin-top: 40px;"></div>
        <div>Organization Adviser</div>
        <div>
            Date: <span style="border-bottom: 1px solid #000; width: 120px; display: inline-block;"></span>
        </div>
    </div>

    <!-- Recommending and Approval Section -->
   <div class="approval-section">
    <!-- Recommending Approval -->
<div class="approval-box">
  <div style="margin-bottom:30px;"><strong>Recommending Approval:</strong></div>
  <br>
  <div><strong><u><?= h(strtoupper($recSig['name'] ?? '')) ?></u></strong></div>
  <div><?= h($recSig['title'] ?? '') ?></div>
  <div>Date: <span style="border-bottom: 1px solid #000; width: 120px; display: inline-block;"></span></div>
</div>

<div class="approval-box">
  <div style="margin-bottom:30px;"><strong>Approved:</strong></div>
  <br>
  <div><strong><u><?= h(strtoupper($appSig['name'] ?? '')) ?></u></strong></div>
  <div><?= h($appSig['title'] ?? '') ?></div>
  <div>Date: <span style="border-bottom: 1px solid #000; width: 120px; display: inline-block;"></span></div>
</div>


</div>
    <!-- Footer -->
    <div class="footer-note">
        <span>SWK-FR-004</span>
        <span>Effectivity Date: June 3, 2024</span>
        <span style="float:right;">Rev.: 04<br>Page 1 of 1</span>
    </div>
</div>

<!-- bottom back only -->
<div style="width:100%; text-align:center; margin:30px 0 10px 0; display:flex; justify-content:center; gap: 20px;">
  <a class="btn-back" href="javascript:history.back()">
    <i class="bi bi-arrow-left"></i><span>Back</span>
  </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
