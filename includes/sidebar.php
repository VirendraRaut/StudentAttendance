<?php

/**
 * Admin Sidebar Navigation
 * Student Attendance Management System
 */

$currentPage = $currentPage ?? 'dashboard';
?>

<aside class="sidebar" id="sidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
        <div class="sidebar-brand-icon">
            🎓
        </div>
        <div>
            <div class="sidebar-brand-title"><?= escape(APP_SHORT_NAME) ?></div>
            <div class="sidebar-brand-subtitle">Admin Portal</div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="sidebar-icon">📊</span>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="sidebar-section-title">Academic Structure</li>
        
        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/departments.php" class="sidebar-link <?= $currentPage === 'departments' ? 'active' : '' ?>">
                <span class="sidebar-icon">🏛️</span>
                <span>Departments</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/sections.php" class="sidebar-link <?= $currentPage === 'sections' ? 'active' : '' ?>">
                <span class="sidebar-icon">📑</span>
                <span>Sections</span>
            </a>
        </li>

        <li class="sidebar-section-title">People</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/teachers.php" class="sidebar-link <?= $currentPage === 'teachers' ? 'active' : '' ?>">
                <span class="sidebar-icon">👨‍🏫</span>
                <span>Teachers</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/students.php" class="sidebar-link <?= $currentPage === 'students' ? 'active' : '' ?>">
                <span class="sidebar-icon">👨‍🎓</span>
                <span>Students</span>
            </a>
        </li>

        <li class="sidebar-section-title">Curriculum</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/subjects.php" class="sidebar-link <?= $currentPage === 'subjects' ? 'active' : '' ?>">
                <span class="sidebar-icon">📚</span>
                <span>Subjects</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/student-subjects.php" class="sidebar-link <?= $currentPage === 'student_subjects' ? 'active' : '' ?>">
                <span class="sidebar-icon">🔗</span>
                <span>Student Subjects</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/teacher-subjects.php" class="sidebar-link <?= $currentPage === 'teacher_subjects' ? 'active' : '' ?>">
                <span class="sidebar-icon">📋</span>
                <span>Teacher Subjects</span>
            </a>
        </li>

        <li class="sidebar-section-title">Attendance</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/attendance.php" class="sidebar-link <?= $currentPage === 'attendance' ? 'active' : '' ?>">
                <span class="sidebar-icon">✍️</span>
                <span>Mark Attendance</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/attendance-history.php" class="sidebar-link <?= $currentPage === 'attendance_history' ? 'active' : '' ?>">
                <span class="sidebar-icon">🕒</span>
                <span>Attendance History</span>
            </a>
        </li>

        <li class="sidebar-section-title">Analytics</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/reports.php" class="sidebar-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
                <span class="sidebar-icon">📈</span>
                <span>Attendance Reports</span>
            </a>
        </li>

        <li class="sidebar-section-title">System</li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/admin/profile.php" class="sidebar-link <?= $currentPage === 'profile' ? 'active' : '' ?>">
                <span class="sidebar-icon">⚙️</span>
                <span>Admin Profile</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link" style="color: #f87171;">
                <span class="sidebar-icon">🚪</span>
                <span>Logout</span>
            </a>
        </li>
    </ul>

    <!-- Footer Status -->
    <div class="sidebar-footer">
        <div class="admin-badge">
            <div class="admin-avatar">
                <?= strtoupper(substr($currentAdmin['full_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?= escape($currentAdmin['full_name'] ?? 'Admin') ?></div>
                <div class="admin-role">System Admin</div>
            </div>
        </div>
    </div>
</aside>
