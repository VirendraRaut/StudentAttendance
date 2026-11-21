<?php

/**
 * Student-Subject Assignments Management - Redesigned
 * Student Attendance Management System
 */

$pageTitle = 'Student Course Enrollments';
$currentPage = 'student_subjects';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$showForm = ($action === 'add');

// Search & Filters
$search = trim($_GET['search'] ?? '');
$deptFilter = (int) ($_GET['department_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$departments = get_active_departments($pdo, false);

// Handle POST: Assign
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token. Please try again.');
        redirect('admin/student-subjects.php');
    }

    $assignMode = $_POST['assign_mode'] ?? 'individual';
    $subjectId  = (int) ($_POST['subject_id'] ?? 0);
    $status     = $_POST['status'] ?? 'Active';

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    if ($subjectId <= 0) {
        set_flash('danger', 'Please select a curriculum subject.');
        redirect('admin/student-subjects.php?action=add');
    }

    $subStmt = $pdo->prepare("SELECT id, department_id, subject_name FROM subjects WHERE id = :id LIMIT 1");
    $subStmt->execute([':id' => $subjectId]);
    $subjectData = $subStmt->fetch();

    if (!$subjectData) {
        set_flash('danger', 'Selected subject does not exist.');
        redirect('admin/student-subjects.php?action=add');
    }

    if ($assignMode === 'individual') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        if ($studentId <= 0) {
            set_flash('danger', 'Please select a student.');
            redirect('admin/student-subjects.php?action=add');
        }

        $stuStmt = $pdo->prepare("SELECT id, department_id, first_name, last_name FROM students WHERE id = :id LIMIT 1");
        $stuStmt->execute([':id' => $studentId]);
        $studentData = $stuStmt->fetch();

        if (!$studentData) {
            set_flash('danger', 'Selected student does not exist.');
            redirect('admin/student-subjects.php?action=add');
        }

        if ($studentData['department_id'] != $subjectData['department_id']) {
            set_flash('danger', 'Department Incompatibility: The student and subject must belong to the same department.');
            redirect('admin/student-subjects.php?action=add');
        }

        try {
            $insertStmt = $pdo->prepare(
                "INSERT INTO student_subjects (student_id, subject_id, status) 
                 VALUES (:student_id, :subject_id, :status)
                 ON DUPLICATE KEY UPDATE status = VALUES(status)"
            );
            $insertStmt->execute([
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':status'     => $status
            ]);
            set_flash('success', "Enrolled {$studentData['first_name']} {$studentData['last_name']} into '{$subjectData['subject_name']}' successfully.");
        } catch (PDOException $e) {
            error_log("Student-Subject assignment error: " . $e->getMessage());
            set_flash('danger', 'Database error occurred while assigning subject.');
        }

    } elseif ($assignMode === 'batch') {
        $deptId = (int) ($_POST['department_id'] ?? 0);
        $secId  = (int) ($_POST['section_id'] ?? 0);

        if ($deptId <= 0 || $secId <= 0) {
            set_flash('danger', 'Please select both Department and Section for batch enrollment.');
            redirect('admin/student-subjects.php?action=add');
        }

        if ($deptId != $subjectData['department_id']) {
            set_flash('danger', 'Department Incompatibility: The selected section department does not match the subject department.');
            redirect('admin/student-subjects.php?action=add');
        }

        try {
            $pdo->beginTransaction();

            $fetchStudents = $pdo->prepare(
                "SELECT id FROM students 
                 WHERE department_id = :dept_id AND section_id = :sec_id AND status = 'Active'"
            );
            $fetchStudents->execute([':dept_id' => $deptId, ':sec_id' => $secId]);
            $eligibleStudents = $fetchStudents->fetchAll(PDO::FETCH_COLUMN);

            if (empty($eligibleStudents)) {
                $pdo->rollBack();
                set_flash('warning', 'No active students found in the selected section.');
                redirect('admin/student-subjects.php?action=add');
            }

            $batchInsert = $pdo->prepare(
                "INSERT INTO student_subjects (student_id, subject_id, status) 
                 VALUES (:student_id, :subject_id, :status)
                 ON DUPLICATE KEY UPDATE status = VALUES(status)"
            );

            $assignedCount = 0;
            foreach ($eligibleStudents as $sId) {
                $batchInsert->execute([
                    ':student_id' => $sId,
                    ':subject_id' => $subjectId,
                    ':status'     => $status
                ]);
                $assignedCount++;
            }

            $pdo->commit();
            set_flash('success', "Batch Enrolled {$assignedCount} student(s) into '{$subjectData['subject_name']}' successfully.");

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Batch assignment error: " . $e->getMessage());
            set_flash('danger', 'Database error occurred during batch enrollment.');
        }
    }

    redirect('admin/student-subjects.php');
}

