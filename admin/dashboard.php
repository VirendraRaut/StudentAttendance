<?php

/**
 * Admin Dashboard - Redesigned
 * Student Attendance Management System
 */

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$today = date('Y-m-d');

// 1. Fetch Key Metrics
try {
    $totalStudents    = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
    $totalTeachers    = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'Active'")->fetchColumn();
    $totalDepartments = (int) $pdo->query("SELECT COUNT(*) FROM departments WHERE status = 'Active'")->fetchColumn();
    $totalSections    = (int) $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'Active'")->fetchColumn();
    $totalSubjects    = (int) $pdo->query("SELECT COUNT(*) FROM subjects WHERE status = 'Active'")->fetchColumn();

    // Today's Attendance
    $stmtToday = $pdo->prepare(
        "SELECT 
            SUM(CASE WHEN attendance_status = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN attendance_status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            COUNT(*) AS total_marked
         FROM attendance 
         WHERE attendance_date = :today"
    );
    $stmtToday->execute([':today' => $today]);
    $todayStats = $stmtToday->fetch();

    $todayPresent = (int) ($todayStats['present_count'] ?? 0);
    $todayAbsent  = (int) ($todayStats['absent_count'] ?? 0);
    $todayTotal   = (int) ($todayStats['total_marked'] ?? 0);
    $todayPercentage = calculate_percentage($todayPresent, $todayTotal);

    // Overall Attendance
    $overallStmt = $pdo->query(
        "SELECT 
            SUM(CASE WHEN attendance_status = 'Present' THEN 1 ELSE 0 END) AS total_present,
            COUNT(*) AS total_records
         FROM attendance"
    );
    $overallStats = $overallStmt->fetch();
    $totalHistoricalPresent = (int) ($overallStats['total_present'] ?? 0);
    $totalHistoricalRecords = (int) ($overallStats['total_records'] ?? 0);
    $overallPercentage = calculate_percentage($totalHistoricalPresent, $totalHistoricalRecords);

    // Department Stats
    $deptStatsStmt = $pdo->query(
        "SELECT 
            d.id, 
            d.department_code, 
            d.department_name,
            COUNT(s.id) AS student_count
         FROM departments d
         LEFT JOIN students s ON d.id = s.department_id AND s.status = 'Active'
         WHERE d.status = 'Active'
         GROUP BY d.id, d.department_code, d.department_name
         ORDER BY student_count DESC, d.department_name ASC"
    );
    $departmentStats = $deptStatsStmt->fetchAll();

    // Subject Stats
    $subjStatsStmt = $pdo->query(
        "SELECT 
            sub.id,
            sub.subject_code,
            sub.subject_name,
            d.department_code,
            COUNT(a.id) AS total_attendance_records,
            SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS present_records
         FROM subjects sub
         JOIN departments d ON sub.department_id = d.id
         LEFT JOIN attendance a ON sub.id = a.subject_id
         WHERE sub.status = 'Active'
         GROUP BY sub.id, sub.subject_code, sub.subject_name, d.department_code
         ORDER BY total_attendance_records DESC, sub.subject_name ASC
         LIMIT 6"
    );
    $subjectStats = $subjStatsStmt->fetchAll();

    // Recent Activity
    $recentStmt = $pdo->query(
        "SELECT 
            a.id,
            a.attendance_date,
            a.attendance_status,
            a.remarks,
            a.created_at,
            s.student_code,
            s.roll_number,
            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
            sub.subject_code,
            sub.subject_name,
            sec.section_name,
            adm.full_name AS marked_by_name
         FROM attendance a
         JOIN students s ON a.student_id = s.id
         JOIN subjects sub ON a.subject_id = sub.id
         JOIN sections sec ON s.section_id = sec.id
         JOIN admins adm ON a.marked_by = adm.id
         ORDER BY a.created_at DESC
         LIMIT 6"
    );
    $recentActivity = $recentStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $totalStudents = $totalTeachers = $totalDepartments = $totalSections = $totalSubjects = 0;
    $todayPresent = $todayAbsent = $todayTotal = 0;
    $todayPercentage = $overallPercentage = 0.00;
    $departmentStats = [];
    $subjectStats = [];
    $recentActivity = [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Clean Page Header -->
<div class="page-header-block">
    <div>
        <h2 class="page-title">Institutional Dashboard</h2>
        <p class="page-subtitle">Real-time overview of college enrollment, academic divisions, and attendance metrics</p>
    </div>
    <div class="page-header-actions">
        <a href="attendance.php" class="btn btn-primary">
            ✍️ Mark Attendance
        </a>
        <a href="students.php?action=add" class="btn btn-secondary">
            ➕ Register Student
        </a>
        <a href="reports.php" class="btn btn-outline">
            📊 View Reports
        </a>
    </div>
</div>

<!-- Group 1: 4 Core Academic Inventory Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Active Students</div>
            <div class="stat-value"><?= number_format($totalStudents) ?></div>
            <div class="stat-sub">Across <?= number_format($totalSections) ?> class sections</div>
        </div>
        <div class="stat-icon stat-icon-primary">
            👨‍🎓
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Faculty Staff</div>
            <div class="stat-value"><?= number_format($totalTeachers) ?></div>
            <div class="stat-sub">Teaching instructors</div>
        </div>
        <div class="stat-icon stat-icon-info">
            👨‍🏫
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Departments</div>
            <div class="stat-value"><?= number_format($totalDepartments) ?></div>
            <div class="stat-sub">Academic programs</div>
        </div>
        <div class="stat-icon stat-icon-warning">
            🏛️
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Curriculum Subjects</div>
            <div class="stat-value"><?= number_format($totalSubjects) ?></div>
            <div class="stat-sub">Active course offerings</div>
        </div>
        <div class="stat-icon stat-icon-primary">
            📚
        </div>
    </div>
</div>

<!-- Group 2: 4 Attendance Pulse Stats -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Present</div>
            <div class="stat-value" style="color: var(--success);"><?= number_format($todayPresent) ?></div>
            <div class="stat-sub"><?= date('d M Y') ?> session</div>
        </div>
        <div class="stat-icon stat-icon-success">
            ✅
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Absent</div>
            <div class="stat-value" style="color: var(--danger);"><?= number_format($todayAbsent) ?></div>
            <div class="stat-sub"><?= date('d M Y') ?> session</div>
        </div>
        <div class="stat-icon stat-icon-danger">
            ❌
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Attendance Rate</div>
            <div class="stat-value" style="color: <?= $todayPercentage >= 75 ? 'var(--success)' : ($todayPercentage >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                <?= $todayTotal > 0 ? number_format($todayPercentage, 1) . '%' : '0.0%' ?>
            </div>
            <div class="stat-sub"><?= number_format($todayTotal) ?> students marked</div>
        </div>
        <div class="stat-icon stat-icon-info">
            ⚡
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Overall Attendance</div>
            <div class="stat-value" style="color: <?= $overallPercentage >= 75 ? 'var(--success)' : ($overallPercentage >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                <?= number_format($overallPercentage, 1) ?>%
            </div>
            <div class="stat-sub"><?= number_format($totalHistoricalRecords) ?> total logs</div>
        </div>
        <div class="stat-icon <?= $overallPercentage >= 75 ? 'stat-icon-success' : 'stat-icon-warning' ?>">
            📈
        </div>
    </div>
</div>

<!-- Two-Column Balanced Analytics Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 20px; margin-bottom: 24px;">
    
    <!-- Department Breakdown -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Student Enrollment by Department</h3>
                <div class="card-subtitle">Active student distribution across faculties</div>
            </div>
            <a href="departments.php" class="btn btn-sm btn-outline">View Departments</a>
        </div>
        <div class="card-body">
            <?php if (empty($departmentStats)): ?>
                <div class="empty-state" style="padding: 24px;">
                    <p class="empty-state-text">No active departments registered yet.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <?php 
                    $maxStudents = max(1, ...array_map(fn($d) => (int)$d['student_count'], $departmentStats));
                    foreach ($departmentStats as $dept): 
                        $pctOfMax = ($dept['student_count'] / $maxStudents) * 100;
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">
                                <span><?= escape($dept['department_name']) ?> <span style="color: var(--text-muted); font-weight: 500;">(<?= escape($dept['department_code']) ?>)</span></span>
                                <span><?= number_format($dept['student_count']) ?> students</span>
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?= $pctOfMax ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Subject Performance -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Subject Attendance Overview</h3>
                <div class="card-subtitle">Attendance performance across active courses</div>
            </div>
            <a href="subjects.php" class="btn btn-sm btn-outline">View Subjects</a>
        </div>
        <div class="card-body">
            <?php if (empty($subjectStats)): ?>
                <div class="empty-state" style="padding: 24px;">
                    <p class="empty-state-text">No subject attendance records found.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($subjectStats as $subj): 
                        $subjPct = calculate_percentage($subj['present_records'], $subj['total_attendance_records']);
                        $barClass = $subjPct >= 75 ? 'progress-bar-success' : ($subjPct >= 60 ? 'progress-bar-warning' : 'progress-bar-danger');
                    ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                                <span>
                                    <strong><?= escape($subj['subject_code']) ?></strong> - <?= escape($subj['subject_name']) ?>
                                    <span class="badge badge-secondary" style="margin-left: 4px;"><?= escape($subj['department_code']) ?></span>
                                </span>
                                <span style="font-weight: 700; color: <?= $subj['total_attendance_records'] > 0 ? ($subjPct >= 75 ? 'var(--success)' : ($subjPct >= 60 ? 'var(--warning)' : 'var(--danger)')) : 'var(--text-muted)' ?>;">
                                    <?= $subj['total_attendance_records'] > 0 ? number_format($subjPct, 1) . '%' : 'No Data' ?>
                                </span>
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar <?= $barClass ?>" style="width: <?= $subj['total_attendance_records'] > 0 ? $subjPct : 0 ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Attendance Activity Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Recent Roll-Call Activity</h3>
            <div class="card-subtitle">Audited log of latest student attendance entries</div>
        </div>
        <a href="attendance-history.php" class="btn btn-sm btn-outline">Full History Log</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student Name & Code</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th class="text-center">Status</th>
                        <th>Remarks</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentActivity)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon">📋</div>
                                    <div class="empty-state-title">No Attendance Recorded Yet</div>
                                    <div class="empty-state-text">Start taking roll-call sessions to view activity logs and live reports.</div>
                                    <a href="attendance.php" class="btn btn-primary btn-sm">Mark Today's Attendance</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $act): ?>
                            <tr>
                                <td><strong><?= format_date($act['attendance_date'], 'd M Y') ?></strong></td>
                                <td>
                                    <strong><?= escape($act['student_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= escape($act['student_code']) ?> &bull; Roll: <?= escape($act['roll_number']) ?></div>
                                </td>
                                <td><span class="badge badge-secondary"><?= escape($act['section_name']) ?></span></td>
                                <td>
                                    <strong><?= escape($act['subject_code']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= escape($act['subject_name']) ?></div>
                                </td>
                                <td class="text-center"><?= render_status_badge($act['attendance_status']) ?></td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= !empty($act['remarks']) ? escape($act['remarks']) : '—' ?></td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= escape($act['marked_by_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
