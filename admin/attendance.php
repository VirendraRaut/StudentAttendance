<?php

/**
 * Attendance Management - Mark Attendance
 * Student Attendance Management System
 */

$pageTitle = 'Mark Attendance';
$currentPage = 'attendance';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$departments = get_active_departments($pdo, true);

// Parameters for sheet selection
$selectedDate = trim($_GET['date'] ?? date('Y-m-d'));
$selectedDept = (int) ($_GET['department_id'] ?? 0);
$selectedSec  = (int) ($_GET['section_id'] ?? 0);
$selectedSub  = (int) ($_GET['subject_id'] ?? 0);

$sections = [];
$subjects = [];
$eligibleStudents = [];
$alreadyRecorded = false;
$existingRecords = [];

if ($selectedDept > 0) {
    $sections = get_sections_by_department($pdo, $selectedDept, true);
    $subjects = get_subjects_by_department($pdo, $selectedDept, true);
}

// Fetch Eligible Students and Existing Attendance
if ($selectedDept > 0 && $selectedSec > 0 && $selectedSub > 0 && !empty($selectedDate)) {
    // 1. Check department consistency
    $vDept = $pdo->prepare("SELECT id FROM sections WHERE id = :sec_id AND department_id = :dept_id");
    $vDept->execute([':sec_id' => $selectedSec, ':dept_id' => $selectedDept]);
    $validSec = $vDept->fetch();

    $vSub = $pdo->prepare("SELECT id FROM subjects WHERE id = :sub_id AND department_id = :dept_id");
    $vSub->execute([':sub_id' => $selectedSub, ':dept_id' => $selectedDept]);
    $validSub = $vSub->fetch();

    if ($validSec && $validSub) {
        // 2. Fetch active students who are enrolled in this subject and belong to this section
        $studentStmt = $pdo->prepare(
            "SELECT 
                s.id AS student_id,
                s.student_code,
                s.roll_number,
                s.first_name,
                s.last_name,
                s.gender,
                ss.id AS assignment_id,
                a.id AS attendance_id,
                a.attendance_status,
                a.remarks
             FROM students s
             JOIN student_subjects ss ON s.id = ss.student_id AND ss.subject_id = :subject_id AND ss.status = 'Active'
             LEFT JOIN attendance a ON s.id = a.student_id AND a.subject_id = :subject_id_att AND a.attendance_date = :att_date
             WHERE s.department_id = :dept_id AND s.section_id = :sec_id AND s.status = 'Active'
             ORDER BY s.roll_number ASC, s.first_name ASC"
        );
        $studentStmt->execute([
            ':subject_id'     => $selectedSub,
            ':subject_id_att' => $selectedSub,
            ':att_date'       => $selectedDate,
            ':dept_id'        => $selectedDept,
            ':sec_id'         => $selectedSec
        ]);
        $eligibleStudents = $studentStmt->fetchAll();

        // Check if attendance has already been recorded for this date and subject
        foreach ($eligibleStudents as $row) {
            if (!empty($row['attendance_id'])) {
                $alreadyRecorded = true;
                break;
            }
        }
    }
}

