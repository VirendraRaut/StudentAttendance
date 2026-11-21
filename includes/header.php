<?php

/**
 * Admin Layout Header
 * Student Attendance Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Enforce admin login on all layout pages
require_admin_login();

$currentAdmin = get_logged_in_admin($pdo);
$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? 'dashboard';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= escape($pageTitle) ?> — <?= escape(APP_NAME) ?></title>

    <!-- Pure CSS3 Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    
    <?php if (isset($extraStyles)): ?>
        <?= $extraStyles ?>
    <?php endif; ?>
</head>
<body>

<div class="app-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="main-wrapper">
        <!-- Top Navigation Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="toggle-sidebar-btn" id="toggleSidebarBtn" aria-label="Toggle Sidebar">
                    ☰
                </button>
                <h1 class="page-title"><?= escape($pageTitle) ?></h1>
            </div>

            <div class="topbar-right">
                <div class="topbar-date">
                    📅 <?= date('l, d F Y') ?>
                </div>

                <div style="position: relative;">
                    <button type="button" class="user-menu-btn" id="userMenuBtn">
                        <div class="admin-avatar" style="width: 32px; height: 32px; font-size: 13px;">
                            <?= strtoupper(substr($currentAdmin['full_name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <span><?= escape($currentAdmin['full_name'] ?? 'Admin') ?></span>
                        <span style="font-size: 10px;">▼</span>
                    </button>

                    <div id="userDropdown" style="display: none; position: absolute; right: 0; top: 110%; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); width: 190px; z-index: 200; overflow: hidden;">
                        <div style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); background: #f8fafc;">
                            <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);"><?= escape($currentAdmin['full_name'] ?? 'Administrator') ?></div>
                            <div style="font-size: 11px; color: var(--text-muted);"><?= escape($currentAdmin['email'] ?? '') ?></div>
                        </div>
                        <a href="<?= BASE_URL ?>/admin/profile.php" style="display: block; padding: 10px 16px; color: var(--text-primary); font-size: 13px; text-decoration: none;">👤 My Profile</a>
                        <a href="<?= BASE_URL ?>/logout.php" style="display: block; padding: 10px 16px; color: var(--danger); font-size: 13px; text-decoration: none; border-top: 1px solid var(--border-color);">🚪 Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <style>
            #userDropdown.active {
                display: block !important;
            }
        </style>

        <!-- Main Body Content -->
        <main class="content-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?= escape($flash['type']) ?>">
                    <span><?= escape($flash['message']) ?></span>
                    <button type="button" class="alert-close">&times;</button>
                </div>
            <?php endif; ?>
