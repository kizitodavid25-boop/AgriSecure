<?php
/**
 * AgroSecure – db_connect.php
 * Core Database Connection & Security Helpers
 * SDG 2: Zero Hunger | MUBS Group Project
 */

// 1. Database Credentials (Updated for XAMPP defaults)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Default XAMPP user
define('DB_PASS', '');               // Default XAMPP password is empty
define('DB_NAME', 'agrosecure_db');
define('DB_PORT', 3306);

/**
 * Establishes connection to MySQL.
 * If it fails, it displays a clean error instead of raw JSON.
 */
function getDBConnection(): mysqli {
    // Disable error reporting for a cleaner user experience
    mysqli_report(MYSQLI_REPORT_OFF); 

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        // Option B: Instead of JSON, show a clean HTML message or log it
        die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>
                <h2>System Maintenance</h2>
                <p>We are having trouble connecting to our servers. Please try again in a few minutes.</p>
             </div>");
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Sanitizes input to prevent XSS and SQL Injection.
 */
function sanitize(mysqli $conn, string $value): string {
    return $conn->real_escape_string(htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8'));
}


function redirectWithMsg(string $page, string $status, string $message): void {
    $url = $page . "?status=" . $status . "&message=" . urlencode($message);
    header("Location: " . $url);
    exit;
}
?>