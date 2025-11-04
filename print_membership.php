<?php
include 'database/db_connection.php';

if (!isset($_GET['id'])) {
    die("No member ID provided.");
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM member_details WHERE member_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
// === Active signatories (GLOBAL: org_id IS NULL) ===
$recSig = $appSig = [];

$q = $conn->query("
  SELECT role, name, title
  FROM signatories
  WHERE is_active = 1 AND org_id IS NULL AND role IN ('recommending_approval','approved')
  ORDER BY started_on DESC
");
if ($q) {
  while ($s = $q->fetch_assoc()) {
    if ($s['role'] === 'recommending_approval') $recSig = $s;
    if ($s['role'] === 'approved')               $appSig = $s;
  }
}

// School ID (support both 'school_id' or older 'student_id' column names)
$schoolId = $row['school_id'] ?? $row['student_id'] ?? '';

$preferred = explode(', ', $row['preferred_org']);

// Function to check if a box should be checked
function isChecked($org, $preferred) {
    return in_array($org, $preferred) ? 'checked' : '';
}

// Known organizations
$orgs = [
    'Majorettes', 'Marching Band', 'Performing Arts Ensemble', 'Chorale', 'KAMFIL',
    'Wasivas/Colorguard', 'Gurit', 'Literary Arts Group'
];

// Check for custom "Others" value
$othersText = '';
foreach ($preferred as $org) {
    if (!in_array(trim($org), $orgs)) {
        $othersText = trim($org);
    }
}

$orgs = [];
$sql = "SELECT org_id, org_name FROM organization ORDER BY org_name ASC";
$result = $conn->query($sql);
while ($row_org = $result->fetch_assoc()) {
    $orgs[] = $row_org;
}
?>

<style>
body {
    width: 210mm;
     margin: 5mm auto 20mm; /* ⬅️ changed from 20mm top to 10mm */
    font-family: Arial, sans-serif;
    font-size: 11.5px;
}

.print-header {
    margin-top: 0px; /* ⬅️ remove vertical gap */
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #000;
    padding-bottom: 6px;
    margin-bottom: 6px;
}

.left-logos {
    display: flex;
    gap: 8px;
}
.left-logos img {
    height: 58px;
}

.center-text {
    flex: 1;
    text-align: center;
    font-size: 12px;
    line-height: 1.4;
}
.center-text a {
    color: blue;
    text-decoration: underline;
}

.iso-logo img {
    height: 60px;
}

.membership-form-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.membership-form-title img {
    height: 30px;
}

.membership-form-title h2 {
    font-size: 16px;
    font-weight: bold;
    margin: 0;
    text-transform: uppercase;
}

/* Photo box */

/* Checkbox layout */
.checkbox-group {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
}
.checkbox-column {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.checkbox-column label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
}
.photo-box {
    width: 144px;
    height: 144px;
    border: 1.5px solid #000;
    float: right;
    margin-top: -35px;
}

/* Checkbox layout */
.checkbox-group {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}
.checkbox-column {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.checkbox-column label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
}

.title-layout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-bottom: 50px; /* ⬅️ make room below title */
    font-family: Arial, sans-serif;
}

.title-text-block {
    text-align: center;
    line-height: 1.3;
}

.title-text-block h4 {
    font-size: 15px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 0;
}

.title-text-block h2 {
    font-size: 17px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 0;
}

.title-layout img {
    height: 60px;
}

.preferred-org-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-top: 10px;
    margin-left: 30px;     /* ⬅️ indent to the right */
    margin-right: 20px;    /* ⬅️ prevent overflow on right */
    font-family: Arial, sans-serif;
    font-size: 12px;
    margin-bottom: 10px;
}


.org-checkboxes {
    flex: 1;
}

.org-checkboxes .section-title {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 6px;
}

.checkbox-columns {
    display: flex;
    gap: 30%;
    margin-left: 10px;
}

