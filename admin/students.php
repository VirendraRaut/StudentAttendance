<?php

/**
 * Student Management - Redesigned
 * Student Attendance Management System
 */

$pageTitle = 'Students';
$currentPage = 'students';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

<!-- Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title"><?= $pageTitle ?></h2>
        <p class="page-subtitle">Manage student records and assignments</p>
    </div>
</div>

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$editStudent = null;
$viewStudent = null;
$showForm = ($action === 'add' || $action === 'edit');

// Search & Filter
$search = trim($_GET['search'] ?? '');
$deptFilter = (int) ($_GET['department_id'] ?? 0);
$secFilter  = (int) ($_GET['section_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$departments = get_active_departments($pdo, false);

// Handle POST: Add or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token. Please try again.');
        redirect('admin/students.php');
    }

    $studentId     = (int) ($_POST['student_id'] ?? 0);
    $studentCode   = strtoupper(trim($_POST['student_code'] ?? ''));
    $rollNumber    = trim($_POST['roll_number'] ?? '');
    $firstName     = trim($_POST['first_name'] ?? '');
    $lastName      = trim($_POST['last_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $gender        = $_POST['gender'] ?? null;
    $dob           = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $address       = trim($_POST['address'] ?? '');
    $deptId        = (int) ($_POST['department_id'] ?? 0);
    $sectionId     = (int) ($_POST['section_id'] ?? 0);
    $admissionDate = !empty($_POST['admission_date']) ? $_POST['admission_date'] : null;
    $status        = $_POST['status'] ?? 'Active';

    if (empty($studentCode) || empty($rollNumber) || empty($firstName) || empty($lastName) || $deptId <= 0 || $sectionId <= 0) {
        set_flash('danger', 'Student Code, Roll Number, Name, Department, and Section are required.');
        redirect($studentId > 0 ? "admin/students.php?action=edit&id={$studentId}" : "admin/students.php?action=add");
    }

    // STRICT VALIDATION: Ensure selected section belongs to selected department
    $chkDeptSec = $pdo->prepare("SELECT id FROM sections WHERE id = :sec_id AND department_id = :dept_id LIMIT 1");
    $chkDeptSec->execute([':sec_id' => $sectionId, ':dept_id' => $deptId]);
    if (!$chkDeptSec->fetch()) {
        set_flash('danger', 'Validation Error: The selected section does not belong to the selected department.');
        redirect($studentId > 0 ? "admin/students.php?action=edit&id={$studentId}" : "admin/students.php?action=add");
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please enter a valid email address.');
        redirect($studentId > 0 ? "admin/students.php?action=edit&id={$studentId}" : "admin/students.php?action=add");
    }

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    try {
        if ($studentId > 0) {
            $chkCode = $pdo->prepare("SELECT id FROM students WHERE student_code = :code AND id != :id LIMIT 1");
            $chkCode->execute([':code' => $studentCode, ':id' => $studentId]);
            if ($chkCode->fetch()) {
                set_flash('danger', 'Another student with this Student Code already exists.');
                redirect("admin/students.php?action=edit&id={$studentId}");
            }

            $chkRoll = $pdo->prepare("SELECT id FROM students WHERE roll_number = :roll AND section_id = :sec_id AND id != :id LIMIT 1");
            $chkRoll->execute([':roll' => $rollNumber, ':sec_id' => $sectionId, ':id' => $studentId]);
            if ($chkRoll->fetch()) {
                set_flash('danger', "Roll number '{$rollNumber}' is already registered in this section.");
                redirect("admin/students.php?action=edit&id={$studentId}");
            }

            $updateStmt = $pdo->prepare(
                "UPDATE students 
                 SET student_code = :code, roll_number = :roll, first_name = :fname, last_name = :lname,
                     email = :email, phone = :phone, gender = :gender, date_of_birth = :dob,
                     address = :address, department_id = :dept_id, section_id = :sec_id,
                     admission_date = :adm_date, status = :status
                 WHERE id = :id"
            );
            $updateStmt->execute([
                ':code'     => $studentCode,
                ':roll'     => $rollNumber,
                ':fname'    => $firstName,
                ':lname'    => $lastName,
                ':email'    => $email ?: null,
                ':phone'    => $phone ?: null,
                ':gender'   => $gender ?: null,
                ':dob'      => $dob,
                ':address'  => $address ?: null,
                ':dept_id'  => $deptId,
                ':sec_id'   => $sectionId,
                ':adm_date' => $admissionDate,
                ':status'   => $status,
                ':id'       => $studentId
            ]);
            set_flash('success', "Student '{$firstName} {$lastName}' updated successfully.");

        } else {
            $chkCode = $pdo->prepare("SELECT id FROM students WHERE student_code = :code LIMIT 1");
            $chkCode->execute([':code' => $studentCode]);
            if ($chkCode->fetch()) {
                set_flash('danger', 'A student with this Student Code already exists.');
                redirect("admin/students.php?action=add");
            }

            $chkRoll = $pdo->prepare("SELECT id FROM students WHERE roll_number = :roll AND section_id = :sec_id LIMIT 1");
            $chkRoll->execute([':roll' => $rollNumber, ':sec_id' => $sectionId]);
            if ($chkRoll->fetch()) {
                set_flash('danger', "Roll number '{$rollNumber}' already exists in the selected section.");
                redirect("admin/students.php?action=add");
            }

            $insertStmt = $pdo->prepare(
                "INSERT INTO students 
                 (student_code, roll_number, first_name, last_name, email, phone, gender, 
                  date_of_birth, address, department_id, section_id, admission_date, status) 
                 VALUES 
                 (:code, :roll, :fname, :lname, :email, :phone, :gender, 
                  :dob, :address, :dept_id, :sec_id, :adm_date, :status)"
            );
            $insertStmt->execute([
                ':code'     => $studentCode,
                ':roll'     => $rollNumber,
                ':fname'    => $firstName,
                ':lname'    => $lastName,
                ':email'    => $email ?: null,
                ':phone'    => $phone ?: null,
                ':gender'   => $gender ?: null,
                ':dob'      => $dob,
                ':address'  => $address ?: null,
                ':dept_id'  => $deptId,
                ':sec_id'   => $sectionId,
                ':adm_date' => $admissionDate,
                ':status'   => $status
            ]);
            set_flash('success', "Student '{$firstName} {$lastName}' registered successfully.");
        }

        redirect('admin/students.php');

    } catch (PDOException $e) {
        error_log("Student save error: " . $e->getMessage());
        set_flash('danger', 'Database error occurred while saving student.');
        redirect('admin/students.php');
    }
}

// Handle GET: Delete
if ($action === 'delete' && $editId > 0) {
    try {
        $chkAtt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = :id");
        $chkAtt->execute([':id' => $editId]);
        $hasAttendance = (int) $chkAtt->fetchColumn();

        $chkSub = $pdo->prepare("SELECT COUNT(*) FROM student_subjects WHERE student_id = :id");
        $chkSub->execute([':id' => $editId]);
        $hasSubjects = (int) $chkSub->fetchColumn();

        if ($hasAttendance > 0) {
            set_flash('danger', "Cannot delete student: {$hasAttendance} attendance session(s) recorded. Historical logs are protected. Change status to 'Inactive' instead.");
        } elseif ($hasSubjects > 0) {
            set_flash('danger', "Cannot delete student: Currently enrolled in {$hasSubjects} subject(s). Remove course enrollments first.");
        } else {
            $delStmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
            $delStmt->execute([':id' => $editId]);
            set_flash('success', 'Student record deleted successfully.');
        }

    } catch (PDOException $e) {
        error_log("Student delete error: " . $e->getMessage());
        set_flash('danger', 'Cannot delete student due to database relationships.');
    }
    redirect('admin/students.php');
}

// Fetch record if editing
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editStudent = $stmt->fetch();
    if (!$editStudent) {
        set_flash('danger', 'Student record not found.');
        redirect('admin/students.php');
    }
}

// Fetch record if viewing profile & attendance
if ($action === 'view' && $editId > 0) {
    $stmt = $pdo->prepare(
        "SELECT s.*, d.department_name, d.department_code, sec.section_name 
         FROM students s
         JOIN departments d ON s.department_id = d.id
         JOIN sections sec ON s.section_id = sec.id
         WHERE s.id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $editId]);
    $viewStudent = $stmt->fetch();

    if ($viewStudent) {
        $subjAttStmt = $pdo->prepare(
            "SELECT 
                sub.id AS subject_id,
                sub.subject_code,
                sub.subject_name,
                COUNT(a.id) AS total_sessions,
                SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
             FROM student_subjects ss
             JOIN subjects sub ON ss.subject_id = sub.id
             LEFT JOIN attendance a ON ss.student_id = a.student_id AND ss.subject_id = a.subject_id
             WHERE ss.student_id = :stu_id
             GROUP BY sub.id, sub.subject_code, sub.subject_name
             ORDER BY sub.subject_code ASC"
        );
        $subjAttStmt->execute([':stu_id' => $editId]);
        $viewStudent['subjects_attendance'] = $subjAttStmt->fetchAll();

        $totSess = 0;
        $totPres = 0;
        foreach ($viewStudent['subjects_attendance'] as $sa) {
            $totSess += (int) $sa['total_sessions'];
            $totPres += (int) $sa['present_count'];
        }
        $viewStudent['overall_percentage'] = calculate_percentage($totPres, $totSess);
        $viewStudent['total_sessions'] = $totSess;
        $viewStudent['total_present'] = $totPres;
        $viewStudent['total_absent'] = $totSess - $totPres;
    }
}

