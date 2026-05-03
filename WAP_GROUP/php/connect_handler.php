<?php
/**
 * AgroSecure – connect_handler.php
 * The central Authentication & Session Controller
 */

session_start();
require_once 'db_connect.php';
$conn = getDBConnection();

// --- 1. HANDLE LOGOUT CONNECTION ---
// Triggers if you link to: ../php/connect_handler.php?action=logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: ../pages/login.html?status=success&message=" . urlencode('Logged out successfully.'));
    exit;
}

// --- 2. HANDLE LOGIN CONNECTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email    = sanitize($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; 

    // Basic Validation
    if (empty($email) || empty($password)) {
        header("Location: ../pages/login.html?status=error&message=" . urlencode('All fields are required.'));
        exit;
    }

    // Database Lookup
    $stmt = $conn->prepare("SELECT id, first_name, last_name, password_hash, role, district FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify Password
        if (password_verify($password, $user['password_hash'])) {
            
            // Set Session Data (This "connects" the user to the whole site)
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role']     = $user['role'];
            $_SESSION['user_district'] = $user['district'];
            $_SESSION['last_login']    = time();

            // Log the successful connection in activity_log
            $log = $conn->prepare("INSERT INTO activity_log (action_type, reference_id, details) VALUES ('user_login', ?, 'User logged in successfully')");
            $log->bind_param('i', $user['id']);
            $log->execute();

            $stmt->close();
            $conn->close();

          // --- 3. ROLE-BASED REDIRECTION ---
          // Now passing the status=login_success flag to the dashboard URL
            if ($user['role'] === 'admin') {
               header("Location: ../pages/admin_crud.php?status=login_success");
            } else {
                  header("Location: ../pages/dashboard.php?status=login_success");
            }
            exit;
        }
    }

    // Connection Failed
    $conn->close();
    header("Location: ../pages/login.html?status=error&message=" . urlencode('Invalid credentials. Please try again.'));
    exit;

} else {
    // If someone tries to access this file directly without POST or GET action
    header("Location: ../pages/login.html");
    exit;
}