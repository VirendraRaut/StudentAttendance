<?php

/**
 * Authentication and Session Management
 * Student Attendance Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Check if an admin is currently logged in and active
 */
function is_admin_logged_in(): bool {
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        return false;
    }

    // Check last activity timeout (e.g., 2 hours)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
        logout_admin();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Enforce admin login on protected pages
 */
function require_admin_login(): void {
    if (!is_admin_logged_in()) {
        header("Location: " . BASE_URL . "/index.php?msg=login_required");
        exit;
    }
}

/**
 * Attempt to authenticate admin with username or email
 */
function login_admin(PDO $pdo, string $identifier, string $password): array {
    $identifier = trim($identifier);

    if (empty($identifier) || empty($password)) {
        return ['success' => false, 'message' => 'Please enter both username/email and password.'];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, username, email, password, full_name, status 
             FROM admins 
             WHERE username = :u_user OR email = :u_email 
             LIMIT 1"
        );
        $stmt->execute([
            ':u_user'  => $identifier,
            ':u_email' => $identifier
        ]);
        $admin = $stmt->fetch();

        if (!$admin) {
            return ['success' => false, 'message' => 'Invalid username/email or password.'];
        }

        if ($admin['status'] !== 'Active') {
            return ['success' => false, 'message' => 'Your admin account is inactive. Please contact the system administrator.'];
        }

        if (!password_verify($password, $admin['password'])) {
            return ['success' => false, 'message' => 'Invalid username/email or password.'];
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['last_activity'] = time();

        // Update last login timestamp
        $updateStmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $admin['id']]);

        return ['success' => true, 'message' => 'Login successful.'];

    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during login. Please try again.'];
    }
}

/**
 * Log out current admin
 */
function logout_admin(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Get current logged in admin data
 */
function get_logged_in_admin(PDO $pdo): ?array {
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, status, last_login FROM admins WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        return $admin ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Render hidden CSRF input field
 */
function csrf_input(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
