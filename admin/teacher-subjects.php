<?php

/**
 * Teacher-Subject Assignments Management - Redesigned
 * Student Attendance Management System
 */

$pageTitle = 'Faculty Teaching Allocations';
$currentPage = 'teacher_subjects';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

<!-- Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title"><?= $pageTitle ?></h2>
        <p class="page-subtitle">Allocate teachers to subjects and sections</p>
    </div>
</div>

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$showForm = ($action === 'add');

// Filters
$search = trim($_GET['search'] ?? '');
$teacherFilter = (int) ($_GET['teacher_id'] ?? 0);
$deptFilter    = (int) ($_GET['department_id'] ?? 0);
$statusFilter  = trim($_GET['status'] ?? '');

$departments = get_active_departments($pdo, false);

// Handle POST: Assign Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token. Please try again.');
        redirect('admin/teacher-subjects.php');
    }

    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $sectionId = (int) ($_POST['section_id'] ?? 0);
    $status    = $_POST['status'] ?? 'Active';

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    if ($teacherId <= 0 || $subjectId <= 0 || $sectionId <= 0) {
        set_flash('danger', 'Teacher, Subject, and Section are all required.');
        redirect('admin/teacher-subjects.php?action=add');
    }

    $tStmt = $pdo->prepare("SELECT id, department_id, first_name, last_name FROM teachers WHERE id = :id LIMIT 1");
    $tStmt->execute([':id' => $teacherId]);
    $teacherData = $tStmt->fetch();

    $subStmt = $pdo->prepare("SELECT id, department_id, subject_name FROM subjects WHERE id = :id LIMIT 1");
    $subStmt->execute([':id' => $subjectId]);
    $subjectData = $subStmt->fetch();

    $secStmt = $pdo->prepare("SELECT id, department_id, section_name FROM sections WHERE id = :id LIMIT 1");
    $secStmt->execute([':id' => $sectionId]);
    $sectionData = $secStmt->fetch();

    if (!$teacherData || !$subjectData || !$sectionData) {
        set_flash('danger', 'Invalid selection. Please choose active teacher, subject, and section.');
        redirect('admin/teacher-subjects.php?action=add');
    }

    if ($subjectData['department_id'] != $sectionData['department_id']) {
        set_flash('danger', 'Incompatible Allocation: The Subject and Section must belong to the same department.');
        redirect('admin/teacher-subjects.php?action=add');
    }

    try {
        $chkStmt = $pdo->prepare(
            "SELECT id FROM teacher_subjects 
             WHERE teacher_id = :tid AND subject_id = :subid AND section_id = :secid 
             LIMIT 1"
        );
        $chkStmt->execute([':tid' => $teacherId, ':subid' => $subjectId, ':secid' => $sectionId]);
        if ($chkStmt->fetch()) {
            set_flash('warning', "This teacher is already assigned to this subject and section.");
            redirect('admin/teacher-subjects.php');
        }

        $insertStmt = $pdo->prepare(
            "INSERT INTO teacher_subjects (teacher_id, subject_id, section_id, status) 
             VALUES (:tid, :subid, :secid, :status)"
        );
        $insertStmt->execute([
            ':tid'    => $teacherId,
            ':subid'  => $subjectId,
            ':secid'  => $sectionId,
            ':status' => $status
        ]);
        set_flash('success', "Assigned {$teacherData['first_name']} {$teacherData['last_name']} to '{$subjectData['subject_name']}' ({$sectionData['section_name']}) successfully.");

    } catch (PDOException $e) {
        error_log("Teacher-subject assignment error: " . $e->getMessage());
        set_flash('danger', 'Database error occurred while assigning faculty.');
    }

    redirect('admin/teacher-subjects.php');
}

// Handle GET: Delete
if ($action === 'delete' && $editId > 0) {
    try {
        $delStmt = $pdo->prepare("DELETE FROM teacher_subjects WHERE id = :id");
        $delStmt->execute([':id' => $editId]);
        set_flash('success', 'Teaching allocation removed successfully.');
    } catch (PDOException $e) {
        error_log("Delete teacher-subject error: " . $e->getMessage());
        set_flash('danger', 'Unable to remove assignment.');
    }
    redirect('admin/teacher-subjects.php');
}

// Handle GET: Toggle Status
if ($action === 'toggle_status' && $editId > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE teacher_subjects SET status = IF(status = 'Active', 'Inactive', 'Active') WHERE id = :id");
        $stmt->execute([':id' => $editId]);
        set_flash('success', 'Assignment status updated.');
    } catch (PDOException $e) {
        error_log("Toggle status error: " . $e->getMessage());
        set_flash('danger', 'Unable to toggle status.');
    }
    redirect('admin/teacher-subjects.php');
}