// Fetch List with Search & Pagination
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(s.student_code LIKE :search OR s.roll_number LIKE :search OR s.first_name LIKE :search OR s.last_name LIKE :search OR s.email LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($deptFilter > 0) {
    $whereClauses[] = "s.department_id = :dept_filter";
    $params[':dept_filter'] = $deptFilter;
}

if ($secFilter > 0) {
    $whereClauses[] = "s.section_id = :sec_filter";
    $params[':sec_filter'] = $secFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "s.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s JOIN departments d ON s.department_id = d.id JOIN sections sec ON s.section_id = sec.id {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch page rows
$listSql = "SELECT s.*, d.department_code, d.department_name, sec.section_name,
            (SELECT COUNT(*) FROM student_subjects ss WHERE ss.student_id = s.id) AS enrolled_subjects_count
            FROM students s 
            JOIN departments d ON s.department_id = d.id 
            JOIN sections sec ON s.section_id = sec.id 
            {$whereSql} 
            ORDER BY d.department_code ASC, sec.section_name ASC, s.roll_number ASC 
            LIMIT {$limit} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$students = $listStmt->fetchAll();

// Available sections for the active department in forms/filters
$selectedDeptForForm = $editStudent['department_id'] ?? ($deptFilter > 0 ? $deptFilter : ($departments[0]['id'] ?? 0));
$sectionsForForm = $selectedDeptForForm > 0 ? get_sections_by_department($pdo, $selectedDeptForForm, false) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title">Students</h2>
        <p class="page-subtitle">Student enrollment directory, department allocations, and personal academic files</p>
    </div>
    <div class="page-header-actions">
        <?php if (!$showForm && !$viewStudent): ?>
            <a href="students.php?action=add" class="btn btn-primary">
                ➕ Register Student
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. View Student Profile Mode -->
<?php if ($action === 'view' && $viewStudent): ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--primary);">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= escape($viewStudent['first_name'] . ' ' . $viewStudent['last_name']) ?></h3>
                <div class="card-subtitle">
                    Student ID: <strong><?= escape($viewStudent['student_code']) ?></strong> &bull; 
                    Roll No: <strong><?= escape($viewStudent['roll_number']) ?></strong> &bull; 
                    <?= escape($viewStudent['department_name']) ?> (<?= escape($viewStudent['section_name']) ?>)
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="students.php?action=edit&id=<?= $viewStudent['id'] ?>" class="btn btn-sm btn-outline">✏️ Edit Student</a>
                <a href="students.php" class="btn btn-sm btn-secondary">✕ Close Profile</a>
            </div>
        </div>
        <div class="card-body">
            <!-- Metric Summary Cards -->
            <div class="stats-grid" style="margin-bottom: 20px;">
                <div class="stat-card">
                    <div>
                        <div class="stat-label">Enrollment Status</div>
                        <div style="margin-top: 4px;"><?= render_status_badge($viewStudent['status']) ?></div>
                        <div class="stat-sub">Admitted: <?= format_date($viewStudent['admission_date']) ?></div>
                    </div>
                    <div class="stat-icon stat-icon-primary">🎓</div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="stat-label">Overall Attendance</div>
                        <div class="stat-value" style="color: <?= $viewStudent['overall_percentage'] >= 75 ? 'var(--success)' : ($viewStudent['overall_percentage'] >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                            <?= number_format($viewStudent['overall_percentage'], 1) ?>%
                        </div>
                        <div class="stat-sub">Across all enrolled subjects</div>
                    </div>
                    <div class="stat-icon <?= $viewStudent['overall_percentage'] >= 75 ? 'stat-icon-success' : 'stat-icon-warning' ?>">📈</div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="stat-label">Sessions Present</div>
                        <div class="stat-value" style="color: var(--success);"><?= number_format($viewStudent['total_present']) ?></div>
                        <div class="stat-sub">Out of <?= number_format($viewStudent['total_sessions']) ?> total classes</div>
                    </div>
                    <div class="stat-icon stat-icon-success">✅</div>
                </div>

                <div class="stat-card">
                    <div>
                        <div class="stat-label">Sessions Absent</div>
                        <div class="stat-value" style="color: var(--danger);"><?= number_format($viewStudent['total_absent']) ?></div>
                        <div class="stat-sub">Missed classes</div>
                    </div>
                    <div class="stat-icon stat-icon-danger">❌</div>
                </div>
            </div>

            <!-- Subject-Wise Attendance Breakdown Table -->
            <div class="card" style="margin-bottom: 0; box-shadow: none;">
                <div class="card-header" style="background: #fafbfd;">
                    <h4 class="card-title" style="font-size: 14px;">Subject-Wise Attendance Breakdown</h4>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Subject Code</th>
                                    <th>Subject Name</th>
                                    <th style="width: 120px;" class="text-center">Total Sessions</th>
                                    <th style="width: 110px;" class="text-center">Present</th>
                                    <th style="width: 110px;" class="text-center">Absent</th>
                                    <th style="width: 130px;" class="text-center">Percentage</th>
                                    <th style="width: 160px;">Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($viewStudent['subjects_attendance'])): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state" style="padding: 24px;">
                                                <p class="empty-state-text">No subjects assigned yet to this student.</p>
                                                <a href="student-subjects.php" class="btn btn-sm btn-outline">Enroll in Subjects</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($viewStudent['subjects_attendance'] as $sub): 
                                        $pct = calculate_percentage($sub['present_count'], $sub['total_sessions']);
                                        $barClass = $pct >= 75 ? 'progress-bar-success' : ($pct >= 60 ? 'progress-bar-warning' : 'progress-bar-danger');
                                    ?>
                                        <tr>
                                            <td><strong><?= escape($sub['subject_code']) ?></strong></td>
                                            <td><?= escape($sub['subject_name']) ?></td>
                                            <td class="text-center"><?= number_format($sub['total_sessions']) ?></td>
                                            <td class="text-center" style="color: var(--success); font-weight: 700;"><?= number_format($sub['present_count']) ?></td>
                                            <td class="text-center" style="color: var(--danger); font-weight: 700;"><?= number_format($sub['absent_count']) ?></td>
                                            <td class="text-center" style="font-weight: 700; color: <?= $pct >= 75 ? 'var(--success)' : ($pct >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                                <?= $sub['total_sessions'] > 0 ? number_format($pct, 1) . '%' : '0.0%' ?>
                                            </td>
                                            <td>
                                                <div class="progress-container">
                                                    <div class="progress-bar <?= $barClass ?>" style="width: <?= $sub['total_sessions'] > 0 ? $pct : 0 ?>%;"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 3. Add / Edit Form Card -->