// Handle Attendance Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token. Please try again.');
        redirect("admin/attendance.php?date={$selectedDate}&department_id={$selectedDept}&section_id={$selectedSec}&subject_id={$selectedSub}");
    }

    $postDate      = trim($_POST['attendance_date'] ?? '');
    $postDept      = (int) ($_POST['department_id'] ?? 0);
    $postSec       = (int) ($_POST['section_id'] ?? 0);
    $postSub       = (int) ($_POST['subject_id'] ?? 0);
    $attendanceArr = $_POST['attendance'] ?? []; // Array: [student_id => 'Present'|'Absent']
    $remarksArr    = $_POST['remarks'] ?? [];    // Array: [student_id => string]

    // Basic Validations
    if (empty($postDate) || $postDept <= 0 || $postSec <= 0 || $postSub <= 0) {
        set_flash('danger', 'Invalid session parameters. Please ensure date, department, section, and subject are selected.');
        redirect("admin/attendance.php");
    }

    if (empty($attendanceArr)) {
        set_flash('warning', 'No students were marked for attendance.');
        redirect("admin/attendance.php?date={$postDate}&department_id={$postDept}&section_id={$postSec}&subject_id={$postSub}");
    }

    $adminId = (int) ($_SESSION['admin_id'] ?? 0);

    try {
        // Use Database Transaction
        $pdo->beginTransaction();

        $savedPresent = 0;
        $savedAbsent  = 0;

        // Upsert attendance statement
        $upsertStmt = $pdo->prepare(
            "INSERT INTO attendance 
             (student_id, subject_id, attendance_date, attendance_status, remarks, marked_by) 
             VALUES 
             (:student_id, :subject_id, :att_date, :status, :remarks, :marked_by)
             ON DUPLICATE KEY UPDATE 
                 attendance_status = VALUES(attendance_status),
                 remarks = VALUES(remarks),
                 marked_by = VALUES(marked_by),
                 updated_at = CURRENT_TIMESTAMP"
        );

        // Verification statement: ensure each student is genuinely active and assigned to subject
        $verifyAssignment = $pdo->prepare(
            "SELECT ss.id 
             FROM student_subjects ss 
             JOIN students s ON ss.student_id = s.id 
             WHERE ss.student_id = :stu_id AND ss.subject_id = :sub_id 
               AND ss.status = 'Active' AND s.status = 'Active' 
             LIMIT 1"
        );

        foreach ($attendanceArr as $sId => $rawStatus) {
            $studentId = (int) $sId;
            $status = in_array($rawStatus, ['Present', 'Absent']) ? $rawStatus : 'Absent';
            $remarks = trim($remarksArr[$studentId] ?? '');

            // Verify active enrollment
            $verifyAssignment->execute([':stu_id' => $studentId, ':sub_id' => $postSub]);
            if (!$verifyAssignment->fetch()) {
                // Skip if not actively assigned
                continue;
            }

            // Execute upsert
            $upsertStmt->execute([
                ':student_id' => $studentId,
                ':subject_id' => $postSub,
                ':att_date'   => $postDate,
                ':status'     => $status,
                ':remarks'    => !empty($remarks) ? $remarks : null,
                ':marked_by'  => $adminId
            ]);

            if ($status === 'Present') {
                $savedPresent++;
            } else {
                $savedAbsent++;
            }
        }

        // Commit transaction
        $pdo->commit();

        $totalSaved = $savedPresent + $savedAbsent;
        set_flash('success', "Attendance saved successfully for {$totalSaved} student(s) on " . format_date($postDate) . "! (Present: {$savedPresent}, Absent: {$savedAbsent})");

        redirect("admin/attendance.php?date={$postDate}&department_id={$postDept}&section_id={$postSec}&subject_id={$postSub}");

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Attendance save transaction error: " . $e->getMessage());
        set_flash('danger', 'A database error occurred while saving attendance. Transaction was safely aborted.');
        redirect("admin/attendance.php?date={$postDate}&department_id={$postDept}&section_id={$postSec}&subject_id={$postSub}");
    }
}