// Fetch List with Search & Pagination
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(t.first_name LIKE :search OR t.last_name LIKE :search OR sub.subject_name LIKE :search OR sub.subject_code LIKE :search OR sec.section_name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($teacherFilter > 0) {
    $whereClauses[] = "ts.teacher_id = :tch_filter";
    $params[':tch_filter'] = $teacherFilter;
}

if ($deptFilter > 0) {
    $whereClauses[] = "sub.department_id = :dept_filter";
    $params[':dept_filter'] = $deptFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "ts.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';

// Count total
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) 
     FROM teacher_subjects ts 
     JOIN teachers t ON ts.teacher_id = t.id 
     JOIN subjects sub ON ts.subject_id = sub.id 
     JOIN sections sec ON ts.section_id = sec.id 
     JOIN departments d ON sub.department_id = d.id 
     {$whereSql}"
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch page rows
$listSql = "SELECT ts.*, t.teacher_code, t.first_name, t.last_name, 
            d.department_code, d.department_name, sec.section_name,
            sub.subject_code, sub.subject_name
            FROM teacher_subjects ts 
            JOIN teachers t ON ts.teacher_id = t.id 
            JOIN subjects sub ON ts.subject_id = sub.id 
            JOIN sections sec ON ts.section_id = sec.id 
            JOIN departments d ON sub.department_id = d.id 
            {$whereSql} 
            ORDER BY d.department_code ASC, sec.section_name ASC, sub.subject_code ASC, t.first_name ASC 
            LIMIT {$limit} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$assignments = $listStmt->fetchAll();

$teachersList = get_teachers($pdo, null, true);
$subjectsList = $pdo->query("SELECT s.id, s.subject_code, s.subject_name, s.department_id, d.department_code FROM subjects s JOIN departments d ON s.department_id = d.id WHERE s.status = 'Active' ORDER BY d.department_code ASC, s.subject_name ASC")->fetchAll();
$sectionsList = $pdo->query("SELECT sec.id, sec.section_name, sec.department_id, d.department_code FROM sections sec JOIN departments d ON sec.department_id = d.id WHERE sec.status = 'Active' ORDER BY d.department_code ASC, sec.section_name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title">Faculty Teaching Allocations</h2>
        <p class="page-subtitle">Assign instructors to course subjects and specific class sections</p>
    </div>
    <div class="page-header-actions">
        <?php if (!$showForm): ?>
            <a href="teacher-subjects.php?action=add" class="btn btn-primary">
                ➕ Allocate Instructor
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Add / Assign Form Card -->
<?php if ($showForm): ?>
    <div class="card" style="border-left: 4px solid var(--primary); margin-bottom: 24px;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Assign Instructor to Subject & Section</h3>
                <div class="card-subtitle">Select the faculty member, curriculum course, and target class section</div>
            </div>
            <a href="teacher-subjects.php" class="btn btn-sm btn-outline">✕ Cancel</a>
        </div>
        <div class="card-body">
            <form method="POST" action="teacher-subjects.php" autocomplete="off">
                <?= csrf_input() ?>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label" for="teacher_id">Instructor <span class="required">*</span></label>
                        <select id="teacher_id" name="teacher_id" class="form-select" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachersList as $tch): ?>
                                <option value="<?= $tch['id'] ?>" <?= $teacherFilter == $tch['id'] ? 'selected' : '' ?>>
                                    <?= escape($tch['first_name'] . ' ' . $tch['last_name']) ?> (<?= escape($tch['teacher_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="subject_id">Subject <span class="required">*</span></label>
                        <select id="subject_id" name="subject_id" class="form-select" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjectsList as $sub): ?>
                                <option value="<?= $sub['id'] ?>">
                                    [<?= escape($sub['department_code']) ?>] <?= escape($sub['subject_code']) ?> - <?= escape($sub['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="section_id">Class Section <span class="required">*</span></label>
                        <select id="section_id" name="section_id" class="form-select" required>
                            <option value="">-- Select Section --</option>
                            <?php foreach ($sectionsList as $sec): ?>
                                <option value="<?= $sec['id'] ?>">
                                    [<?= escape($sec['department_code']) ?>] <?= escape($sec['section_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Must be in the same department as the subject.</div>
                    </div>
                </div>

                <div class="form-group" style="max-width: 260px; margin-bottom: 20px;">
                    <label class="form-label" for="status">Assignment Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        Save Teaching Allocation
                    </button>
                    <a href="teacher-subjects.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- 3. Filter & Search Toolbar -->
<div class="filter-toolbar">
    <form method="GET" action="teacher-subjects.php">
        <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search instructor, subject, section..." 
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
            <a href="teacher-subjects.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>

        <div style="margin-left: auto; font-size: 12.5px; color: var(--text-muted);">
            Total: <strong><?= number_format($totalRows) ?></strong> allocation<?= $totalRows === 1 ? '' : 's' ?>
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
                        <th>Instructor</th>
                        <th>Course Subject</th>
                        <th>Target Section</th>
                        <th>Department</th>
                        <th style="width: 100px;" class="text-center">Status</th>
                        <th style="width: 110px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-state-icon">📋</div>
                                    <div class="empty-state-title">No Faculty Allocations Found</div>
                                    <div class="empty-state-text">No teaching assignments match your criteria, or none have been assigned yet.</div>
                                    <a href="teacher-subjects.php?action=add" class="btn btn-primary btn-sm">➕ Allocate Instructor</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $as): ?>
                            <tr>
                                <td>
                                    <strong><?= escape($as['first_name'] . ' ' . $as['last_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= escape($as['teacher_code']) ?></div>
                                </td>
                                <td>
                                    <strong><?= escape($as['subject_code']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= escape($as['subject_name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?= escape($as['section_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?= escape($as['department_code']) ?></span>
                                    <span style="font-size: 12px; margin-left: 4px; color: var(--text-muted);"><?= escape($as['department_name']) ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="teacher-subjects.php?action=toggle_status&id=<?= $as['id'] ?>" title="Click to toggle status">
                                        <?= render_status_badge($as['status']) ?>
                                    </a>
                                </td>
                                <td class="text-right">
                                    <div class="table-actions">
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('teacher-subjects.php?action=delete&id=<?= $as['id'] ?>', 'teaching allocation for <?= escape($as['first_name'] . ' ' . $as['last_name']) ?> in <?= escape($as['subject_code']) ?> (<?= escape($as['section_name']) ?>)')"
                                            title="Remove Allocation"
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
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> allocations
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
