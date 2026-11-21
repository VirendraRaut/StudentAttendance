<?php

/**
 * Student Attendance Management System
 * Application Configuration
 */

// Prevent multiple definitions
if (!defined('APP_INIT')) {
    define('APP_INIT', true);

    // Error reporting - set to 0 in production
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);

    // Application details
    define('APP_NAME', 'Student Attendance Management System');
    define('APP_SHORT_NAME', 'AttendanceHub');
    define('APP_VERSION', '1.0.0');

    // Timezone
    date_default_timezone_set('Asia/Kolkata');

    // Base URL detection
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Auto-detect base folder path
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // If in admin or exports subdirectory, go up one level
    $basePath = preg_replace('/(\/admin|\/exports|\/config|\/includes)$/', '', $scriptDir);
    $basePath = rtrim($basePath, '/');

    define('BASE_URL', $protocol . $host . $basePath);
    define('ROOT_PATH', dirname(__DIR__));

    // Session settings
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        // Optional: ini_set('session.cookie_secure', 1); // Enable if HTTPS
        session_name('SAMS_SESSION');
        session_start();
    }
}
