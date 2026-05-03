<?php
/**
 * AgroSecure – resource_tracker.php
 * Records when a logged-in user views or saves a resource.
 * SDG 2: Zero Hunger | MUBS Group Project
 *
 * Expected POST fields:
 *   resource_id  (int)    – the resource's ID (1–6 matching resources.html cards)
 *   action       (string) – 'viewed' | 'saved'
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Must be logged in — guests are silently ignored (no error, so page still works)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

require_once 'db_connect.php';
$conn = getDBConnection();

$user_id     = (int) $_SESSION['user_id'];
$resource_id = (int) ($_POST['resource_id'] ?? 0);
$action      = trim($_POST['action'] ?? '');

// Validate
$valid_actions = ['viewed', 'saved'];
if ($resource_id < 1 || !in_array($action, $valid_actions)) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

// If action is 'viewed', avoid duplicate entries for the same session
// (only insert if this user hasn't viewed this resource before)
if ($action === 'viewed') {
    $check = $conn->prepare(
        "SELECT id FROM user_resources WHERE user_id = ? AND resource_id = ? AND action = 'viewed' LIMIT 1"
    );
    $check->bind_param('ii', $user_id, $resource_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Already recorded.']);
        exit;
    }
    $check->close();
}

// Insert the record
$stmt = $conn->prepare(
    "INSERT INTO user_resources (user_id, resource_id, action, acted_at)
     VALUES (?, ?, ?, NOW())"
);
$stmt->bind_param('iis', $user_id, $resource_id, $action);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'message' => ucfirst($action) . ' recorded.']);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>