<?php

/**
 * Admin Login
 * Student Attendance Management System
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect straight to dashboard
if (is_admin_logged_in()) {
    redirect('admin/dashboard.php');
}

$errorMessage = '';
$successMessage = '';

// Check URL status notices
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'logged_out') {
        $successMessage = 'You have been successfully logged out.';
    } elseif ($_GET['msg'] === 'login_required') {
        $errorMessage = 'Please sign in to access the administration portal.';
    }
}

// Check if any admin account exists in database
$adminCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    $adminCount = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error checking admins: " . $e->getMessage());
}

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrfToken  = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'Invalid or expired session security token. Please try again.';
    } elseif (empty($identifier) || empty($password)) {
        $errorMessage = 'Please enter both your username/email and password.';
    } else {
        $loginResult = login_admin($pdo, $identifier, $password);
        if ($loginResult['success']) {
            redirect('admin/dashboard.php');
        } else {
            $errorMessage = $loginResult['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= escape(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.15);
        }

        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-badge {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, var(--primary), #3b82f6);
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #ffffff;
            margin-bottom: 14px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
        }

        .login-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.3px;
        }

        .login-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .setup-alert {
            background-color: var(--warning-light);
            border: 1px solid var(--warning-border);
            color: var(--warning-dark);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .setup-alert a {
            color: var(--primary-dark);
            font-weight: 700;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-brand">
        <div class="brand-badge">🎓</div>
        <h1 class="login-title"><?= escape(APP_SHORT_NAME) ?></h1>
        <p class="login-subtitle">Admin Authentication Portal</p>
    </div>

    <?php if ($adminCount === 0): ?>
        <div class="setup-alert">
            ℹ️ <strong>Initial Setup Required:</strong> No administrator accounts found in the database.
            <br>
            Please <a href="setup-admin.php">click here to run Admin Setup</a> to create the first administrator.
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <span><?= escape($errorMessage) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <span><?= escape($successMessage) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php" autocomplete="off">
        <?= csrf_input() ?>

        <div class="form-group">
            <label class="form-label" for="identifier">Username or Email Address</label>
            <input 
                type="text" 
                id="identifier" 
                name="identifier" 
                class="form-control" 
                placeholder="admin or admin@college.edu" 
                required 
                autofocus
                value="<?= isset($_POST['identifier']) ? escape($_POST['identifier']) : '' ?>"
            >
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label" for="password">Password</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-control" 
                placeholder="Enter your admin password" 
                required
            >
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 14.5px;">
            Sign In to Dashboard
        </button>
    </form>

    <div style="text-align: center; margin-top: 26px; font-size: 12px; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 16px;">
        Student Attendance Management System &bull; Strictly Admin Only
    </div>
</div>

</body>
</html>
