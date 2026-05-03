<?php
/**
 * AgroSecure – admin_handler.php
 * Central API handler for Admin CRUD operations.
 * All responses are JSON.
 * SDG 2: Zero Hunger | MUBS Group Project
 *
 * Actions (POST):
 *   insert_user, update_user, delete_user
 *   insert_report, update_report, delete_report
 *   insert_contact, update_contact, delete_contact
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ── Auth guard ────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

// ── Only accept POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once 'db_connect.php';
$conn   = getDBConnection();
$action = trim($_GET['action'] ?? '');

// ── Route ─────────────────────────────────────────────────────────────────
switch ($action) {

    // ════════════════════════════
    // USER ACTIONS
    // ════════════════════════════

    case 'insert_user':
        $first_name = sanitize($conn, $_POST['first_name'] ?? '');
        $last_name  = sanitize($conn, $_POST['last_name']  ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = sanitize($conn, $_POST['phone']      ?? '');
        $district   = sanitize($conn, $_POST['district']   ?? '');
        $role       = sanitize($conn, $_POST['role']       ?? '');
        $password   = $_POST['password'] ?? '';
        $is_active  = (int)($_POST['is_active'] ?? 1);

        // Validate
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($district) || empty($role)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
            break;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            break;
        }
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            break;
        }

        // Check duplicate email
        $email_safe = sanitize($conn, $email);
        $chk = $conn->query("SELECT id FROM users WHERE email = '$email_safe' LIMIT 1");
        if ($chk && $chk->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already registered.']);
            break;
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare(
            "INSERT INTO users (first_name, last_name, email, phone, district, role, password_hash, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param('sssssssi', $first_name, $last_name, $email_safe, $phone, $district, $role, $password_hash, $is_active);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            echo json_encode([
                'success' => true,
                'message' => "User \"{$first_name} {$last_name}\" inserted successfully!",
                'id'      => $new_id,
                'created_at' => date('Y-m-d'),
            ]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error inserting user.']);
        }
        break;

    case 'update_user':
        $id         = (int)($_POST['id'] ?? 0);
        $first_name = sanitize($conn, $_POST['first_name'] ?? '');
        $last_name  = sanitize($conn, $_POST['last_name']  ?? '');
        $email      = sanitize($conn, $_POST['email']      ?? '');
        $phone      = sanitize($conn, $_POST['phone']      ?? '');
        $district   = sanitize($conn, $_POST['district']   ?? '');
        $role       = sanitize($conn, $_POST['role']       ?? '');
        $is_active  = (int)($_POST['is_active'] ?? 1);

        if ($id < 1 || empty($first_name) || empty($last_name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
            break;
        }

        $stmt = $conn->prepare(
            "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, district=?, role=?, is_active=?
             WHERE id=?"
        );
        $stmt->bind_param('ssssssis', $first_name, $last_name, $email, $phone, $district, $role, $is_active, $id);

        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => "User \"{$first_name} {$last_name}\" updated."]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error updating user.']);
        }
        break;

    case 'delete_user':
        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            break;
        }
        // Prevent admin from deleting themselves
        if ($id === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
            break;
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $stmt->close();
            // Also clean up their resources & activity logs
            $conn->query("DELETE FROM user_resources WHERE user_id = $id");
            echo json_encode(['success' => true, 'message' => 'User deleted.']);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error deleting user.']);
        }
        break;

    // ════════════════════════════
    // CRISIS REPORT ACTIONS
    // ════════════════════════════

    case 'insert_report':
        $reporter_name       = sanitize($conn, $_POST['reporter_name']       ?? '');
        $reporter_phone      = sanitize($conn, $_POST['reporter_phone']      ?? '');
        $district            = sanitize($conn, $_POST['district']            ?? '');
        $village             = sanitize($conn, $_POST['village']             ?? '');
        $crisis_type         = sanitize($conn, $_POST['crisis_type']         ?? '');
        $severity            = sanitize($conn, $_POST['severity']            ?? '');
        $households_affected = (int)($_POST['households_affected']           ?? 0);
        $description         = sanitize($conn, $_POST['description']         ?? '');
        $email               = sanitize($conn, $_POST['email']               ?? '');
        $status              = sanitize($conn, $_POST['status']              ?? 'pending');

        $valid_severities = ['low', 'medium', 'high'];
        $valid_statuses   = ['pending', 'verified', 'resolved', 'dismissed'];

        if (empty($reporter_name) || empty($reporter_phone) || empty($district) || empty($crisis_type)) {
            echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
            break;
        }
        if (!in_array($severity, $valid_severities)) {
            echo json_encode(['success' => false, 'message' => 'Invalid severity level.']);
            break;
        }
        if (!in_array($status, $valid_statuses)) {
            $status = 'pending';
        }

        $stmt = $conn->prepare(
            "INSERT INTO crisis_reports
             (reporter_name, reporter_phone, district, village, crisis_type, severity,
              households_affected, description, email, status, reported_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param('ssssssissa',
            $reporter_name, $reporter_phone, $district, $village,
            $crisis_type, $severity, $households_affected,
            $description, $email, $status
        );

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            echo json_encode([
                'success'    => true,
                'message'    => "Crisis report by \"{$reporter_name}\" inserted!",
                'id'         => $new_id,
                'reported_at'=> date('Y-m-d'),
            ]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error inserting report.']);
        }
        break;

    case 'update_report':
        $id                  = (int)($_POST['id'] ?? 0);
        $reporter_name       = sanitize($conn, $_POST['reporter_name']       ?? '');
        $reporter_phone      = sanitize($conn, $_POST['reporter_phone']      ?? '');
        $district            = sanitize($conn, $_POST['district']            ?? '');
        $village             = sanitize($conn, $_POST['village']             ?? '');
        $crisis_type         = sanitize($conn, $_POST['crisis_type']         ?? '');
        $severity            = sanitize($conn, $_POST['severity']            ?? '');
        $households_affected = (int)($_POST['households_affected']           ?? 0);
        $description         = sanitize($conn, $_POST['description']         ?? '');
        $status              = sanitize($conn, $_POST['status']              ?? 'pending');

        if ($id < 1 || empty($reporter_name) || empty($district) || empty($crisis_type)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
            break;
        }

        $stmt = $conn->prepare(
            "UPDATE crisis_reports SET
             reporter_name=?, reporter_phone=?, district=?, village=?,
             crisis_type=?, severity=?, households_affected=?, description=?, status=?
             WHERE id=?"
        );
        $stmt->bind_param('ssssssissi',
            $reporter_name, $reporter_phone, $district, $village,
            $crisis_type, $severity, $households_affected, $description, $status, $id
        );

        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => "Report #{$id} updated."]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error updating report.']);
        }
        break;

    case 'delete_report':
        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid report ID.']);
            break;
        }

        $stmt = $conn->prepare("DELETE FROM crisis_reports WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Report deleted.']);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error deleting report.']);
        }
        break;

    // ════════════════════════════
    // CONTACT MESSAGE ACTIONS
    // ════════════════════════════

    case 'insert_contact':
        $first_name  = sanitize($conn, $_POST['first_name']  ?? '');
        $last_name   = sanitize($conn, $_POST['last_name']   ?? '');
        $email       = sanitize($conn, $_POST['email']       ?? '');
        $phone       = sanitize($conn, $_POST['phone']       ?? '');
        $subject     = sanitize($conn, $_POST['subject']     ?? '');
        $message     = sanitize($conn, $_POST['message']     ?? '');
        $read_status = sanitize($conn, $_POST['read_status'] ?? 'unread');

        if (empty($first_name) || empty($last_name) || empty($email) || empty($subject) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
            break;
        }

        $stmt = $conn->prepare(
            "INSERT INTO contact_messages (first_name, last_name, email, phone, subject, message, read_status, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param('sssssss', $first_name, $last_name, $email, $phone, $subject, $message, $read_status);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            $stmt->close();
            echo json_encode([
                'success'      => true,
                'message'      => "Message from \"{$first_name} {$last_name}\" inserted!",
                'id'           => $new_id,
                'submitted_at' => date('Y-m-d'),
            ]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error inserting message.']);
        }
        break;

    case 'update_contact':
        $id          = (int)($_POST['id'] ?? 0);
        $first_name  = sanitize($conn, $_POST['first_name']  ?? '');
        $last_name   = sanitize($conn, $_POST['last_name']   ?? '');
        $email       = sanitize($conn, $_POST['email']       ?? '');
        $phone       = sanitize($conn, $_POST['phone']       ?? '');
        $subject     = sanitize($conn, $_POST['subject']     ?? '');
        $message     = sanitize($conn, $_POST['message']     ?? '');
        $read_status = sanitize($conn, $_POST['read_status'] ?? 'unread');

        if ($id < 1 || empty($first_name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
            break;
        }

        $stmt = $conn->prepare(
            "UPDATE contact_messages SET
             first_name=?, last_name=?, email=?, phone=?, subject=?, message=?, read_status=?
             WHERE id=?"
        );
        $stmt->bind_param('sssssssi', $first_name, $last_name, $email, $phone, $subject, $message, $read_status, $id);

        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => "Message #{$id} updated."]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error updating message.']);
        }
        break;

    case 'delete_contact':
        $id = (int)($_POST['id'] ?? 0);
        if ($id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid message ID.']);
            break;
        }

        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Message deleted.']);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error deleting message.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}

$conn->close();
?>