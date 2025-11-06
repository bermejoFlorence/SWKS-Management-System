<?php
include 'database/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    function clean($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }

    // -------- Read & sanitize --------
    $full_name         = clean($_POST['full_name'] ?? '');
    $nickname          = clean($_POST['nickname'] ?? '');
    $ay                = clean($_POST['ay'] ?? '');
    $gender            = clean($_POST['gender'] ?? '');
    $course            = clean($_POST['course'] ?? '');
    $year_level        = clean($_POST['year_level'] ?? '');

    // Student ID: digits + dash only (keeps leading zeros); adjust regex if your format differs
    $student_id_raw    = $_POST['student_id'] ?? '';
    $student_id        = preg_replace('/[^0-9\-]/', '', $student_id_raw);

    $birthdate         = clean($_POST['birthdate'] ?? '');
    $address           = clean($_POST['address'] ?? '');
    $contact_number    = clean($_POST['contact_number'] ?? '');
    $email             = clean($_POST['email'] ?? '');
    $mother_name       = clean($_POST['mother_name'] ?? '');
    $mother_occupation = clean($_POST['mother_occupation'] ?? '');
    $father_name       = clean($_POST['father_name'] ?? '');
    $father_occupation = clean($_POST['father_occupation'] ?? '');
    $guardian          = clean($_POST['guardian'] ?? '');
    $guardian_address  = clean($_POST['guardian_address'] ?? '');
    $date_submitted    = date('Y-m-d');

    // Preferred org (single org_id from radio)
    $preferred_org     = clean($_POST['preferred_org'] ?? '');

    // -------- Basic validations --------

    // Institutional email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strcasecmp(substr($email, -13), '@cbsua.edu.ph') !== 0) {
        echo "<script>alert('Please use your institutional email ending in @cbsua.edu.ph'); window.history.back();</script>";
        exit;
    }

    // Student ID: presence + length (5–30) + simple format
    if ($student_id === '' || strlen($student_id) < 5 || strlen($student_id) > 30) {
        echo "<script>alert('Please enter a valid Student ID (5–30 characters, digits and dashes only).'); window.history.back();</script>";
        exit;
    }

    // Compute age from birthdate (server-side)
    $ts = strtotime($birthdate);
    if (!$ts) {
        echo "<script>alert('Please provide a valid birthdate.'); window.history.back();</script>";
        exit;
    }
    $ageYears = (int)floor((time() - $ts) / (365.2425 * 24 * 60 * 60));
    if ($ageYears < 18) {
        echo "<script>alert('You must be at least 18 years old to submit.'); window.history.back();</script>";
        exit;
    }
    // Trust server-computed age
    $age = $ageYears;

    // -------- Profile picture upload (optional) --------
    $profile_picture = '';
    $hasNewPicture = false;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $tmp_name = $_FILES['profile_picture']['tmp_name'];
        $original_name = basename($_FILES['profile_picture']['name']);
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        // (Optional) allowlist
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($extension, $allowed, true)) {
            echo "<script>alert('Invalid image type. Allowed: ".implode(', ', $allowed)."'); window.history.back();</script>";
            exit;
        }

        $new_filename = uniqid("profile_", true) . "." . $extension;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($tmp_name, $destination)) {
            $profile_picture = $new_filename;
            $hasNewPicture = true;
        }
    }

    // -------- Duplicate Student ID check --------
    if (isset($_POST['member_id']) && $_POST['member_id'] !== '') {
        // UPDATE: exclude self
        $member_id_for_dup = (int)$_POST['member_id'];
        $dup = $conn->prepare("SELECT member_id FROM member_details WHERE student_id = ? AND member_id <> ? LIMIT 1");
        $dup->bind_param("si", $student_id, $member_id_for_dup);
        $dup->execute(); $dup->store_result();
        if ($dup->num_rows > 0) {
            echo "<script>alert('Student ID is already in use.'); window.history.back();</script>";
            exit;
        }
        $dup->close();
    } else {
        // INSERT
        $dup = $conn->prepare("SELECT member_id FROM member_details WHERE student_id = ? LIMIT 1");
        $dup->bind_param("s", $student_id);
        $dup->execute(); $dup->store_result();
        if ($dup->num_rows > 0) {
            echo "<script>alert('Student ID is already in use.'); window.history.back();</script>";
            exit;
        }
        $dup->close();
    }

    // -------- Insert or Update --------
    if (isset($_POST['member_id']) && $_POST['member_id'] !== '') {
        // ==================== UPDATE ====================
        // ==================== UPDATE ====================
$member_id = (int)$_POST['member_id'];

// Keep old picture if no new upload
if (!$hasNewPicture) {
    $stmt_pic = $conn->prepare("SELECT profile_picture FROM member_details WHERE member_id = ?");
    $stmt_pic->bind_param("i", $member_id);
    $stmt_pic->execute();
    $res_pic = $stmt_pic->get_result();
    if ($row_pic = $res_pic->fetch_assoc()) {
        $profile_picture = $row_pic['profile_picture'];
    }
    $stmt_pic->close();
}

$stmt = $conn->prepare("UPDATE member_details SET
    full_name=?, nickname=?, ay=?, gender=?, course=?, year_level=?, student_id=?, birthdate=?, age=?, address=?,
    contact_number=?, email=?, mother_name=?, mother_occupation=?, father_name=?, father_occupation=?,
    guardian=?, guardian_address=?, profile_picture=?, preferred_org=?, date_submitted=?
    WHERE member_id=?");

// types: s s s s s s s s i s s s s s s s s s s i s i  (22 chars total)
$stmt->bind_param(
    "ssssssssissssssssssisi",
    $full_name, $nickname, $ay, $gender, $course, $year_level, $student_id, $birthdate, $age, $address,
    $contact_number, $email, $mother_name, $mother_occupation, $father_name, $father_occupation,
    $guardian, $guardian_address, $profile_picture, $preferred_org, $date_submitted,
    $member_id
);

if ($stmt->execute()) {
    header("Location: membership.php?success=print&id=$member_id");
    exit;
} else {
    echo "Error updating record: " . $stmt->error;
}
$stmt->close();


    } else {
        // ==================== INSERT ====================
        $status = 'pending';

        $stmt = $conn->prepare("INSERT INTO member_details (
            full_name, nickname, ay, gender, course, year_level, student_id, birthdate, age, address,
            contact_number, email, mother_name, mother_occupation, father_name, father_occupation,
            guardian, guardian_address, profile_picture, preferred_org, status, date_submitted
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "ssssssssssssssssssssss",
            $full_name, $nickname, $ay, $gender, $course, $year_level, $student_id, $birthdate, $age, $address,
            $contact_number, $email, $mother_name, $mother_occupation, $father_name, $father_occupation,
            $guardian, $guardian_address, $profile_picture, $preferred_org, $status, $date_submitted
        );

        if ($stmt->execute()) {
            $last_id = $stmt->insert_id;

            // ---- Notify adviser (same as your original) ----
            $adviserStmt = $conn->prepare("SELECT user_id FROM user WHERE org_id = ? AND user_role = 'adviser' LIMIT 1");
            $adviserStmt->bind_param("s", $preferred_org);
            $adviserStmt->execute();
            $adviserStmt->bind_result($adviser_user_id);
            $adviserStmt->fetch();
            $adviserStmt->close();

            if (!empty($adviser_user_id)) {
                $notifType = 'membership_form';
                $notifMsg  = "New membership application received for your organization.";
                $notifStmt = $conn->prepare(
                    "INSERT INTO notification (user_id, type, message, is_seen, created_at, org_id)
                     VALUES (?, ?, ?, 0, NOW(), ?)"
                );
                $notifStmt->bind_param("issi", $adviser_user_id, $notifType, $notifMsg, $preferred_org);
                $notifStmt->execute();
                $notifStmt->close();
            }
            // ---- End notify ----

            header("Location: membership.php?success=print&id=$last_id");
            exit;
        } else {
            echo "Error saving record: " . $stmt->error;
        }
        $stmt->close();
    }

    $conn->close();
} else {
    header("Location: membership.php");
    exit;
}
?>
