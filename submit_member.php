<?php
include 'database/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    function clean($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }

    // Sanitize form data
    $full_name         = clean($_POST['full_name']);
    $nickname          = clean($_POST['nickname']);
    $ay                = clean($_POST['ay']);
    $gender            = clean($_POST['gender']);
    $course            = clean($_POST['course']);
    $year_level        = clean($_POST['year_level']);
    $birthdate         = clean($_POST['birthdate']);
    $age               = intval($_POST['age']);
    $address           = clean($_POST['address']);
    $contact_number    = clean($_POST['contact_number']);
    $email             = clean($_POST['email']);
    if (strtolower(substr($email, -13)) !== '@cbsua.edu.ph') {
        echo "<script>alert('Please use your institutional email ending in @cbsua.edu.ph'); window.history.back();</script>";
        exit;
    }
    $mother_name       = clean($_POST['mother_name']);
    $mother_occupation = clean($_POST['mother_occupation']);
    $father_name       = clean($_POST['father_name']);
    $father_occupation = clean($_POST['father_occupation']);
    $guardian          = clean($_POST['guardian']);
    $guardian_address  = clean($_POST['guardian_address']);
    $date_submitted    = date('Y-m-d');

    // Preferred org (single org_id from radio)
    $preferred_org = '';
    if (isset($_POST['preferred_org'])) {
        $preferred_org = clean($_POST['preferred_org']); // This should be org_id
    }

    // Handle profile picture upload
    $profile_picture = '';
    $hasNewPicture = false;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $tmp_name = $_FILES['profile_picture']['tmp_name'];
        $original_name = basename($_FILES['profile_picture']['name']);
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $new_filename = uniqid("profile_", true) . "." . $extension;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($tmp_name, $destination)) {
            $profile_picture = $new_filename;
            $hasNewPicture = true;
        }
    }

    // Check if update or insert
    if (isset($_POST['member_id']) && !empty($_POST['member_id'])) {
        // --------- UPDATE ---------
        $member_id = intval($_POST['member_id']);

        // Get old profile picture filename if no new upload
        if (!$hasNewPicture) {
            $stmt_pic = $conn->prepare("SELECT profile_picture FROM member_details WHERE member_id = ?");
            $stmt_pic->bind_param("i", $member_id);
            $stmt_pic->execute();
            $res_pic = $stmt_pic->get_result();
            $row_pic = $res_pic->fetch_assoc();
            $profile_picture = $row_pic['profile_picture'];
            $stmt_pic->close();
        }

        $stmt = $conn->prepare("UPDATE member_details SET
            full_name=?, nickname=?, ay=?, gender=?, course=?, year_level=?, birthdate=?, age=?, address=?,
            contact_number=?, email=?, mother_name=?, mother_occupation=?, father_name=?, father_occupation=?,
            guardian=?, guardian_address=?, profile_picture=?, preferred_org=?, date_submitted=?
            WHERE member_id=?");

        $stmt->bind_param("ssssssssssssssssssssi",
            $full_name, $nickname, $ay, $gender, $course, $year_level, $birthdate, $age, $address,
            $contact_number, $email, $mother_name, $mother_occupation, $father_name, $father_occupation,
            $guardian, $guardian_address, $profile_picture, $preferred_org, $date_submitted, $member_id
        );

        if ($stmt->execute()) {
            header("Location: membership.php?success=print&id=$member_id");
            exit;
        } else {
            echo "Error updating record: " . $stmt->error;
        }

        $stmt->close();

    } else {
        // --------- INSERT ---------
        $status = 'pending'; // default for new applicant

        $stmt = $conn->prepare("INSERT INTO member_details (
            full_name, nickname, ay, gender, course, year_level, birthdate, age, address,
            contact_number, email, mother_name, mother_occupation, father_name, father_occupation,
            guardian, guardian_address, profile_picture, preferred_org, status, date_submitted
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("sssssssssssssssssssss",
            $full_name, $nickname, $ay, $gender, $course, $year_level, $birthdate, $age, $address,
            $contact_number, $email, $mother_name, $mother_occupation, $father_name, $father_occupation,
            $guardian, $guardian_address, $profile_picture, $preferred_org, $status, $date_submitted
        );

        if ($stmt->execute()) {
            $last_id = $stmt->insert_id;

            // ---- NOTIFICATION TO ADVISER ----
            $adviserStmt = $conn->prepare("SELECT user_id FROM user WHERE org_id = ? AND user_role = 'adviser' LIMIT 1");
            $adviserStmt->bind_param("i", $preferred_org);
            $adviserStmt->execute();
            $adviserStmt->bind_result($adviser_user_id);
            $adviserStmt->fetch();
            $adviserStmt->close();

            // Kung may adviser, notify adviser
            if ($adviser_user_id) {
                $notifType = 'membership_form';
                $notifMsg = "New membership application received for your organization.";
                $notifStmt = $conn->prepare(
                    "INSERT INTO notification (user_id, type, message, is_seen, created_at, org_id)
                    VALUES (?, ?, ?, 0, NOW(), ?)"
                );
                $notifStmt->bind_param("issi", $adviser_user_id, $notifType, $notifMsg, $preferred_org);
                $notifStmt->execute();
                $notifStmt->close();
            }
            // ---- END NOTIFICATION ----

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
