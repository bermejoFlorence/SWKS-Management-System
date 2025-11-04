<?php
session_start();
include_once '../database/db_connection.php';

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Unauthorized access.');
}

$user_id    = (int)$_SESSION['user_id'];
$description = trim($_POST['description'] ?? '');
$type        = 'carousel';

if ($description === '' || !isset($_FILES['image'])) {
    http_response_code(400);
    exit('Missing fields.');
}

// --- Helper for error messages ---
function upload_err_msg(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE from form.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        default => 'Unknown upload error.'
    };
}

// --- Validate PHP upload status ---
$file = $_FILES['image'];
if (!empty($file['error'])) {
    http_response_code(400);
    exit('Upload error: ' . upload_err_msg((int)$file['error']));
}

// --- Resolve absolute upload dir safely ---
// current file is likely in /public_html/admin/...
$projectRoot = dirname(__DIR__);                 // /public_html
$upload_dir  = $projectRoot . '/uploads';        // /public_html/uploads

// Ensure dir exists & writable
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        http_response_code(500);
        exit('Server error: cannot create uploads directory.');
    }
}
if (!is_writable($upload_dir)) {
    http_response_code(500);
    exit('Server error: uploads directory not writable.');
}

// --- Check MIME using finfo ---
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']) ?: '';
finfo_close($finfo);

$allowed = ['image/jpeg','image/png','image/webp'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    exit('Invalid file type. Allowed: JPEG, PNG, WEBP.');
}

// --- Build a safe filename ---
$ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'bin'
};
$basename = bin2hex(random_bytes(8)) . '_' . time();
$filename = $basename . '.' . $ext;

$target_file = $upload_dir . '/' . $filename;
$rel_path    = 'uploads/' . $filename; // for <img src="...">

// --- Move the file ---
if (!is_uploaded_file($file['tmp_name'])) {
    http_response_code(400);
    exit('Security check failed: not an uploaded file.');
}

if (!move_uploaded_file($file['tmp_name'], $target_file)) {
    // add quick debug to error log
    error_log('move_uploaded_file failed: target=' . $target_file);
    http_response_code(500);
    exit('Image upload failed (unable to move file).');
}

// --- Insert into DB ---
$stmt = $conn->prepare("INSERT INTO web_settings (user_id, type, image_path, description, status) VALUES (?, ?, ?, ?, 'visible')");
$stmt->bind_param('isss', $user_id, $type, $rel_path, $description);

if ($stmt->execute()) {
    echo "<script>
        sessionStorage.setItem('carouselAddSuccess', '1');
        window.location.href = 'web_settings.php';
    </script>";
} else {
    // cleanup file if DB insert failed
    @unlink($target_file);
    http_response_code(500);
    echo 'Failed to insert.';
}
$stmt->close();
