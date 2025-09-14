<?php
include 'includes/header.php';
include 'database/db_connection.php';

// Prefill support for update mode
$editMode = false;
$memberData = [];
$preferred = [];
$otherOrg = "";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM member_details WHERE member_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $memberData = $result->fetch_assoc();
    $stmt->close();

    $editMode = true;
}

$orgs = [];
$sql = "SELECT org_id, org_name FROM organization ORDER BY org_name ASC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $orgs[] = $row;
}

$today = date('Y-m-d'); 
?>
<style>
    .membership-form label {
        font-weight: 600;
        color: #064d00;
    }
    .membership-form h3 {
        color: #064d00;
        font-weight: 700;
    }
    .form-section {
        background-color: #fff;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
    .form-card {
        border: 2px solid #4CAF50;
        border-radius: 25px;
        padding: 40px 20px 20px;
        margin-bottom: 30px;
        position: relative;
    }
    .form-card::before {
        content: attr(data-title);
        position: absolute;
        top: -14px;
        left: 30px;
        background: #fff;
        padding: 0 10px;
        font-weight: 700;
        font-size: 1.2rem;
        color: #064d00;
    }
    .form-check-inline {
        display: inline-flex;
        align-items: center;
        margin-right: 10px;
    }
    .gender-wrapper {
        display: flex;
        flex-direction: column;
    }
    .gender-wrapper .gender-options {
        display: flex;
        justify-content: space-around;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 8px 0;
    }
    @media (max-width: 576px) {
        .form-section {
            padding: 20px;
        }
        .form-card::before {
            font-size: 1rem;
        }
    }
</style>

<!-- Membership form -->
<div class="container py-5">
    <div class="form-section membership-form">
        <h3 class="text-center mb-4">Membership Form</h3>
        <form method="post" action="submit_member.php" enctype="multipart/form-data">
            <?php if ($editMode): ?>
                <input type="hidden" name="member_id" value="<?= $id ?>">
            <?php endif; ?>

            <div class="form-card" data-title="Preferred Organization">
                <label>Select only one preferred organization:</label>
                <div class="row">
                    <?php foreach ($orgs as $org): ?>
                        <div class="col-sm-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="preferred_org" value="<?= $org['org_id'] ?>"
                                    <?= (isset($preferred_org) && $preferred_org == $org['org_id']) ? 'checked' : '' ?>>
                                <label class="form-check-label"><?= htmlspecialchars($org['org_name']) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-card" data-title="Personal Information">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label>Full Name:</label>
                        <input type="text" name="full_name" class="form-control letters-only" required
                               value="<?= $editMode ? htmlspecialchars($memberData['full_name']) : '' ?>">
                    </div>
                    <div class="col-md-2">
                        <label>Nickname:</label>
                        <input type="text" name="nickname" class="form-control letters-only"
                               value="<?= $editMode ? htmlspecialchars($memberData['nickname']) : '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label>AY:</label>
                        <input type="text" name="ay" class="form-control"
                               value="<?= $editMode ? htmlspecialchars($memberData['ay']) : '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Gender:</label>
                        <div class="d-flex justify-content-center">
                            <div class="form-check me-3">
                                <input class="form-check-input" type="radio" name="gender" value="Female" required
                                    <?= ($editMode && $memberData['gender'] == 'Female') ? 'checked' : '' ?>>
                                <label class="form-check-label">Female</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="gender" value="Male"
                                    <?= ($editMode && $memberData['gender'] == 'Male') ? 'checked' : '' ?>>
                                <label class="form-check-label">Male</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label>Course:</label>
                        <select name="course" class="form-select" required>
                            <option value="">-- Select Course --</option>
                            <?php
                            $courses = [
                                "Bachelor of Elementary Education",
                                "Bachelor of Science in Information Technology",
                                "Bachelor of Secondary Major in Mathematics",
                                "Bachelor of Secondary Major in English",
                                "Bachelor of Secondary Major in Filipino",
                                "Bachelor of Secondary Major in Technical and Livelihood Education",
                                "Bachelor of Secondary Major in Science",
                                "Bachelor of Industrial Technology"
                            ];
                            foreach ($courses as $course) {
                                $selected = ($editMode && $memberData['course'] == $course) ? 'selected' : '';
                                echo "<option $selected>$course</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Year Level:</label>
                        <select name="year_level" class="form-select" style="min-width: 160px;" required>
                            <option value="">Year Level</option>
                            <?php
                            $levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
                            foreach ($levels as $lvl) {
                                $selected = ($editMode && $memberData['year_level'] == $lvl) ? 'selected' : '';
                                echo "<option $selected>$lvl</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Birthdate:</label>
                        <input type="date" name="birthdate" id="birthdate" class="form-control" required
                            max="<?= $today ?>"
                            value="<?= $editMode ? htmlspecialchars($memberData['birthdate']) : '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Age:</label>
                        <input type="number" name="age" id="age" class="form-control" readonly
                               value="<?= $editMode ? htmlspecialchars($memberData['age']) : '' ?>">
                    </div>
                    <div class="col-12">
                        <label>Address:</label>
                        <input type="text" name="address" class="form-control"
                               value="<?= $editMode ? htmlspecialchars($memberData['address']) : '' ?>">
                    </div>
                </div>
            </div>

            <div class="form-card" data-title="Contact, Parent & Guardian Info">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Contact Number:</label>
                        <input type="text" name="contact_number" class="form-control numbers-only"
                               value="<?= $editMode ? htmlspecialchars($memberData['contact_number']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Email Address:</label>
                        <input type="email" name="email" class="form-control"
                            placeholder="Please use your Institutional Email"
                            value="<?= $editMode ? htmlspecialchars($memberData['email']) : '' ?>"
                            id="emailField" required>
                    </div>
                    <div class="col-md-6">
                        <label>Mother's Name:</label>
                        <input type="text" name="mother_name" class="form-control letters-only"
                               value="<?= $editMode ? htmlspecialchars($memberData['mother_name']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Mother's Occupation:</label>
                        <input type="text" name="mother_occupation" class="form-control letters-only"
                               value="<?= $editMode ? htmlspecialchars($memberData['mother_occupation']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Father's Name:</label>
                        <input type="text" name="father_name" class="form-control letters-only"
                               value="<?= $editMode ? htmlspecialchars($memberData['father_name']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Father's Occupation:</label>
                        <input type="text" name="father_occupation" class="form-control letters-only"
                               value="<?= $editMode ? htmlspecialchars($memberData['father_occupation']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Guardian:</label>
                        <input type="text" name="guardian" class="form-control letters-only"
                               value="<?= $editMode ? htmlspecialchars($memberData['guardian']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Guardian Address:</label>
                        <input type="text" name="guardian_address" class="form-control"
                               value="<?= $editMode ? htmlspecialchars($memberData['guardian_address']) : '' ?>">
                    </div>
                </div>
            </div>

            <div class="form-card" data-title="Profile Picture & Certification">
                <div class="mb-3">
                    <label>Upload Profile Picture:</label>
                    <input type="file" name="profile_picture" class="form-control">
                    <?php if ($editMode && !empty($memberData['profile_picture'])): ?>
                        <div style="margin-top:10px;">
                            <img src="uploads/<?= htmlspecialchars($memberData['profile_picture']) ?>" style="max-width: 120px; border:1px solid #ccc;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="certify" name="certify" required <?= $editMode ? 'checked' : '' ?>>
                    <label class="form-check-label" for="certify">
                        I hereby certify that all facts and information indicated herein are true and correct to the best of my knowledge. I further declare that I will follow the rules and regulations imposed under the constitution and by-laws of the organization.
                    </label>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary px-5"><?= $editMode ? 'Update' : 'Submit' ?></button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_GET['success']) && $_GET['success'] == 'print' && isset($_GET['id'])): ?>
<script>
    // If this is a forward/after submit
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Your membership form has been submitted successfully.',
        confirmButtonColor: '#064d00'
    }).then(function(){
        window.location.href = "print_membership.php?id=<?= $_GET['id']; ?>";
    });
</script>
<?php else: ?>

<?php endif; ?>


<script>
      // Allow only one checkbox to be checked
    document.querySelectorAll('.preferred-checkbox').forEach(box => {
        box.addEventListener('change', function () {
            if (this.checked) {
                document.querySelectorAll('.preferred-checkbox').forEach(cb => {
                    if (cb !== this) cb.checked = false;
                });
            }
        });
    });

        document.addEventListener('DOMContentLoaded', function () {
        const letterOnlyInputs = document.querySelectorAll('.letters-only');
        letterOnlyInputs.forEach(input => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^a-zA-Z\s\.\-]/g, '');
            });
        });
        const numberOnlyInputs = document.querySelectorAll('.numbers-only');
        numberOnlyInputs.forEach(input => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9\s\-]/g, '');
            });
        });
    });

    document.getElementsByName('birthdate')[0].addEventListener('change', function () {
        const birthDate = new Date(this.value);
        const today = new Date();

        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();

        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        document.getElementsByName('age')[0].value = age;
    });

    document.addEventListener('DOMContentLoaded', function () {
    const emailInput = document.getElementById('emailField');

    emailInput.addEventListener('input', function () {
        // Remove previous error state
        emailInput.setCustomValidity('');

        // Only check if value is not empty
        if (emailInput.value !== '') {
            // If not ending with @cbsua.edu.ph, set error
            if (!emailInput.value.toLowerCase().endsWith('@cbsua.edu.ph')) {
                emailInput.setCustomValidity('Please use your institutional email ending in @cbsua.edu.ph');
            }
        }
    });
});

</script>
<?php include 'includes/footer.php'; ?>