$extraScripts = '<script src="' . BASE_URL . '/assets/js/attendance.js"></script>';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filter & Selection Bar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div>
            <h3 class="card-title">Select Attendance Session</h3>
            <div class="card-subtitle">Choose the date, department, section, and subject to record attendance</div>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="attendance.php" id="sessionForm">
            <div class="form-row-4">
                <div class="form-group">
                    <label class="form-label" for="date">Attendance Date <span class="required">*</span></label>
                    <input 
                        type="date" 
                        id="date" 
                        name="date" 
                        class="form-control" 
                        value="<?= escape($selectedDate) ?>" 
                        max="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="department_select">Department <span class="required">*</span></label>
                    <select id="department_select" name="department_id" class="form-select" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= $selectedDept == $dept['id'] ? 'selected' : '' ?>>
                                <?= escape($dept['department_name']) ?> (<?= escape($dept['department_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="section_select">Section <span class="required">*</span></label>
                    <select id="section_select" name="section_id" class="form-select" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?= $sec['id'] ?>" <?= $selectedSec == $sec['id'] ? 'selected' : '' ?>>
                                <?= escape($sec['section_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subject_select">Subject <span class="required">*</span></label>
                    <select id="subject_select" name="subject_id" class="form-select" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>" <?= $selectedSub == $sub['id'] ? 'selected' : '' ?>>
                                <?= escape($sub['subject_code']) ?> - <?= escape($sub['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 6px;">
                <button type="submit" class="btn btn-primary">
                    🔍 Load Eligible Students
                </button>
                <a href="attendance.php" class="btn btn-outline">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedDept > 0 && $selectedSec > 0 && $selectedSub > 0): ?>

    <?php if ($alreadyRecorded): ?>
        <div class="alert alert-info">
            <span>
                ℹ️ <strong>Previously Recorded:</strong> Attendance for this subject on <strong><?= format_date($selectedDate) ?></strong> has already been saved. Existing marks are displayed below and can be updated.
            </span>
        </div>
    <?php endif; ?>

    <?php if (empty($eligibleStudents)): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">⚠️</div>
                    <div class="empty-state-title">No Eligible Students Found</div>
                    <div class="empty-state-text">
                        There are no active students in this department & section assigned to the selected subject.
                        <br>
                        Please visit <a href="student-subjects.php" style="font-weight: 700; text-decoration: underline;">Student-Subject Assignments</a> to enroll students into this course.
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- Attendance Marking Sheet -->
        <form method="POST" action="attendance.php" id="attendanceForm">
            <?= csrf_input() ?>
            <input type="hidden" name="attendance_date" value="<?= escape($selectedDate) ?>">
            <input type="hidden" name="department_id" value="<?= $selectedDept ?>">
            <input type="hidden" name="section_id" value="<?= $selectedSec ?>">
            <input type="hidden" name="subject_id" value="<?= $selectedSub ?>">

            <!-- Live Summary Counter & Bulk Action Controls -->
            <div class="attendance-summary-bar">
                <div class="summary-pill">
                    <span>Total Students:</span>
                    <span class="summary-pill-num badge badge-secondary" id="count_total"><?= count($eligibleStudents) ?></span>
                </div>
                <div class="summary-pill">
                    <span>Present:</span>
                    <span class="summary-pill-num badge badge-success" id="count_present">0</span>
                </div>
                <div class="summary-pill">
                    <span>Absent:</span>
                    <span class="summary-pill-num badge badge-danger" id="count_absent">0</span>
                </div>
                <div class="summary-pill">
                    <span>Attendance Rate:</span>
                    <span class="summary-pill-num badge badge-info" id="count_percentage">0.0%</span>
                </div>

                <div style="margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                    <input 
                        type="text" 
                        id="student_search_input" 
                        class="form-control" 
                        placeholder="Search student or roll in list..." 
                        style="width: 200px; padding: 6px 12px; font-size: 12.5px;"
                    >
                    <button type="button" class="btn btn-sm btn-success" id="markAllPresentBtn">
                        ✅ Mark All Present
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="markAllAbsentBtn">
                        ❌ Mark All Absent
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Class Roll Call Sheet</h3>
                        <div class="card-subtitle"><?= count($eligibleStudents) ?> students eligible for attendance</div>
                    </div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Roll No</th>
                                    <th>Student Code</th>
                                    <th>Student Name</th>
                                    <th>Gender</th>
                                    <th style="width: 220px; text-align: center;">Attendance Status</th>
                                    <th style="width: 260px;">Remarks (Optional)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eligibleStudents as $idx => $student): 
                                    // Determine default status: if already recorded use recorded, else default to 'Present'
                                    $currentStatus = $student['attendance_status'] ?? 'Present';
                                    $currentRemarks = $student['remarks'] ?? '';
                                ?>
                                    <tr class="attendance-student-row">
                                        <td>
                                            <span class="badge badge-secondary" style="font-size: 13px; font-weight: 700;">
                                                <?= escape($student['roll_number']) ?>
                                            </span>
                                        </td>
                                        <td><?= escape($student['student_code']) ?></td>
                                        <td>
                                            <strong><?= escape($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                                        </td>
                                        <td><?= escape($student['gender'] ?? '—') ?></td>
                                        <td style="text-align: center;">
                                            <div class="attendance-status-switch">
                                                <label>
                                                    <input 
                                                        type="radio" 
                                                        name="attendance[<?= $student['student_id'] ?>]" 
                                                        value="Present" 
                                                        <?= $currentStatus === 'Present' ? 'checked' : '' ?>
                                                    >
                                                    <span class="present-label">Present</span>
                                                </label>
                                                <label>
                                                    <input 
                                                        type="radio" 
                                                        name="attendance[<?= $student['student_id'] ?>]" 
                                                        value="Absent" 
                                                        <?= $currentStatus === 'Absent' ? 'checked' : '' ?>
                                                    >
                                                    <span class="absent-label">Absent</span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input 
                                                type="text" 
                                                name="remarks[<?= $student['student_id'] ?>]" 
                                                class="form-control" 
                                                placeholder="Remarks (e.g. Late, Medical)" 
                                                value="<?= escape($currentRemarks) ?>" 
                                                style="padding: 6px 10px; font-size: 12.5px;"
                                            >
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer" style="padding: 16px 24px;">
                    <div style="font-size: 12.5px; color: var(--text-muted);">
                        🔒 Changes are saved via safe transactional database operations with audit logs.
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="padding: 11px 32px; font-size: 14px;">
                        💾 Save Attendance Records
                    </button>
                </div>
            </div>
        </form>

    <?php endif; ?>

<?php else: ?>
    <!-- Initial Guide Card -->
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-title">Select Session to Mark Attendance</div>
                <div class="empty-state-text">
                    Select a date, department, section, and subject above, then click <strong>Load Eligible Students</strong> to begin marking attendance.
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
