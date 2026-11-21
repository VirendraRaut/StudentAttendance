<?php

/**
 * Student Management
 * Student Attendance Management System
 */

$pageTitle = 'Students';
$currentPage = 'students';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$editStudent = null;
$viewStudent = null;

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

    // Validation
    if (empty($studentCode) || empty($rollNumber) || empty($firstName) || empty($lastName) || $deptId <= 0 || $sectionId <= 0) {
        set_flash('danger', 'Student Code, Roll Number, Name, Department, and Section are required.');
        redirect($studentId > 0 ? "admin/students.php?action=edit&id={$studentId}" : "admin/students.php?action=add");
    }

    // STRICT VALIDATION: Ensure the selected section belongs to the selected department
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
            // Check unique student_code
            $chkCode = $pdo->prepare("SELECT id FROM students WHERE student_code = :code AND id != :id LIMIT 1");
            $chkCode->execute([':code' => $studentCode, ':id' => $studentId]);
            if ($chkCode->fetch()) {
                set_flash('danger', 'Another student with this Student Code already exists.');
                redirect("admin/students.php?action=edit&id={$studentId}");
            }

            // Check unique roll_number + section_id
            $chkRoll = $pdo->prepare("SELECT id FROM students WHERE roll_number = :roll AND section_id = :sec_id AND id != :id LIMIT 1");
            $chkRoll->execute([':roll' => $rollNumber, ':sec_id' => $sectionId, ':id' => $studentId]);
            if ($chkRoll->fetch()) {
                set_flash('danger', "Roll number '{$rollNumber}' is already registered in this section.");
                redirect("admin/students.php?action=edit&id={$studentId}");
            }

            // Update
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
            // Check unique student_code
            $chkCode = $pdo->prepare("SELECT id FROM students WHERE student_code = :code LIMIT 1");
            $chkCode->execute([':code' => $studentCode]);
            if ($chkCode->fetch()) {
                set_flash('danger', 'A student with this Student Code already exists.');
                redirect("admin/students.php?action=add");
            }

            // Check unique roll_number + section_id
            $chkRoll = $pdo->prepare("SELECT id FROM students WHERE roll_number = :roll AND section_id = :sec_id LIMIT 1");
            $chkRoll->execute([':roll' => $rollNumber, ':sec_id' => $sectionId]);
            if ($chkRoll->fetch()) {
                set_flash('danger', "Roll number '{$rollNumber}' already exists in the selected section.");
                redirect("admin/students.php?action=add");
            }

            // Insert
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
        // Safe check for historical attendance
        $chkAtt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = :id");
        $chkAtt->execute([':id' => $editId]);
        $hasAttendance = (int) $chkAtt->fetchColumn();

        $chkSub = $pdo->prepare("SELECT COUNT(*) FROM student_subjects WHERE student_id = :id");
        $chkSub->execute([':id' => $editId]);
        $hasSubjects = (int) $chkSub->fetchColumn();

        if ($hasAttendance > 0) {
            set_flash('danger', "Cannot delete this student because they have {$hasAttendance} recorded attendance session(s). Historical attendance cannot be destroyed. You may set their status to 'Inactive' instead.");
        } elseif ($hasSubjects > 0) {
            set_flash('danger', "Cannot delete this student because they are assigned to {$hasSubjects} subject(s). Please unassign their subjects first.");
        } else {
            $delStmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
            $delStmt->execute([':id' => $editId]);
            set_flash('success', 'Student record deleted successfully.');
        }

    } catch (PDOException $e) {
        error_log("Student delete error: " . $e->getMessage());
        set_flash('danger', 'Unable to delete student. Foreign key constraints protect this record.');
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

// Fetch record if viewing details & attendance summary
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
        // Fetch Subject-wise attendance breakdown
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

        // Calculate overall student attendance
        $totSess = 0;
        $totPres = 0;
        foreach ($viewStudent['subjects_attendance'] as $sa) {
            $totSess += (int) $sa['total_sessions'];
            $totPres += (int) $sa['present_count'];
        }
        $viewStudent['overall_percentage'] = calculate_percentage($totPres, $totSess);
        $viewStudent['total_sessions'] = $totSess;
        $viewStudent['total_present'] = $totPres;
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

// Available sections for the active/selected department
$selectedDeptForForm = $editStudent['department_id'] ?? ($deptFilter > 0 ? $deptFilter : ($departments[0]['id'] ?? 0));
$sectionsForForm = $selectedDeptForForm > 0 ? get_sections_by_department($pdo, $selectedDeptForForm, false) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($action === 'view' && $viewStudent): ?>
    <!-- Student Detail & Attendance Card -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= escape($viewStudent['first_name'] . ' ' . $viewStudent['last_name']) ?></h3>
                <div class="card-subtitle">
                    Student Code: <strong><?= escape($viewStudent['student_code']) ?></strong> &bull; 
                    Roll No: <strong><?= escape($viewStudent['roll_number']) ?></strong> &bull; 
                    Department: <strong><?= escape($viewStudent['department_name']) ?></strong> &bull; 
                    Section: <strong><?= escape($viewStudent['section_name']) ?></strong>
                </div>
            </div>
            <div>
                <a href="students.php?action=edit&id=<?= $viewStudent['id'] ?>" class="btn btn-sm btn-outline">Edit Student</a>
                <a href="students.php" class="btn btn-sm btn-secondary">Back to List</a>
            </div>
        </div>
        <div class="card-body">
            <!-- Student Quick Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div style="background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Status</div>
                    <div style="margin-top: 4px;"><?= render_status_badge($viewStudent['status']) ?></div>
                </div>
                <div style="background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Overall Attendance</div>
                    <div style="font-size: 20px; font-weight: 800; color: <?= $viewStudent['overall_percentage'] >= 75 ? 'var(--success)' : ($viewStudent['overall_percentage'] >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                        <?= number_format($viewStudent['overall_percentage'], 1) ?>%
                    </div>
                </div>
                <div style="background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Sessions Present</div>
                    <div style="font-size: 20px; font-weight: 800; color: var(--success);"><?= $viewStudent['total_present'] ?> / <?= $viewStudent['total_sessions'] ?></div>
                </div>
                <div style="background: #f8fafc; padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Admission Date</div>
                    <div style="font-size: 14px; font-weight: 600; margin-top: 4px;"><?= format_date($viewStudent['admission_date']) ?></div>
                </div>
            </div>

            <!-- Subject-wise Attendance Table -->
            <h4 style="font-size: 15px; font-weight: 700; margin-bottom: 12px;">Subject-Wise Attendance Breakdown</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Total Classes</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Attendance %</th>
                            <th style="min-width: 150px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($viewStudent['subjects_attendance'])): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state" style="padding: 20px;">
                                        No subjects assigned yet to this student.
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
                                    <td><?= $sub['total_sessions'] ?></td>
                                    <td style="color: var(--success); font-weight: 600;"><?= $sub['present_count'] ?></td>
                                    <td style="color: var(--danger); font-weight: 600;"><?= $sub['absent_count'] ?></td>
                                    <td style="font-weight: 700; color: <?= $pct >= 75 ? 'var(--success)' : ($pct >= 60 ? 'var(--warning)' : 'var(--danger)') ?>;">
                                        <?= $sub['total_sessions'] > 0 ? number_format($pct, 1) . '%' : '0.0%' ?>
                                    </td>
                                    <td>
                                        <div class="progress-container" style="height: 7px; margin-top: 0;">
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
<?php endif; ?>

<div style="display: grid; grid-template-columns: <?= ($action === 'add' || $editStudent) ? '1fr' : '1fr' ?>; gap: 24px; align-items: start;">
    
    <?php if ($action === 'add' || $editStudent): ?>
        <!-- Add / Edit Student Form -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title"><?= $editStudent ? 'Edit Student Details' : 'Register New Student' ?></h3>
                    <div class="card-subtitle">Ensure the selected section belongs to the selected department</div>
                </div>
                <a href="students.php" class="btn btn-sm btn-outline">Cancel</a>
            </div>
            <div class="card-body">
                <form method="POST" action="students.php" autocomplete="off">
                    <?= csrf_input() ?>
                    <input type="hidden" name="student_id" value="<?= $editStudent['id'] ?? 0 ?>">

                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label" for="student_code">Student Code / ID <span class="required">*</span></label>
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
                                placeholder="e.g. 01, 101, CS2601" 
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

                    <div class="form-row">
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

                    <div class="form-row">
                        <!-- Department Selector with Dynamic Section Loading -->
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

                        <!-- Section Selector -->
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
                            <div class="form-text">Must belong to the selected department.</div>
                        </div>
                    </div>

                    <div class="form-row-3">
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

                    <div class="form-row">
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
                            <label class="form-label" for="status">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="Active" <?= ($editStudent['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= ($editStudent['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="address">Residential Address</label>
                        <textarea 
                            id="address" 
                            name="address" 
                            class="form-control" 
                            placeholder="Student permanent or contact address..."
                        ><?= escape($editStudent['address'] ?? '') ?></textarea>
                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 10px;">
                        <button type="submit" class="btn btn-primary" style="padding: 11px 28px;">
                            <?= $editStudent ? 'Update Student Record' : 'Save Student' ?>
                        </button>
                        <a href="students.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Students Directory Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">All Students (<?= number_format($totalRows) ?>)</h3>
                <div class="card-subtitle">Student enrollment and attendance records</div>
            </div>
            <?php if ($action !== 'add' && !$editStudent): ?>
                <a href="students.php?action=add" class="btn btn-primary">
                    ➕ Register New Student
                </a>
            <?php endif; ?>
        </div>

        <!-- Filter / Search Toolbar -->
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); background: #fafbfd;">
            <form method="GET" action="students.php" class="filter-actions" style="margin: 0; display: flex; gap: 12px; flex-wrap: wrap;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search name, roll, code..." 
                    value="<?= escape($search) ?>" 
                    style="max-width: 220px;"
                >
                <select name="department_id" class="form-select" style="max-width: 180px;">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $deptFilter == $dept['id'] ? 'selected' : '' ?>>
                            <?= escape($dept['department_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select" style="max-width: 130px;">
                    <option value="">All Statuses</option>
                    <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if (!empty($search) || $deptFilter > 0 || !empty($statusFilter)): ?>
                    <a href="students.php" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Roll No</th>
                            <th>Student Name</th>
                            <th>Department</th>
                            <th>Section</th>
                            <th>Assigned Subjects</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">👨‍🎓</div>
                                        <div class="empty-state-title">No Students Found</div>
                                        <div class="empty-state-text">Click "Register New Student" to enroll students into classes.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $stu): ?>
                                <tr>
                                    <td><strong><?= escape($stu['student_code']) ?></strong></td>
                                    <td><span class="badge badge-secondary" style="font-size: 12px;"><?= escape($stu['roll_number']) ?></span></td>
                                    <td>
                                        <strong><?= escape($stu['first_name'] . ' ' . $stu['last_name']) ?></strong>
                                        <?php if (!empty($stu['email'])): ?>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?= escape($stu['email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-secondary"><?= escape($stu['department_code']) ?></span></td>
                                    <td><?= escape($stu['section_name']) ?></td>
                                    <td><span class="badge badge-info"><?= number_format($stu['enrolled_subjects_count']) ?> subjects</span></td>
                                    <td><?= render_status_badge($stu['status']) ?></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="students.php?action=view&id=<?= $stu['id'] ?>" class="btn btn-sm btn-outline" title="View Profile & Attendance">
                                            👁️ View
                                        </a>
                                        <a href="students.php?action=edit&id=<?= $stu['id'] ?>" class="btn btn-sm btn-outline">
                                            ✏️ Edit
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('students.php?action=delete&id=<?= $stu['id'] ?>', 'student <?= escape($stu['first_name'] . ' ' . $stu['last_name']) ?>')"
                                        >
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <div style="font-size: 12px; color: var(--text-muted);">
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