.checkbox-column {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.checkbox-column label {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Adjust the "Others" input line */
.others-input {
    display: flex;
    align-items: center;
    gap: 6px;
}

.others-line {
    display: inline-block;
    border-bottom: 1px solid #000;
    width: 80px;
    margin-left: 4px;
}

.info-section {
    display: flex;
    justify-content: space-between;
    margin: 30px;
    font-family: Arial, sans-serif;
    font-size: 13px;
    margin-top: 10px;
}
.info-col {
    width: 48%;
}
.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
    margin-bottom: 10px;   /* <--- adjust to 6px or 8px for 0.5 spacing */
}
.info-row label {
    width: 45%;
}
.info-row span {
    font-weight: bold;
    border-bottom: 1px solid #000;
    display: inline-block;
    width: 80%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}


.gender-options {
  display: flex;
  gap: 40px;
  align-items: center;
  font-size: 13px;
  margin-right: 50px;
}
.gender-options label {
  display: flex;
  align-items: center;
  font-weight: normal;
}
.certification {
    margin: 30px;
    font-size: 12px;
    font-family: Arial, sans-serif;
}

.certification p {
    margin-bottom: 25px;
    text-align: justify;
}

/* Signature and Certified section */
.signature-section {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    gap: 50px;
}

.signature-block {
    width: 45%;
    font-size: 12px;
}

.signature-block .line {
    border-bottom: 1px solid #000;
    width: 230px;
    margin-bottom: 2px;
}

/* Approval box */
.approval-section {
    display: flex;
    justify-content: space-between;
    margin-bottom: 25px;
    font-size: 12px;
    margin-top: 20px;
}

.approval-box {
    width: 45%;
    line-height: 1.4;
}

/* Footer note */
.footer-note {
    font-size: 11px;
    border-top: 1px solid #000;
    padding-top: 6px;
    margin-top: 40px;
    display: flex;
    justify-content: space-between;
}

@media print {
    .noprint {
        display: none !important;
    }
}

</style>

<!-- HEADER -->
<div class="print-header">
    <div class="left-logos">
        <img src="assets/cbsua_logo.png" alt="CBSUA Logo">
        <img src="assets/bagong_pilipinas_logo.png" alt="Bagong Pilipinas Logo">
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
        <img src="assets/iso_logo.png" alt="ISO Certified Logo">
    </div>
</div>

<!-- SENTRO NG WIKA Title Block -->
<div class="title-layout">
    <img src="assets/swks.png" alt="Left Logo">

    <div class="title-text-block">
        <h4>Sentro ng Wika, Sining at Kultura</h4>
        <h2>Membership Form</h2>
    </div>

    <img src="assets/aca.jpg" alt="Right Logo">
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
            $per_col = ceil($orgs_count / 2);

            // First column
            echo '<div class="checkbox-column">';
            for ($i = 0; $i < $per_col; $i++) {
                $checked = ($row['preferred_org'] == $orgs[$i]['org_id']) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" '.$checked.' disabled> '.htmlspecialchars($orgs[$i]['org_name']).'
                </label>';
            }
            echo '</div>';

            // Second column
            echo '<div class="checkbox-column">';
            for ($i = $per_col; $i < $orgs_count; $i++) {
                $checked = ($row['preferred_org'] == $orgs[$i]['org_id']) ? 'checked' : '';
                echo '<label style="display:block; margin-bottom:8px;">
                    <input type="checkbox" '.$checked.' disabled> '.htmlspecialchars($orgs[$i]['org_name']).'
                </label>';
            }
            echo '</div>';
            ?>
        </div>
    </div>
    <!-- Photo section as is... -->
    <div class="photo-box">
        <img src="uploads/<?= htmlspecialchars($row['profile_picture']) ?>" 
             alt="Profile Picture" 
             style="width: 144px; height: 144px; object-fit: cover;">
    </div>
</div>


<div class="info-section">
    <!-- Left Column -->
    <div class="info-col">
        <div class="info-row"><label>Name:</label><span><?= htmlspecialchars($row['full_name']) ?></span></div>
        <div class="info-row"><label>Nickname:</label><span><?= htmlspecialchars($row['nickname']) ?></span></div>
        <div class="info-row"><label>Course:</label><span><?= htmlspecialchars($row['course']) ?></span></div>
        <div class="info-row"><label>Birthdate:</label><span><?= htmlspecialchars($row['birthdate']) ?></span></div>
        <div class="info-row"><label>Address:</label><span><?= htmlspecialchars($row['address']) ?></span></div>
        <div class="info-row"><label>Contact Number:</label><span><?= htmlspecialchars($row['contact_number']) ?></span></div>
        <div class="info-row"><label>Mother's Name:</label><span><?= htmlspecialchars($row['mother_name']) ?></span></div>
        <div class="info-row"><label>Father's Name:</label><span><?= htmlspecialchars($row['father_name']) ?></span></div>
        <div class="info-row"><label>Guardian:</label><span><?= htmlspecialchars($row['guardian']) ?></span></div>
        <div class="info-row"><label>Address:</label><span><?= htmlspecialchars($row['guardian_address']) ?></span></div>
    </div>

    <!-- Right Column -->
    <div class="info-col">
        <div class="info-row"><label>AY:</label><span><?= htmlspecialchars($row['ay']) ?></span></div>
        <div class="info-row">
            <div class="info-label">Gender:</div>
            <div class="gender-options">
                <label><input type="checkbox" <?= $row['gender'] == 'Female' ? 'checked' : '' ?> disabled> Female</label>
                <label><input type="checkbox" <?= $row['gender'] == 'Male' ? 'checked' : '' ?> disabled> Male</label>
            </div>
        </div>

        <div class="info-row"><label>Year Level:</label><span><?= htmlspecialchars($row['year_level']) ?></span></div>
        <div class="info-row"><label>School ID:</label><span><?= htmlspecialchars($schoolId) ?></span></div>
        <div class="info-row"><label>Age:</label><span><?= htmlspecialchars($row['age']) ?></span></div>
        <div class="info-row"><label>Email Address:</label><span><?= htmlspecialchars($row['email']) ?></span></div>
        <div class="info-row"><label>Occupation (Mother):</label><span><?= htmlspecialchars($row['mother_occupation']) ?></span></div>
        <div class="info-row"><label>Occupation (Father):</label><span><?= htmlspecialchars($row['father_occupation']) ?></span></div>
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
  <div><strong><u><?= htmlspecialchars(strtoupper($recSig['name'] ?? '')) ?></u></strong></div>
  <div><?= htmlspecialchars($recSig['title'] ?? '') ?></div>
  <div>Date: <span style="border-bottom: 1px solid #000; width: 120px; display: inline-block;"></span></div>
</div>

<!-- Approved -->
<div class="approval-box">
  <div style="margin-bottom:30px;"><strong>Approved:</strong></div>
  <br>
  <div><strong><u><?= htmlspecialchars(strtoupper($appSig['name'] ?? '')) ?></u></strong></div>
  <div><?= htmlspecialchars($appSig['title'] ?? '') ?></div>
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

<div class="noprint" style="width:100%; text-align:center; margin:30px 0 10px 0; print-color-adjust: exact; display: flex; justify-content: center; gap: 20px;">
    <a class="noprint" href="index.php" 
   style="padding:10px 25px; font-size:16px; background-color:#5b9bd5; color:#fff; border:none; border-radius:10px; text-decoration:none; display:inline-block; transition: background 0.2s;">
    <span style="font-size:18px; vertical-align:middle; margin-right:6px;">&#8592;</span> Back to Homepage
</a>

    <button onclick="window.print()" style="padding:10px 25px; font-size:13px; background-color:#116c3d; color:#fff; border:none; border-radius:5px; cursor:pointer;">
        🖨️ Print Form
    </button>
    <a class="noprint" href="membership.php?id=<?= $row['member_id'] ?>&action=update" 
       style="padding:10px 25px; font-size:13px; background-color:#1976d2; color:#fff; border:none; border-radius:5px; text-decoration:none; display:inline-block;">
        ✏️ Update
    </a>
</div>


