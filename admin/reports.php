<?php

/**
 * Attendance Reporting & Analytics
 * Student Attendance Management System
 */

$pageTitle = 'Attendance Reports';
$currentPage = 'reports';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$departments = get_active_departments($pdo, false);

// 1. Report Type Selection
$reportType = $_GET['report_type'] ?? 'daily'; // 'daily', 'monthly', 'student', 'subject', 'section', 'department'

// 2. Filter Inputs
$date        = trim($_GET['date'] ?? date('Y-m-d'));
$month       = trim($_GET['month'] ?? date('Y-m'));
$startDate   = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate     = trim($_GET['end_date'] ?? date('Y-m-d'));
$deptFilter  = (int) ($_GET['department_id'] ?? 0);
$secFilter   = (int) ($_GET['section_id'] ?? 0);
$subjFilter  = (int) ($_GET['subject_id'] ?? 0);
$stuFilter   = (int) ($_GET['student_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$sections = $deptFilter > 0 ? get_sections_by_department($pdo, $deptFilter, false) : [];
$subjects = $deptFilter > 0 ? get_subjects_by_department($pdo, $deptFilter, false) : [];

// Fetch students for dropdown if department or section selected
$studentsDropdown = [];
if ($deptFilter > 0 || $secFilter > 0) {
    $stuSql = "SELECT id, student_code, roll_number, first_name, last_name FROM students WHERE 1=1";
    $stuParams = [];
    if ($deptFilter > 0) {
        $stuSql .= " AND department_id = :d";
        $stuParams[':d'] = $deptFilter;
    }
    if ($secFilter > 0) {
        $stuSql .= " AND section_id = :s";
        $stuParams[':s'] = $secFilter;
    }
    $stuSql .= " ORDER BY roll_number ASC, first_name ASC";
    $stuStmt = $pdo->prepare($stuSql);
    $stuStmt->execute($stuParams);
    $studentsDropdown = $stuStmt->fetchAll();
}

// 3. Process Report Data Based on Report Type
$reportData = [];
$reportSummary = [
    'total_records' => 0,
    'total_present' => 0,
    'total_absent'  => 0,
    'overall_percentage' => 0.00
];

try {
    switch ($reportType) {
        // ---------------------------------------------------------
        // 1. DAILY ATTENDANCE REPORT
        // ---------------------------------------------------------
        case 'daily':
            $where = ["a.attendance_date = :att_date"];
            $params = [':att_date' => $date];

            if ($deptFilter > 0) {
                $where[] = "s.department_id = :dept";
                $params[':dept'] = $deptFilter;
            }
            if ($secFilter > 0) {
                $where[] = "s.section_id = :sec";
                $params[':sec'] = $secFilter;
            }
            if ($subjFilter > 0) {
                $where[] = "a.subject_id = :subj";
                $params[':subj'] = $subjFilter;
            }
            if (!empty($statusFilter)) {
                $where[] = "a.attendance_status = :status";
                $params[':status'] = $statusFilter;
            }

            $sql = "SELECT a.*, 
                    s.student_code, s.roll_number, s.first_name, s.last_name, s.gender,
                    d.department_code, d.department_name,
                    sec.section_name,
                    sub.subject_code, sub.subject_name
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    JOIN subjects sub ON a.subject_id = sub.id
                    JOIN departments d ON s.department_id = d.id
                    JOIN sections sec ON s.section_id = sec.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY d.department_code ASC, sec.section_name ASC, s.roll_number ASC, sub.subject_code ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();

            foreach ($reportData as $r) {
                $reportSummary['total_records']++;
                if ($r['attendance_status'] === 'Present') $reportSummary['total_present']++;
                else $reportSummary['total_absent']++;
            }
            $reportSummary['overall_percentage'] = calculate_percentage($reportSummary['total_present'], $reportSummary['total_records']);
            break;

        // ---------------------------------------------------------
        // 2. MONTHLY ATTENDANCE REPORT
        // ---------------------------------------------------------
        case 'monthly':
            // Month format: YYYY-MM
            $monthStart = $month . '-01';
            $monthEnd   = date('Y-m-t', strtotime($monthStart));

            $where = ["a.attendance_date BETWEEN :start AND :end"];
            $params = [':start' => $monthStart, ':end' => $monthEnd];

            if ($deptFilter > 0) {
                $where[] = "s.department_id = :dept";
                $params[':dept'] = $deptFilter;
            }
            if ($secFilter > 0) {
                $where[] = "s.section_id = :sec";
                $params[':sec'] = $secFilter;
            }
            if ($subjFilter > 0) {
                $where[] = "a.subject_id = :subj";
                $params[':subj'] = $subjFilter;
            }

            $sql = "SELECT 
                    s.id AS student_id, s.student_code, s.roll_number, s.first_name, s.last_name,
                    d.department_code, sec.section_name,
                    COUNT(a.id) AS total_sessions,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
                    FROM students s
                    JOIN departments d ON s.department_id = d.id
                    JOIN sections sec ON s.section_id = sec.id
                    JOIN attendance a ON s.id = a.student_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY s.id, s.student_code, s.roll_number, s.first_name, s.last_name, d.department_code, sec.section_name
                    ORDER BY d.department_code ASC, sec.section_name ASC, s.roll_number ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();

            foreach ($reportData as $r) {
                $reportSummary['total_records'] += (int) $r['total_sessions'];
                $reportSummary['total_present'] += (int) $r['present_count'];
                $reportSummary['total_absent']  += (int) $r['absent_count'];
            }
            $reportSummary['overall_percentage'] = calculate_percentage($reportSummary['total_present'], $reportSummary['total_records']);
            break;

        // ---------------------------------------------------------
        // 3. STUDENT ATTENDANCE REPORT
        // ---------------------------------------------------------
        case 'student':
            $where = ["1=1"];
            $params = [];

            if (!empty($startDate)) {
                $where[] = "a.attendance_date >= :start";
                $params[':start'] = $startDate;
            }
            if (!empty($endDate)) {
                $where[] = "a.attendance_date <= :end";
                $params[':end'] = $endDate;
            }
            if ($stuFilter > 0) {
                $where[] = "s.id = :student_id";
                $params[':student_id'] = $stuFilter;
            } elseif ($deptFilter > 0) {
                $where[] = "s.department_id = :dept";
                $params[':dept'] = $deptFilter;
            }

            $sql = "SELECT 
                    s.id AS student_id, s.student_code, s.roll_number, s.first_name, s.last_name,
                    d.department_code, sec.section_name,
                    sub.id AS subject_id, sub.subject_code, sub.subject_name,
                    COUNT(a.id) AS total_sessions,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
                    FROM students s
                    JOIN departments d ON s.department_id = d.id
                    JOIN sections sec ON s.section_id = sec.id
                    JOIN student_subjects ss ON s.id = ss.student_id AND ss.status = 'Active'
                    JOIN subjects sub ON ss.subject_id = sub.id
                    LEFT JOIN attendance a ON s.id = a.student_id AND sub.id = a.subject_id AND " . implode(' AND ', $where) . "
                    WHERE s.status = 'Active' " . ($stuFilter > 0 ? "AND s.id = :s_target" : ($deptFilter > 0 ? "AND s.department_id = :d_target" : "")) . "
                    GROUP BY s.id, s.student_code, s.roll_number, s.first_name, s.last_name, d.department_code, sec.section_name, sub.id, sub.subject_code, sub.subject_name
                    ORDER BY d.department_code ASC, sec.section_name ASC, s.roll_number ASC, sub.subject_code ASC";

            if ($stuFilter > 0) $params[':s_target'] = $stuFilter;
            elseif ($deptFilter > 0) $params[':d_target'] = $deptFilter;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();

            foreach ($reportData as $r) {
                $reportSummary['total_records'] += (int) $r['total_sessions'];
                $reportSummary['total_present'] += (int) $r['present_count'];
                $reportSummary['total_absent']  += (int) $r['absent_count'];
            }
            $reportSummary['overall_percentage'] = calculate_percentage($reportSummary['total_present'], $reportSummary['total_records']);
            break;

        // ---------------------------------------------------------
        // 4. SUBJECT-WISE REPORT
        // ---------------------------------------------------------
        case 'subject':
            $where = ["1=1"];
            $params = [];

            if (!empty($startDate)) {
                $where[] = "a.attendance_date >= :start";
                $params[':start'] = $startDate;
            }
            if (!empty($endDate)) {
                $where[] = "a.attendance_date <= :end";
                $params[':end'] = $endDate;
            }
            if ($deptFilter > 0) {
                $where[] = "sub.department_id = :dept";
                $params[':dept'] = $deptFilter;
            }

            $sql = "SELECT 
                    sub.id AS subject_id, sub.subject_code, sub.subject_name,
                    d.department_code, d.department_name,
                    COUNT(DISTINCT a.attendance_date) AS total_dates_conducted,
                    COUNT(a.id) AS total_student_sessions,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS total_present,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS total_absent
                    FROM subjects sub
                    JOIN departments d ON sub.department_id = d.id
                    LEFT JOIN attendance a ON sub.id = a.subject_id AND " . implode(' AND ', $where) . "
                    WHERE sub.status = 'Active' " . ($deptFilter > 0 ? "AND sub.department_id = :d_target" : "") . "
                    GROUP BY sub.id, sub.subject_code, sub.subject_name, d.department_code, d.department_name
                    ORDER BY d.department_code ASC, sub.subject_code ASC";

            if ($deptFilter > 0) $params[':d_target'] = $deptFilter;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();

            foreach ($reportData as $r) {
                $reportSummary['total_records'] += (int) $r['total_student_sessions'];
                $reportSummary['total_present'] += (int) $r['total_present'];
                $reportSummary['total_absent']  += (int) $r['total_absent'];
            }
            $reportSummary['overall_percentage'] = calculate_percentage($reportSummary['total_present'], $reportSummary['total_records']);
            break;

        // ---------------------------------------------------------
        // 5. SECTION-WISE REPORT
        // ---------------------------------------------------------
        case 'section':
            $where = ["1=1"];
            $params = [];

            if (!empty($startDate)) {
                $where[] = "a.attendance_date >= :start";
                $params[':start'] = $startDate;
            }
            if (!empty($endDate)) {
                $where[] = "a.attendance_date <= :end";
                $params[':end'] = $endDate;
            }
            if ($deptFilter > 0) {
                $where[] = "sec.department_id = :dept";
                $params[':dept'] = $deptFilter;
            }

            $sql = "SELECT 
                    sec.id AS section_id, sec.section_name,
                    d.department_code, d.department_name,
                    (SELECT COUNT(*) FROM students st WHERE st.section_id = sec.id AND st.status = 'Active') AS student_count,
                    COUNT(a.id) AS total_attendance_records,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS total_present,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS total_absent
                    FROM sections sec
                    JOIN departments d ON sec.department_id = d.id
                    LEFT JOIN students s ON sec.id = s.section_id
                    LEFT JOIN attendance a ON s.id = a.student_id AND " . implode(' AND ', $where) . "
                    WHERE sec.status = 'Active' " . ($deptFilter > 0 ? "AND sec.department_id = :d_target" : "") . "
                    GROUP BY sec.id, sec.section_name, d.department_code, d.department_name
                    ORDER BY d.department_code ASC, sec.section_name ASC";

            if ($deptFilter > 0) $params[':d_target'] = $deptFilter;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();

            foreach ($reportData as $r) {
                $reportSummary['total_records'] += (int) $r['total_attendance_records'];
                $reportSummary['total_present'] += (int) $r['total_present'];
                $reportSummary['total_absent']  += (int) $r['total_absent'];
            }
            $reportSummary['overall_percentage'] = calculate_percentage($reportSummary['total_present'], $reportSummary['total_records']);
            break;

        // ---------------------------------------------------------
        // 6. DEPARTMENT-WISE REPORT
        // ---------------------------------------------------------
        case 'department':
            $where = ["1=1"];
            $params = [];

            if (!empty($startDate)) {
                $where[] = "a.attendance_date >= :start";
                $params[':start'] = $startDate;
            }
            if (!empty($endDate)) {
                $where[] = "a.attendance_date <= :end";
                $params[':end'] = $endDate;
            }

            $sql = "SELECT 
                    d.id AS department_id, d.department_code, d.department_name,
                    (SELECT COUNT(*) FROM sections sec WHERE sec.department_id = d.id AND sec.status = 'Active') AS section_count,
                    (SELECT COUNT(*) FROM students st WHERE st.department_id = d.id AND st.status = 'Active') AS student_count,
                    (SELECT COUNT(*) FROM subjects sub WHERE sub.department_id = d.id AND sub.status = 'Active') AS subject_count,
                    COUNT(a.id) AS total_attendance_records,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS total_present,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS total_absent
                    FROM departments d
                    LEFT JOIN students s ON d.id = s.department_id
                    LEFT JOIN attendance a ON s.id = a.student_id AND " . implode(' AND ', $where) . "
                    WHERE d.status = 'Active'
                    GROUP BY d.id, d.department_code, d.department_name
                    ORDER BY d.department_name ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();

            foreach ($reportData as $r) {
                $reportSummary['total_records'] += (int) $r['total_attendance_records'];
                $reportSummary['total_present'] += (int) $r['total_present'];
                $reportSummary['total_absent']  += (int) $r['total_absent'];
            }
            $reportSummary['overall_percentage'] = calculate_percentage($reportSummary['total_present'], $reportSummary['total_records']);
            break;
    }
} catch (PDOException $e) {
    error_log("Report generation error: " . $e->getMessage());
    set_flash('danger', 'Database error occurred during report generation.');
}

// Build query string for export links
$exportParams = $_GET;
$exportQs = http_build_query($exportParams);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Report Type Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 4px;">
    <a href="?report_type=daily" class="btn <?= $reportType === 'daily' ? 'btn-primary' : 'btn-outline' ?>">
        📅 Daily Report
    </a>
    <a href="?report_type=monthly" class="btn <?= $reportType === 'monthly' ? 'btn-primary' : 'btn-outline' ?>">
        🗓️ Monthly Report
    </a>
    <a href="?report_type=student" class="btn <?= $reportType === 'student' ? 'btn-primary' : 'btn-outline' ?>">
        👨‍🎓 Student Report
    </a>
    <a href="?report_type=subject" class="btn <?= $reportType === 'subject' ? 'btn-primary' : 'btn-outline' ?>">
        📚 Subject-Wise
    </a>
    <a href="?report_type=section" class="btn <?= $reportType === 'section' ? 'btn-primary' : 'btn-outline' ?>">
        📑 Section-Wise
    </a>
    <a href="?report_type=department" class="btn <?= $reportType === 'department' ? 'btn-primary' : 'btn-outline' ?>">
        🏛️ Department-Wise
    </a>
</div>

<!-- Filter Toolbar -->
<div class="filter-card">
    <form method="GET" action="reports.php" id="reportFilterForm">
        <input type="hidden" name="report_type" value="<?= escape($reportType) ?>">

        <div class="form-row-4" style="margin-bottom: 14px;">
            <?php if ($reportType === 'daily'): ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="date">Specific Date</label>
                    <input type="date" id="date" name="date" class="form-control" value="<?= escape($date) ?>" required>
                </div>
            <?php elseif ($reportType === 'monthly'): ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="month">Select Month</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?= escape($month) ?>" required>
                </div>
            <?php else: ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="start_date">From Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?= escape($startDate) ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="end_date">To Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?= escape($endDate) ?>">
                </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="department_select">Department</label>
                <select id="department_select" name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $deptFilter == $dept['id'] ? 'selected' : '' ?>>
                            <?= escape($dept['department_name']) ?> (<?= escape($dept['department_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (in_array($reportType, ['daily', 'monthly', 'student'])): ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="section_select">Section</label>
                    <select id="section_select" name="section_id" class="form-select">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?= $sec['id'] ?>" <?= $secFilter == $sec['id'] ? 'selected' : '' ?>>
                                <?= escape($sec['section_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (in_array($reportType, ['daily', 'monthly', 'student'])): ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="subject_select">Subject</label>
                    <select id="subject_select" name="subject_id" class="form-select">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>" <?= $subjFilter == $sub['id'] ? 'selected' : '' ?>>
                                <?= escape($sub['subject_code']) ?> - <?= escape($sub['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($reportType === 'student'): ?>
            <div class="form-row" style="margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="student_id">Specific Student</label>
                    <select id="student_id" name="student_id" class="form-select">
                        <option value="">All Enrolled Students</option>
                        <?php foreach ($studentsDropdown as $stu): ?>
                            <option value="<?= $stu['id'] ?>" <?= $stuFilter == $stu['id'] ? 'selected' : '' ?>>
                                Roll: <?= escape($stu['roll_number']) ?> &bull; <?= escape($stu['first_name'] . ' ' . $stu['last_name']) ?> (<?= escape($stu['student_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($reportType === 'daily'): ?>
            <div class="form-row" style="margin-bottom: 14px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All (Present & Absent)</option>
                        <option value="Present" <?= $statusFilter === 'Present' ? 'selected' : '' ?>>Present Only</option>
                        <option value="Absent" <?= $statusFilter === 'Absent' ? 'selected' : '' ?>>Absent Only</option>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <a href="reports.php?report_type=<?= $reportType ?>" class="btn btn-outline">Reset Filters</a>

            <!-- Export Buttons -->
            <div style="margin-left: auto; display: flex; gap: 8px;">
                <a href="../exports/export-excel.php?<?= $exportQs ?>" class="btn btn-success" target="_blank">
                    📥 Export Excel
                </a>
                <a href="../exports/export-pdf.php?<?= $exportQs ?>" class="btn btn-danger" target="_blank">
                    📄 Export PDF
                </a>
                <button type="button" class="btn btn-secondary" onclick="window.print()">
                    🖨️ Print
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Aggregated Summary Bar -->
<div class="attendance-summary-bar">
    <div class="summary-pill">
        <span>Total Attendance Sessions:</span>
        <span class="summary-pill-num badge badge-secondary"><?= number_format($reportSummary['total_records']) ?></span>
    </div>
    <div class="summary-pill">
        <span>Present:</span>
        <span class="summary-pill-num badge badge-success"><?= number_format($reportSummary['total_present']) ?></span>
    </div>
    <div class="summary-pill">
        <span>Absent:</span>
        <span class="summary-pill-num badge badge-danger"><?= number_format($reportSummary['total_absent']) ?></span>
    </div>
    <div class="summary-pill">
        <span>Overall Attendance Rate:</span>
        <span class="summary-pill-num badge <?= $reportSummary['overall_percentage'] >= 75 ? 'badge-success' : ($reportSummary['overall_percentage'] >= 60 ? 'badge-warning' : 'badge-danger') ?>">
            <?= number_format($reportSummary['overall_percentage'], 1) ?>%
        </span>
    </div>
</div>

<!-- Report Table Output -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title"><?= ucfirst($reportType) ?> Attendance Report</h3>
            <div class="card-subtitle">
                Generated for <?= date('d M Y, h:i A') ?> &bull; Filtered Results: <?= count($reportData) ?> records
            </div>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <?php if ($reportType === 'daily'): ?>
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Code</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="7"><div class="empty-state">No records for this date.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <td><strong><?= escape($row['roll_number']) ?></strong></td>
                                    <td><?= escape($row['student_code']) ?></td>
                                    <td><?= escape($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><span class="badge badge-secondary"><?= escape($row['department_code']) ?></span> <?= escape($row['section_name']) ?></td>
                                    <td><?= escape($row['subject_code']) ?> - <?= escape($row['subject_name']) ?></td>
                                    <td><?= render_status_badge($row['attendance_status']) ?></td>
                                    <td><?= !empty($row['remarks']) ? escape($row['remarks']) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                <?php elseif ($reportType === 'monthly'): ?>
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Code</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Working Classes</th>
                            <th>Present Days</th>
                            <th>Absent Days</th>
                            <th>Attendance %</th>
                            <th style="min-width: 140px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="9"><div class="empty-state">No attendance records for selected month.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): 
                                $pct = calculate_percentage($row['present_count'], $row['total_sessions']);
                                $barClass = $pct >= 75 ? 'progress-bar-success' : ($pct >= 60 ? 'progress-bar-warning' : 'progress-bar-danger');
                            ?>
                                <tr>
                                    <td><strong><?= escape($row['roll_number']) ?></strong></td>
                                    <td><?= escape($row['student_code']) ?></td>
                                    <td><?= escape($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                    <td><span class="badge badge-secondary"><?= escape($row['department_code']) ?></span> <?= escape($row['section_name']) ?></td>
                                    <td><?= number_format($row['total_sessions']) ?></td>
                                    <td style="color: var(--success); font-weight: 700;"><?= number_format($row['present_count']) ?></td>
                                    <td style="color: var(--danger); font-weight: 700;"><?= number_format($row['absent_count']) ?></td>
                                    <td style="font-weight: 700; color: <?= $pct >= 75 ? 'var(--success)' : ($pct >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                        <?= number_format($pct, 1) ?>%
                                    </td>
                                    <td>
                                        <div class="progress-container" style="height: 7px; margin-top: 0;">
                                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                <?php elseif ($reportType === 'student'): ?>
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Recorded Classes</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Attendance %</th>
                            <th style="min-width: 140px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="9"><div class="empty-state">No attendance found for selected students.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): 
                                $pct = calculate_percentage($row['present_count'], $row['total_sessions']);
                                $barClass = $pct >= 75 ? 'progress-bar-success' : ($pct >= 60 ? 'progress-bar-warning' : 'progress-bar-danger');
                            ?>
                                <tr>
                                    <td><strong><?= escape($row['roll_number']) ?></strong></td>
                                    <td>
                                        <?= escape($row['first_name'] . ' ' . $row['last_name']) ?>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= escape($row['student_code']) ?></div>
                                    </td>
                                    <td><span class="badge badge-secondary"><?= escape($row['department_code']) ?></span> <?= escape($row['section_name']) ?></td>
                                    <td><strong><?= escape($row['subject_code']) ?></strong> - <?= escape($row['subject_name']) ?></td>
                                    <td><?= number_format($row['total_sessions']) ?></td>
                                    <td style="color: var(--success); font-weight: 700;"><?= number_format($row['present_count']) ?></td>
                                    <td style="color: var(--danger); font-weight: 700;"><?= number_format($row['absent_count']) ?></td>
                                    <td style="font-weight: 700; color: <?= $pct >= 75 ? 'var(--success)' : ($pct >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                        <?= number_format($pct, 1) ?>%
                                    </td>
                                    <td>
                                        <div class="progress-container" style="height: 7px; margin-top: 0;">
                                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                <?php elseif ($reportType === 'subject'): ?>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Name</th>
                            <th>Department</th>
                            <th>Dates Conducted</th>
                            <th>Total Attendances</th>
                            <th>Present Marks</th>
                            <th>Absent Marks</th>
                            <th>Attendance %</th>
                            <th style="min-width: 140px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="9"><div class="empty-state">No subjects found.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): 
                                $pct = calculate_percentage($row['total_present'], $row['total_student_sessions']);
                                $barClass = $pct >= 75 ? 'progress-bar-success' : ($pct >= 60 ? 'progress-bar-warning' : 'progress-bar-danger');
                            ?>
                                <tr>
                                    <td><strong><?= escape($row['subject_code']) ?></strong></td>
                                    <td><?= escape($row['subject_name']) ?></td>
                                    <td><span class="badge badge-secondary"><?= escape($row['department_code']) ?></span></td>
                                    <td><?= number_format($row['total_dates_conducted']) ?> dates</td>
                                    <td><?= number_format($row['total_student_sessions']) ?></td>
                                    <td style="color: var(--success); font-weight: 700;"><?= number_format($row['total_present']) ?></td>
                                    <td style="color: var(--danger); font-weight: 700;"><?= number_format($row['total_absent']) ?></td>
                                    <td style="font-weight: 700; color: <?= $pct >= 75 ? 'var(--success)' : ($pct >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                        <?= number_format($pct, 1) ?>%
                                    </td>
                                    <td>
                                        <div class="progress-container" style="height: 7px; margin-top: 0;">
                                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                <?php elseif ($reportType === 'section'): ?>
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Department</th>
                            <th>Active Students</th>
                            <th>Total Attendance Marks</th>
                            <th>Present Marks</th>
                            <th>Absent Marks</th>
                            <th>Attendance %</th>
                            <th style="min-width: 140px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8"><div class="empty-state">No sections found.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): 
                                $pct = calculate_percentage($row['total_present'], $row['total_attendance_records']);
                                $barClass = $pct >= 75 ? 'progress-bar-success' : ($pct >= 60 ? 'progress-bar-warning' : 'progress-bar-danger');
                            ?>
                                <tr>
                                    <td><strong><?= escape($row['section_name']) ?></strong></td>
                                    <td><span class="badge badge-secondary"><?= escape($row['department_code']) ?></span> <?= escape($row['department_name']) ?></td>
                                    <td><span class="badge badge-info"><?= number_format($row['student_count']) ?> students</span></td>
                                    <td><?= number_format($row['total_attendance_records']) ?></td>
                                    <td style="color: var(--success); font-weight: 700;"><?= number_format($row['total_present']) ?></td>
                                    <td style="color: var(--danger); font-weight: 700;"><?= number_format($row['total_absent']) ?></td>
                                    <td style="font-weight: 700; color: <?= $pct >= 75 ? 'var(--success)' : ($pct >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                        <?= number_format($pct, 1) ?>%
                                    </td>
                                    <td>
                                        <div class="progress-container" style="height: 7px; margin-top: 0;">
                                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>

                <?php elseif ($reportType === 'department'): ?>
                    <thead>
                        <tr>
                            <th>Department Code</th>
                            <th>Department Name</th>
                            <th>Sections</th>
                            <th>Enrolled Students</th>
                            <th>Subjects</th>
                            <th>Total Attendance Records</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Attendance %</th>
                            <th style="min-width: 140px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="10"><div class="empty-state">No departments found.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): 
                                $pct = calculate_percentage($row['total_present'], $row['total_attendance_records']);
                                $barClass = $pct >= 75 ? 'progress-bar-success' : ($pct >= 60 ? 'progress-bar-warning' : 'progress-bar-danger');
                            ?>
                                <tr>
                                    <td><strong><?= escape($row['department_code']) ?></strong></td>
                                    <td><strong><?= escape($row['department_name']) ?></strong></td>
                                    <td><span class="badge badge-secondary"><?= number_format($row['section_count']) ?></span></td>
                                    <td><span class="badge badge-info"><?= number_format($row['student_count']) ?></span></td>
                                    <td><span class="badge badge-secondary"><?= number_format($row['subject_count']) ?></span></td>
                                    <td><?= number_format($row['total_attendance_records']) ?></td>
                                    <td style="color: var(--success); font-weight: 700;"><?= number_format($row['total_present']) ?></td>
                                    <td style="color: var(--danger); font-weight: 700;"><?= number_format($row['total_absent']) ?></td>
                                    <td style="font-weight: 700; color: <?= $pct >= 75 ? 'var(--success)' : ($pct >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                        <?= number_format($pct, 1) ?>%
                                    </td>
                                    <td>
                                        <div class="progress-container" style="height: 7px; margin-top: 0;">
                                            <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
