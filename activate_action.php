<?php
include 'database/db_connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Step 1: Lookup adviser or member
    $stmt = $conn->prepare("
        SELECT member_id AS id, 'member' AS role FROM member_details WHERE email = ? AND status = 'approved'
        UNION
        SELECT user_id AS id, 'adviser' AS role FROM adviser_details WHERE adviser_email = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!($user = $result->fetch_assoc())) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid or unauthorized activation."
        ]);
        exit;
    }

    $entity_id = $user['id'];
    $user_role = $user['role'];

    // Step 2: Get corresponding user record and check if already activated
    $check = $conn->prepare("SELECT user_id, user_password FROM user WHERE user_email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    $res = $check->get_result();

    if (!($userRow = $res->fetch_assoc())) {
        echo json_encode([
            "status" => "error",
            "message" => "Account record not found."
        ]);
        exit;
    }

    if (!empty($userRow['user_password'])) {
        echo json_encode([
            "status" => "error",
            "message" => "Account already activated."
        ]);
        exit;
    }

    $user_id = $userRow['user_id'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // -- CHANGES START HERE --
    if ($user_role === 'member') {
        // Get the member's organization
        $stmt_org = $conn->prepare("SELECT preferred_org FROM member_details WHERE member_id = ?");
        $stmt_org->bind_param("i", $entity_id);
        $stmt_org->execute();
        $res_org = $stmt_org->get_result();
        $org_row = $res_org->fetch_assoc();
        $preferred_org = $org_row ? intval($org_row['preferred_org']) : null;
        $stmt_org->close();

        if (!$preferred_org) {
            echo json_encode([
                "status" => "error",
                "message" => "Organization not found for this member."
            ]);
            exit;
        }

        // Update password, set user_role to 'member', and set org_id
        $update = $conn->prepare("UPDATE user SET user_password = ?, user_role = 'member', org_id = ? WHERE user_id = ?");
        $update->bind_param("sii", $hashed_password, $preferred_org, $user_id);

    } else {
        // For adviser: do NOT touch org or user_role, just set password
        $update = $conn->prepare("UPDATE user SET user_password = ? WHERE user_id = ?");
        $update->bind_param("si", $hashed_password, $user_id);
    }
    // -- CHANGES END HERE --

    $success = $update->execute();

    if ($success) {
        echo json_encode([
            "status" => "success",
            "message" => "Account activated! You may now log in."
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to activate account. Please try again."
        ]);
    }
    exit;
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
    exit;
}
?>
