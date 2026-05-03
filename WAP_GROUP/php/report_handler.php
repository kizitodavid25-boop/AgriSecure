<?php


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header("Location: ../pages/report.html?status=error&message=" . urlencode('Method not allowed.'));
    exit;
}

require_once 'db_connect.php';

$conn = getDBConnection();

// ── Collect & sanitize ─────────────────────────────────────────────────────
$reporter_name       = sanitize($conn, $_POST['reporter_name']       ?? '');
$reporter_phone      = sanitize($conn, $_POST['reporter_phone']      ?? '');
$district            = sanitize($conn, $_POST['district']            ?? '');
$village             = sanitize($conn, $_POST['village']             ?? '');
$crisis_type         = sanitize($conn, $_POST['crisis_type']         ?? '');
$severity            = sanitize($conn, $_POST['severity']            ?? '');
$households_affected = (int)($_POST['households_affected']           ?? 0);
$description         = sanitize($conn, $_POST['description']         ?? '');
$email               = sanitize($conn, $_POST['email']               ?? '');

// ── Validate ───────────────────────────────────────────────────────────────
$errors = [];
$valid_severities = ['low', 'medium', 'high'];

if (empty($reporter_name))  $errors[] = 'Reporter name is required.';
if (empty($reporter_phone)) $errors[] = 'Phone number is required.';
if (empty($district))       $errors[] = 'District is required.';
if (empty($crisis_type))    $errors[] = 'Crisis type is required.';
if (empty($severity) || !in_array($severity, $valid_severities)) {
    $errors[] = 'Please select a valid severity level.';
}
if (empty($description) || strlen($description) < 15) {
    $errors[] = 'Please provide a description of at least 15 characters.';
}
if (!empty($email) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}

if (!empty($errors)) {
    $conn->close();
    $error_msg = urlencode(implode(' ', $errors));
    header("Location: ../pages/report.html?status=error&message=" . $error_msg);
    exit;
}

// ── Insert into crisis_reports table ──────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO crisis_reports
     (reporter_name, reporter_phone, district, village, crisis_type,
      severity, households_affected, description, email, status, reported_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
);

if (!$stmt) {
    $conn->close();
    header("Location: ../pages/report.html?status=error&message=" . urlencode('Database error. Please try again.'));
    exit;
}

$stmt->bind_param(
    'ssssssiss',
    $reporter_name, $reporter_phone, $district, $village,
    $crisis_type, $severity, $households_affected,
    $description, $email
);

if ($stmt->execute()) {
    $report_id = $stmt->insert_id;
    $stmt->close();

    // Log in activity table
    $log = $conn->prepare(
        "INSERT INTO activity_log (action_type, reference_id, details, logged_at)
         VALUES ('crisis_report', ?, ?, NOW())"
    );
    if ($log) {
        $details = "Crisis report: $crisis_type in $district (Severity: $severity)";
        $log->bind_param('is', $report_id, $details);
        $log->execute();
        $log->close();
    }

    $conn->close();
    
    // Redirect back to report.html with success
    header("Location: ../pages/report.html?status=success&message=" . urlencode('Crisis report submitted successfully! Our team will contact you within 12 hours.'));
    exit;
} else {
    $stmt->close();
    $conn->close();
    header("Location: ../pages/report.html?status=error&message=" . urlencode('Failed to submit your report. Please try again.'));
    exit;
}
?>