// Handle GET: Delete
if ($action === 'delete' && $editId > 0) {
    try {
        $chkStmt = $pdo->prepare("SELECT student_id, subject_id FROM student_subjects WHERE id = :id LIMIT 1");
        $chkStmt->execute([':id' => $editId]);
        $assignment = $chkStmt->fetch();

        if ($assignment) {
            $chkAtt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = :stu_id AND subject_id = :sub_id");
            $chkAtt->execute([':stu_id' => $assignment['student_id'], ':sub_id' => $assignment['subject_id']]);
            $attendanceCount = (int) $chkAtt->fetchColumn();

            if ($attendanceCount > 0) {
                set_flash('danger', "Cannot remove enrollment: {$attendanceCount} attendance session(s) recorded. Historical records are preserved. Toggle status to 'Inactive' instead.");
            } else {
                $delStmt = $pdo->prepare("DELETE FROM student_subjects WHERE id = :id");
                $delStmt->execute([':id' => $editId]);
                set_flash('success', 'Course enrollment removed successfully.');
            }
        } else {
            set_flash('danger', 'Assignment record not found.');
        }

    } catch (PDOException $e) {
        error_log("Delete student-subject error: " . $e->getMessage());
        set_flash('danger', 'Database error occurred while deleting assignment.');
    }
    redirect('admin/student-subjects.php');
}

// Handle GET: Toggle Status
if ($action === 'toggle_status' && $editId > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE student_subjects SET status = IF(status = 'Active', 'Inactive', 'Active') WHERE id = :id");
        $stmt->execute([':id' => $editId]);
        set_flash('success', 'Enrollment status updated.');
    } catch (PDOException $e) {
        error_log("Toggle status error: " . $e->getMessage());
        set_flash('danger', 'Unable to toggle status.');
    }
    redirect('admin/student-subjects.php');
}

// Fetch List with Search & Pagination
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(s.student_code LIKE :search OR s.roll_number LIKE :search OR s.first_name LIKE :search OR s.last_name LIKE :search OR sub.subject_code LIKE :search OR sub.subject_name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($deptFilter > 0) {
    $whereClauses[] = "s.department_id = :dept_filter";
    $params[':dept_filter'] = $deptFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "ss.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';

// Count total
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) 
     FROM student_subjects ss 
     JOIN students s ON ss.student_id = s.id 
     JOIN subjects sub ON ss.subject_id = sub.id 
     JOIN departments d ON s.department_id = d.id 
     JOIN sections sec ON s.section_id = sec.id 
     {$whereSql}"
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch page rows
$listSql = "SELECT ss.*, s.student_code, s.roll_number, s.first_name, s.last_name, 
            d.department_code, d.department_name, sec.section_name,
            sub.subject_code, sub.subject_name,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = ss.student_id AND a.subject_id = ss.subject_id) AS attendance_count
            FROM student_subjects ss 
            JOIN students s ON ss.student_id = s.id 
            JOIN subjects sub ON ss.subject_id = sub.id 
            JOIN departments d ON s.department_id = d.id 
            JOIN sections sec ON s.section_id = sec.id 
            {$whereSql} 
            ORDER BY d.department_code ASC, sec.section_name ASC, s.roll_number ASC, sub.subject_code ASC 
            LIMIT {$limit} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$assignments = $listStmt->fetchAll();

