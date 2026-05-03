<?php
/**
 * AgroSecure – register_handler.php
 * Handles new farmer / user registration
 * SDG 2: Zero Hunger | MUBS Group Project
 *
 * Expected POST fields:
 * first_name, last_name, email, phone, district, role, password
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once 'db_connect.php';

$conn = getDBConnection();

// ── Sanitize ───────────────────────────────────────────────────────────────
$first_name = sanitize($conn, $_POST['first_name'] ?? '');
$last_name  = sanitize($conn, $_POST['last_name']  ?? '');
$email      = trim($_POST['email']    ?? '');
$phone      = sanitize($conn, $_POST['phone']      ?? '');
$district   = sanitize($conn, $_POST['district']   ?? '');
$role       = sanitize($conn, $_POST['role']       ?? '');
$password   = $_POST['password'] ?? '';

// ── Validate ───────────────────────────────────────────────────────────────
$errors = [];

if (empty($first_name)) $errors[] = 'First name is required.';
if (empty($last_name))  $errors[] = 'Last name is required.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (empty($phone))    $errors[] = 'Phone number is required.';
if (empty($district)) $errors[] = 'District is required.';
if (empty($role))     $errors[] = 'Please select your role.';
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}

if (!empty($errors)) {
    $conn->close();
    // Redirecting back with the validation errors
    header("Location: ../pages/login.html?status=error&message=" . urlencode(implode(' ', $errors)));
    exit;
}

// ── Check duplicate email ──────────────────────────────────────────────────
$email_safe = sanitize($conn, $email);
$check = $conn->query("SELECT id FROM users WHERE email = '$email_safe' LIMIT 1");
if ($check && $check->num_rows > 0) {
    $conn->close();
    header("Location: ../pages/login.html?status=error&message=" . urlencode("This email address is already registered. Please login instead."));
    exit;
}

// ── Hash password & insert ─────────────────────────────────────────────────
$password_hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "INSERT INTO users
     (first_name, last_name, email, phone, district, role, password_hash, is_active, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())"
);

if (!$stmt) {
    $conn->close();
    header("Location: ../pages/login.html?status=error&message=" . urlencode("Database error. Please try again."));
    exit;
}

$stmt->bind_param(
    'sssssss',
    $first_name, $last_name, $email_safe, $phone,
    $district, $role, $password_hash
);

// ── Execute and Insert User ───────────────────────────────────────────────
if ($stmt->execute()) {
    $user_id = $stmt->insert_id;
    $stmt->close();

    // Log registration activity
    $log = $conn->prepare(
        "INSERT INTO activity_log (action_type, reference_id, details, logged_at)
         VALUES ('user_register', ?, ?, NOW())"
    );
    if ($log) {
        $details = "New user: $first_name $last_name ($role) from $district";
        $log->bind_param('is', $user_id, $details);
        $log->execute();
        $log->close();
    }

    $conn->close();
    
    // Redirect to login page with a success message in the URL
    header("Location: ../pages/login.html?status=success&message=" . urlencode("Account created successfully! Please log in below."));
    exit;
} else {
    $stmt->close();
    $conn->close();
    
    // Redirect back to registration page with an error message
    header("Location: ../pages/login.html?status=error&message=" . urlencode("Registration failed. Please try again."));
    exit;
}
?>