<?php if ($showForm): ?>
    <div class="card" style="border-left: 4px solid var(--primary); margin-bottom: 24px;">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= $editStudent ? 'Edit Student Profile' : 'Register New Student' ?></h3>
                <div class="card-subtitle"><?= $editStudent ? 'Update student records, class section, or contact details' : 'Enter enrollment credentials and assign to department and section' ?></div>
            </div>
            <a href="students.php" class="btn btn-sm btn-outline">✕ Cancel</a>
        </div>
        <div class="card-body">
            <form method="POST" action="students.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="student_id" value="<?= $editStudent['id'] ?? 0 ?>">

                <!-- Section A: Academic Details -->
                <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.6px; margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                    1. Academic & Cohort Allocation
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label" for="student_code">Student ID / Code <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="student_code" 
                            name="student_code" 
                            class="form-control" 
                            placeholder="e.g. STU2026001" 
                            maxlength="50" 
                            required 
                            value="<?= escape($editStudent['student_code'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="roll_number">Roll Number <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="roll_number" 
                            name="roll_number" 
                            class="form-control" 
                            placeholder="e.g. 101, CS2601" 
                            maxlength="30" 
                            required 
                            value="<?= escape($editStudent['roll_number'] ?? '') ?>"
                        >
                        <div class="form-text">Unique within the section.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="admission_date">Admission Date</label>
                        <input 
                            type="date" 
                            id="admission_date" 
                            name="admission_date" 
                            class="form-control" 
                            value="<?= escape($editStudent['admission_date'] ?? date('Y-m-d')) ?>"
                        >
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="department_select">Department <span class="required">*</span></label>
                        <select id="department_select" name="department_id" class="form-select" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= ($editStudent['department_id'] ?? $deptFilter) == $dept['id'] ? 'selected' : '' ?>>
                                    <?= escape($dept['department_name']) ?> (<?= escape($dept['department_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="section_select">Section <span class="required">*</span></label>
                        <select id="section_select" name="section_id" class="form-select" required>
                            <option value="">-- Select Section --</option>
                            <?php foreach ($sectionsForForm as $sec): ?>
                                <option value="<?= $sec['id'] ?>" <?= ($editStudent['section_id'] ?? $secFilter) == $sec['id'] ? 'selected' : '' ?>>
                                    <?= escape($sec['section_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Section B: Personal Information -->
                <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.6px; margin: 18px 0 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                    2. Personal & Contact Profile
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-control" 
                            placeholder="Student First Name" 
                            maxlength="100" 
                            required 
                            value="<?= escape($editStudent['first_name'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="form-control" 
                            placeholder="Student Last Name" 
                            maxlength="100" 
                            required 
                            value="<?= escape($editStudent['last_name'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="student@college.edu" 
                            maxlength="150" 
                            value="<?= escape($editStudent['email'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input 
                            type="text" 
                            id="phone" 
                            name="phone" 
                            class="form-control" 
                            placeholder="+91 9876543210" 
                            maxlength="20" 
                            value="<?= escape($editStudent['phone'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-select">
                            <option value="">-- Select Gender --</option>
                            <option value="Male" <?= ($editStudent['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($editStudent['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= ($editStudent['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <input 
                            type="date" 
                            id="date_of_birth" 
                            name="date_of_birth" 
                            class="form-control" 
                            value="<?= escape($editStudent['date_of_birth'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="status">Enrollment Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="Active" <?= ($editStudent['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($editStudent['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="address">Residential Address</label>
                    <textarea 
                        id="address" 
                        name="address" 
                        class="form-control" 
                        placeholder="Permanent or current residential address..."
                    ><?= escape($editStudent['address'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editStudent ? 'Save Student Record' : 'Register Student' ?>
                    </button>
                    <a href="students.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- 4. Filter & Search Toolbar -->
<div class="filter-toolbar">
    <form method="GET" action="students.php">
        <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search name, roll, or ID..." 
            value="<?= escape($search) ?>" 
            style="max-width: 240px;"
        >
        <select name="department_id" class="form-select" style="max-width: 200px;">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= $dept['id'] ?>" <?= $deptFilter == $dept['id'] ? 'selected' : '' ?>>
                    <?= escape($dept['department_code']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select" style="max-width: 140px;">
            <option value="">All Statuses</option>
            <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active Only</option>
            <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive Only</option>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (!empty($search) || $deptFilter > 0 || !empty($statusFilter)): ?>
            <a href="students.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>

        <div style="margin-left: auto; font-size: 12.5px; color: var(--text-muted);">
            Total: <strong><?= number_format($totalRows) ?></strong> student<?= $totalRows === 1 ? '' : 's' ?>
        </div>
    </form>
</div>

<!-- 5. Data Table Card -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 90px;">Roll No</th>
                        <th style="width: 110px;">Student ID</th>
                        <th>Student Name & Email</th>
                        <th>Class Cohort</th>
                        <th style="width: 130px;" class="text-center">Enrolled Courses</th>
                        <th style="width: 100px;" class="text-center">Status</th>
                        <th style="width: 160px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon">👨‍🎓</div>
                                    <div class="empty-state-title">No Students Found</div>
                                    <div class="empty-state-text">No students match your filter criteria, or none have been enrolled yet.</div>
                                    <a href="students.php?action=add" class="btn btn-primary btn-sm">➕ Register New Student</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $stu): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-secondary" style="font-size: 12px; font-weight: 700;">
                                        <?= escape($stu['roll_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                                        <?= escape($stu['student_code']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= escape($stu['first_name'] . ' ' . $stu['last_name']) ?></strong>
                                    <?php if (!empty($stu['email'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= escape($stu['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?= escape($stu['department_code']) ?></span>
                                    <span style="font-size: 12px; margin-left: 4px; color: var(--text-muted);"><?= escape($stu['section_name']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?= number_format($stu['enrolled_subjects_count']) ?> courses</span>
                                </td>
                                <td class="text-center"><?= render_status_badge($stu['status']) ?></td>
                                <td class="text-right">
                                    <div class="table-actions">
                                        <a href="students.php?action=view&id=<?= $stu['id'] ?>" class="btn btn-sm btn-outline" title="View Profile & Attendance">
                                            👁️ View
                                        </a>
                                        <a href="students.php?action=edit&id=<?= $stu['id'] ?>" class="btn btn-sm btn-outline" title="Edit Student">
                                            ✏️ Edit
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('students.php?action=delete&id=<?= $stu['id'] ?>', 'student <?= escape($stu['first_name'] . ' ' . $stu['last_name']) ?>')"
                                            title="Delete Student"
                                        >
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 6. Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> students
            </div>
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?p=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&department_id=<?= $deptFilter ?>&status=<?= urlencode($statusFilter) ?>">Prev</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?p=<?= $i ?>&search=<?= urlencode($search) ?>&department_id=<?= $deptFilter ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?p=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&department_id=<?= $deptFilter ?>&status=<?= urlencode($statusFilter) ?>">Next</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