// Active subjects & students for form
$allSubjects = $pdo->query("SELECT id, subject_code, subject_name, department_id FROM subjects WHERE status = 'Active' ORDER BY subject_name ASC")->fetchAll();
$allStudents = $pdo->query("SELECT s.id, s.student_code, s.roll_number, s.first_name, s.last_name, s.department_id, d.department_code, sec.section_name FROM students s JOIN departments d ON s.department_id = d.id JOIN sections sec ON s.section_id = sec.id WHERE s.status = 'Active' ORDER BY d.department_code ASC, sec.section_name ASC, s.roll_number ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title">Student Course Enrollments</h2>
        <p class="page-subtitle">Map students to curriculum subjects individually or in batch cohorts by section</p>
    </div>
    <div class="page-header-actions">
        <?php if (!$showForm): ?>
            <a href="student-subjects.php?action=add" class="btn btn-primary">
                ➕ Enroll Students
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Add / Enroll Form Card -->
<?php if ($showForm): ?>
    <div class="card" style="border-left: 4px solid var(--primary); margin-bottom: 24px;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Enroll Students into Course</h3>
                <div class="card-subtitle">Select individual student or enroll all active students of a section</div>
            </div>
            <a href="student-subjects.php" class="btn btn-sm btn-outline">✕ Cancel</a>
        </div>
        <div class="card-body">
            <!-- Mode Switcher -->
            <div style="display: flex; gap: 8px; margin-bottom: 18px;">
                <button type="button" class="btn btn-sm btn-primary" id="btnModeIndividual" onclick="switchAssignMode('individual')">
                    👤 Individual Student
                </button>
                <button type="button" class="btn btn-sm btn-outline" id="btnModeBatch" onclick="switchAssignMode('batch')">
                    👥 Batch Enroll by Section
                </button>
            </div>

            <form method="POST" action="student-subjects.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="assign_mode" id="assign_mode_input" value="individual">

                <!-- Individual Mode Fields -->
                <div id="individual_fields">
                    <div class="form-group">
                        <label class="form-label" for="student_id">Select Student <span class="required">*</span></label>
                        <select id="student_id" name="student_id" class="form-select">
                            <option value="">-- Choose Student --</option>
                            <?php foreach ($allStudents as $st): ?>
                                <option value="<?= $st['id'] ?>" data-dept="<?= $st['department_id'] ?>">
                                    [<?= escape($st['department_code']) ?> - <?= escape($st['section_name']) ?>] Roll: <?= escape($st['roll_number']) ?> &bull; <?= escape($st['first_name'] . ' ' . $st['last_name']) ?> (<?= escape($st['student_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Batch Mode Fields -->
                <div id="batch_fields" style="display: none;">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="batch_dept_select">Department <span class="required">*</span></label>
                            <select id="batch_dept_select" name="department_id" class="form-select">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= escape($dept['department_name']) ?> (<?= escape($dept['department_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="batch_sec_select">Section <span class="required">*</span></label>
                            <select id="batch_sec_select" name="section_id" class="form-select">
                                <option value="">-- Select Department First --</option>
                            </select>
                            <div class="form-text">All active students in this cohort will be enrolled.</div>
                        </div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label" for="subject_id">Subject <span class="required">*</span></label>
                        <select id="subject_id" name="subject_id" class="form-select" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($allSubjects as $sub): ?>
                                <option value="<?= $sub['id'] ?>" data-dept="<?= $sub['department_id'] ?>">
                                    <?= escape($sub['subject_code']) ?> - <?= escape($sub['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">Enrollment Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 8px;">
                    <button type="submit" class="btn btn-primary">
                        Enroll Course
                    </button>
                    <a href="student-subjects.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- 3. Filter & Search Toolbar -->
<div class="filter-toolbar">
    <form method="GET" action="student-subjects.php">
        <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search student, roll, or subject..." 
            value="<?= escape($search) ?>" 
            style="max-width: 260px;"
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
            <a href="student-subjects.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>

        <div style="margin-left: auto; font-size: 12.5px; color: var(--text-muted);">
            Total: <strong><?= number_format($totalRows) ?></strong> enrollment<?= $totalRows === 1 ? '' : 's' ?>
        </div>
    </form>
</div>

<!-- 4. Data Table Card -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Roll</th>
                        <th>Student Name & ID</th>
                        <th>Class Cohort</th>
                        <th>Subject Title</th>
                        <th style="width: 130px;" class="text-center">Attendance Logs</th>
                        <th style="width: 100px;" class="text-center">Status</th>
                        <th style="width: 110px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon">🔗</div>
                                    <div class="empty-state-title">No Course Enrollments Found</div>
                                    <div class="empty-state-text">No assignments match your criteria, or no students have been enrolled yet.</div>
                                    <a href="student-subjects.php?action=add" class="btn btn-primary btn-sm">➕ Enroll Students</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $as): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-secondary" style="font-weight: 700; font-size: 12px;">
                                        <?= escape($as['roll_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= escape($as['first_name'] . ' ' . $as['last_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= escape($as['student_code']) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?= escape($as['department_code']) ?></span>
                                    <span style="font-size: 12px; margin-left: 4px; color: var(--text-muted);"><?= escape($as['section_name']) ?></span>
                                </td>
                                <td>
                                    <strong><?= escape($as['subject_code']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= escape($as['subject_name']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary"><?= number_format($as['attendance_count']) ?> sessions</span>
                                </td>
                                <td class="text-center">
                                    <a href="student-subjects.php?action=toggle_status&id=<?= $as['id'] ?>" title="Click to toggle status">
                                        <?= render_status_badge($as['status']) ?>
                                    </a>
                                </td>
                                <td class="text-right">
                                    <div class="table-actions">
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('student-subjects.php?action=delete&id=<?= $as['id'] ?>', 'enrollment for <?= escape($as['first_name'] . ' ' . $as['last_name']) ?> in <?= escape($as['subject_code']) ?>')"
                                            title="Remove Enrollment"
                                        >
                                            🗑️ Remove
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

    <!-- 5. Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> enrollments
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

<script>
function switchAssignMode(mode) {
    const input = document.getElementById('assign_mode_input');
    const indDiv = document.getElementById('individual_fields');
    const batDiv = document.getElementById('batch_fields');
    const btnInd = document.getElementById('btnModeIndividual');
    const btnBat = document.getElementById('btnModeBatch');

    input.value = mode;
    if (mode === 'batch') {
        indDiv.style.display = 'none';
        batDiv.style.display = 'block';
        btnBat.className = 'btn btn-sm btn-primary';
        btnInd.className = 'btn btn-sm btn-outline';
    } else {
        indDiv.style.display = 'block';
        batDiv.style.display = 'none';
        btnInd.className = 'btn btn-sm btn-primary';
        btnBat.className = 'btn btn-sm btn-outline';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const batchDept = document.getElementById('batch_dept_select');
    const batchSec  = document.getElementById('batch_sec_select');
    if (batchDept && batchSec) {
        batchDept.addEventListener('change', function () {
            const deptId = this.value;
            batchSec.innerHTML = '<option value="">Loading sections...</option>';
            if (!deptId) {
                batchSec.innerHTML = '<option value="">-- Select Department First --</option>';
                return;
            }
            fetch('../admin/api.php?action=get_sections&department_id=' + encodeURIComponent(deptId))
                .then(r => r.json())
                .then(data => {
                    let opts = '<option value="">-- Select Section --</option>';
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(s => {
                            opts += `<option value="${s.id}">${escapeHtml(s.section_name)}</option>`;
                        });
                    } else {
                        opts = '<option value="">No sections found</option>';
                    }
                    batchSec.innerHTML = opts;
                });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
