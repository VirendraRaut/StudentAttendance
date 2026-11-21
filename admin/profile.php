<?php

/**
 * Admin Profile & Account Settings
 * Student Attendance Management System
 */

$pageTitle = 'Admin Profile';
$currentPage = 'profile';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$adminId = (int) $_SESSION['admin_id'];

// Fetch current admin profile
$stmt = $pdo->prepare("SELECT id, username, email, full_name, status, last_login, created_at FROM admins WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    logout_admin();
    redirect('index.php');
}

// Handle Profile Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token.');
        redirect('admin/profile.php');
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (empty($fullName) || empty($email)) {
        set_flash('danger', 'Full Name and Email Address are required.');
        redirect('admin/profile.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please enter a valid email address.');
        redirect('admin/profile.php');
    }

    try {
        // Check duplicate email
        $chk = $pdo->prepare("SELECT id FROM admins WHERE email = :email AND id != :id LIMIT 1");
        $chk->execute([':email' => $email, ':id' => $adminId]);
        if ($chk->fetch()) {
            set_flash('danger', 'This email is already in use by another admin.');
            redirect('admin/profile.php');
        }

        $upStmt = $pdo->prepare("UPDATE admins SET full_name = :name, email = :email WHERE id = :id");
        $upStmt->execute([':name' => $fullName, ':email' => $email, ':id' => $adminId]);

        $_SESSION['admin_name'] = $fullName;
        $_SESSION['admin_email'] = $email;

        set_flash('success', 'Profile updated successfully.');
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        set_flash('danger', 'Unable to update profile.');
    }

    redirect('admin/profile.php');
}

// Handle Password Change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token.');
        redirect('admin/profile.php');
    }

    $currPass = $_POST['current_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $confPass = $_POST['confirm_password'] ?? '';

    if (empty($currPass) || empty($newPass) || empty($confPass)) {
        set_flash('danger', 'All password fields are required.');
        redirect('admin/profile.php');
    }

    if (strlen($newPass) < 10) {
        set_flash('danger', 'New password must be at least 10 characters long.');
        redirect('admin/profile.php');
    }

    if ($newPass !== $confPass) {
        set_flash('danger', 'New password and confirmation password do not match.');
        redirect('admin/profile.php');
    }

    try {
        // Fetch current password hash
        $pwdStmt = $pdo->prepare("SELECT password FROM admins WHERE id = :id LIMIT 1");
        $pwdStmt->execute([':id' => $adminId]);
        $currentHash = $pwdStmt->fetchColumn();

        if (!password_verify($currPass, $currentHash)) {
            set_flash('danger', 'Current password is incorrect.');
            redirect('admin/profile.php');
        }

        // Hash and update
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upPwd = $pdo->prepare("UPDATE admins SET password = :pwd WHERE id = :id");
        $upPwd->execute([':pwd' => $newHash, ':id' => $adminId]);

        set_flash('success', 'Password changed successfully. Please keep it secure.');
    } catch (PDOException $e) {
        error_log("Password update error: " . $e->getMessage());
        set_flash('danger', 'Unable to update password.');
    }

    redirect('admin/profile.php');
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header Block -->
<div class="page-header-block">
    <h1 class="page-title">Admin Profile</h1>
    <p class="page-subtitle">Manage your account details and password.</p>
</div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
    
    <!-- Account Information & Profile Edit -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Profile Information</h3>
                <div class="card-subtitle">Administrator account details</div>
            </div>
            <span class="badge badge-success">Active Administrator</span>
        </div>
        <div class="card-body">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);">
                <div class="admin-avatar" style="width: 54px; height: 54px; font-size: 22px;">
                    <?= strtoupper(substr($admin['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <h4 style="font-size: 16px; font-weight: 700;"><?= escape($admin['full_name']) ?></h4>
                    <div style="font-size: 12.5px; color: var(--text-muted);">@<?= escape($admin['username']) ?> &bull; <?= escape($admin['email']) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Last Login: <?= format_date($admin['last_login'], 'd M Y, h:i A') ?></div>
                </div>
            </div>

            <form method="POST" action="profile.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="update_profile" value="1">

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" class="form-control" value="<?= escape($admin['username']) ?>" disabled>
                    <div class="form-text">Username cannot be changed once created.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?= escape($admin['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= escape($admin['email']) ?>" required>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 8px;">
                    Save Profile Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Change Password Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Security & Password</h3>
                <div class="card-subtitle">Update your admin login password</div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="profile.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="change_password" value="1">

                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter current password" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="At least 10 characters" minlength="10" required>
                    <div class="form-text">Must contain at least 10 characters.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter new password" minlength="10" required>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 8px;">
                    Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
