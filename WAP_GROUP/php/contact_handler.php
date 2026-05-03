<?php
/**
 * AgroSecure – contact_handler.php
 * Processes the Contact Us form submission
 * SDG 2: Zero Hunger | MUBS Group Project
 */

// 1. Remove or comment out the JSON header line:
// header('Content-Type: application/json');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header("Location: ../pages/contact.html?status=error&message=" . urlencode('Method not allowed.'));
    exit;
}

require_once 'db_connect.php';

$conn = getDBConnection();

// ── Collect & sanitize inputs ──────────────────────────────────────────────
$first_name = sanitize($conn, $_POST['first_name'] ?? '');
$last_name  = sanitize($conn, $_POST['last_name']  ?? '');
$email      = sanitize($conn, $_POST['email']      ?? '');
$phone      = sanitize($conn, $_POST['phone']      ?? '');
$subject    = sanitize($conn, $_POST['subject']    ?? '');
$message    = sanitize($conn, $_POST['message']    ?? '');

// ── Server-side validation ─────────────────────────────────────────────────
$errors = [];

if (empty($first_name)) $errors[] = 'First name is required.';
if (empty($last_name))  $errors[] = 'Last name is required.';
if (empty($email) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (empty($subject)) $errors[] = 'Subject is required.';
if (empty($message)) $errors[] = 'Message is required.';
if (strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';

if (!empty($errors)) {
    $conn->close();
    $error_msg = urlencode(implode(' ', $errors));
    // Redirect back to contact.html with validation errors
    header("Location: ../pages/contact.html?status=error&message=" . $error_msg);
    exit;
}

// ── Insert into contact_messages table ────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO contact_messages
     (first_name, last_name, email, phone, subject, message, submitted_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())"
);

if (!$stmt) {
    $conn->close();
    header("Location: ../pages/contact.html?status=error&message=" . urlencode('Database error. Please try again.'));
    exit;
}

$stmt->bind_param('ssssss', $first_name, $last_name, $email, $phone, $subject, $message);

if ($stmt->execute()) {
    $inserted_id = $stmt->insert_id;
    $stmt->close();
    $conn->close();

    // Redirect to contact page with a success parameter
    header("Location: ../pages/contact.html?status=success&message=" . urlencode('Message sent successfully! We will reply within 24 hours.'));
    exit;
} else {
    $stmt->close();
    $conn->close();
    header("Location: ../pages/contact.html?status=error&message=" . urlencode('Failed to save your message. Please try again.'));
    exit;
}
?>