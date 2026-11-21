<?php

/**
 * Student Attendance Management System
 * Database Configuration
 * Connection Method: PDO
 */

require_once __DIR__ . '/config.php';

// Database configuration
defined('DB_HOST') or define('DB_HOST', 'localhost');
defined('DB_NAME') or define('DB_NAME', 'student_attendance');
defined('DB_USER') or define('DB_USER', 'root');
defined('DB_PASS') or define('DB_PASS', '');

// Create PDO connection if not already created
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $dsn = "mysql:host=" . DB_HOST .
               ";dbname=" . DB_NAME .
               ";charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            $options
        );

    } catch (PDOException $e) {
        // Log the actual error privately
        error_log("Database Connection Error: " . $e->getMessage());

        // In development / setup mode or display graceful message
        http_response_code(500);
        exit("Unable to connect to the database. Please make sure MySQL is running in XAMPP and database 'student_attendance' has been imported.");
    }
}
