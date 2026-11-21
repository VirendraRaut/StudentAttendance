<?php

/**
 * Subject Management - Redesigned
 * Student Attendance Management System
 */

$pageTitle = 'Subjects';
$currentPage = 'subjects';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

<!-- Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title"><?= $pageTitle ?></h2>
        <p class="page-subtitle">Manage subjects and departmental links</p>
    </div>
</div>

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$editSubject = null;
$showForm = ($action === 'add' || $action === 'edit');

// Search & Filter
$search = trim($_GET['search'] ?? '');
$deptFilter = (int) ($_GET['department_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$departments = get_active_departments($pdo, false);

// Handle POST: Add or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token. Please try again.');
        redirect('admin/subjects.php');
    }

    $subjectId   = (int) ($_POST['subject_id'] ?? 0);
    $code        = strtoupper(trim($_POST['subject_code'] ?? ''));
    $name        = trim($_POST['subject_name'] ?? '');
    $deptId      = (int) ($_POST['department_id'] ?? 0);
    $desc        = trim($_POST['description'] ?? '');
    $status      = $_POST['status'] ?? 'Active';

    if (empty($code) || empty($name) || $deptId <= 0) {
        set_flash('danger', 'Subject Code, Subject Name, and Department are required.');
        redirect($subjectId > 0 ? "admin/subjects.php?action=edit&id={$subjectId}" : "admin/subjects.php?action=add");
    }

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    try {
        if ($subjectId > 0) {
            $chkCode = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = :code AND id != :id LIMIT 1");
            $chkCode->execute([':code' => $code, ':id' => $subjectId]);
            if ($chkCode->fetch()) {
                set_flash('danger', 'Another subject with this Subject Code already exists.');
                redirect("admin/subjects.php?action=edit&id={$subjectId}");
            }

            $chkName = $pdo->prepare("SELECT id FROM subjects WHERE department_id = :dept_id AND subject_name = :name AND id != :id LIMIT 1");
            $chkName->execute([':dept_id' => $deptId, ':name' => $name, ':id' => $subjectId]);
            if ($chkName->fetch()) {
                set_flash('danger', "Subject '{$name}' already exists in the selected department.");
                redirect("admin/subjects.php?action=edit&id={$subjectId}");
            }

            $updateStmt = $pdo->prepare(
                "UPDATE subjects 
                 SET subject_code = :code, subject_name = :name, department_id = :dept_id, 
                     description = :desc, status = :status 
                 WHERE id = :id"
            );
            $updateStmt->execute([
                ':code'    => $code,
                ':name'    => $name,
                ':dept_id' => $deptId,
                ':desc'    => $desc ?: null,
                ':status'  => $status,
                ':id'      => $subjectId
            ]);
            set_flash('success', "Subject '{$name}' updated successfully.");

        } else {
            $chkCode = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = :code LIMIT 1");
            $chkCode->execute([':code' => $code]);
            if ($chkCode->fetch()) {
                set_flash('danger', 'A subject with this Subject Code already exists.');
                redirect("admin/subjects.php?action=add");
            }

            $chkName = $pdo->prepare("SELECT id FROM subjects WHERE department_id = :dept_id AND subject_name = :name LIMIT 1");
            $chkName->execute([':dept_id' => $deptId, ':name' => $name]);
            if ($chkName->fetch()) {
                set_flash('danger', "Subject '{$name}' already exists in the selected department.");
                redirect("admin/subjects.php?action=add");
            }

            $insertStmt = $pdo->prepare(
                "INSERT INTO subjects (subject_code, subject_name, department_id, description, status) 
                 VALUES (:code, :name, :dept_id, :desc, :status)"
            );
            $insertStmt->execute([
                ':code'    => $code,
                ':name'    => $name,
                ':dept_id' => $deptId,
                ':desc'    => $desc ?: null,
                ':status'  => $status
            ]);
            set_flash('success', "Subject '{$name}' created successfully.");
        }

        redirect('admin/subjects.php');

    } catch (PDOException $e) {
        error_log("Subject save error: " . $e->getMessage());
        set_flash('danger', 'Database error occurred while saving the subject.');
        redirect('admin/subjects.php');
    }
}

// Handle GET: Delete
if ($action === 'delete' && $editId > 0) {
    try {
        $chkStuSub = $pdo->prepare("SELECT COUNT(*) FROM student_subjects WHERE subject_id = :id");
        $chkStuSub->execute([':id' => $editId]);
        $hasStudents = (int) $chkStuSub->fetchColumn();

        $chkTeaSub = $pdo->prepare("SELECT COUNT(*) FROM teacher_subjects WHERE subject_id = :id");
        $chkTeaSub->execute([':id' => $editId]);
        $hasTeachers = (int) $chkTeaSub->fetchColumn();

        $chkAtt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE subject_id = :id");
        $chkAtt->execute([':id' => $editId]);
        $hasAttendance = (int) $chkAtt->fetchColumn();

        if ($hasStudents > 0 || $hasTeachers > 0 || $hasAttendance > 0) {
            $deps = [];
            if ($hasStudents > 0) $deps[] = "{$hasStudents} student enrollment(s)";
            if ($hasTeachers > 0) $deps[] = "{$hasTeachers} teacher assignment(s)";
            if ($hasAttendance > 0) $deps[] = "{$hasAttendance} attendance session(s)";
            set_flash('danger', "Cannot delete subject: Linked to " . implode(', ', $deps) . ". Remove dependent assignments first.");
        } else {
            $delStmt = $pdo->prepare("DELETE FROM subjects WHERE id = :id");
            $delStmt->execute([':id' => $editId]);
            set_flash('success', 'Subject deleted successfully.');
        }

    } catch (PDOException $e) {
        error_log("Subject delete error: " . $e->getMessage());
        set_flash('danger', 'Cannot delete subject due to database relationships.');
    }
    redirect('admin/subjects.php');
}

// Fetch record if editing
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editSubject = $stmt->fetch();
    if (!$editSubject) {
        set_flash('danger', 'Subject not found.');
        redirect('admin/subjects.php');
    }
}

// Fetch List with Search & Pagination
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(sub.subject_code LIKE :search OR sub.subject_name LIKE :search OR d.department_name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($deptFilter > 0) {
    $whereClauses[] = "sub.department_id = :dept_filter";
    $params[':dept_filter'] = $deptFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "sub.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM subjects sub JOIN departments d ON sub.department_id = d.id {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch page rows
$listSql = "SELECT sub.*, d.department_code, d.department_name,
            (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = sub.id) AS enrolled_students_count,
            (SELECT COUNT(*) FROM attendance a WHERE a.subject_id = sub.id) AS attendance_records_count
            FROM subjects sub 
            JOIN departments d ON sub.department_id = d.id 
            {$whereSql} 
            ORDER BY d.department_name ASC, sub.subject_code ASC 
            LIMIT {$limit} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$subjects = $listStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title">Curriculum Subjects</h2>
        <p class="page-subtitle">Academic course catalog, subject codes, and departmental assignments</p>
    </div>
    <div class="page-header-actions">
        <?php if (!$showForm): ?>
            <a href="subjects.php?action=add" class="btn btn-primary">
                ➕ Add Subject
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Add / Edit Form Card -->
<?php if ($showForm): ?>
    <div class="card" style="border-left: 4px solid var(--primary); margin-bottom: 24px;">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= $editSubject ? 'Edit Subject Details' : 'Create New Curriculum Subject' ?></h3>
                <div class="card-subtitle"><?= $editSubject ? 'Update subject title, code, or department' : 'Enter syllabus course details and map to an academic department' ?></div>
            </div>
            <a href="subjects.php" class="btn btn-sm btn-outline">✕ Cancel</a>
        </div>
        <div class="card-body">
            <form method="POST" action="subjects.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="subject_id" value="<?= $editSubject['id'] ?? 0 ?>">

                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label" for="subject_code">Subject Code <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="subject_code" 
                            name="subject_code" 
                            class="form-control" 
                            placeholder="e.g. CS101, MATH201" 
                            maxlength="50" 
                            required 
                            value="<?= escape($editSubject['subject_code'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="subject_name">Subject Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="subject_name" 
                            name="subject_name" 
                            class="form-control" 
                            placeholder="e.g. Data Structures and Algorithms" 
                            maxlength="150" 
                            required 
                            value="<?= escape($editSubject['subject_name'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="department_id">Department <span class="required">*</span></label>
                        <select id="department_id" name="department_id" class="form-select" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= ($editSubject['department_id'] ?? $deptFilter) == $dept['id'] ? 'selected' : '' ?>>
                                    <?= escape($dept['department_name']) ?> (<?= escape($dept['department_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Syllabus / Course Description</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        placeholder="Optional syllabus summary, course modules, or prerequisites..."
                    ><?= escape($editSubject['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group" style="max-width: 260px; margin-bottom: 20px;">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="Active" <?= ($editSubject['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($editSubject['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editSubject ? 'Save Changes' : 'Create Subject' ?>
                    </button>
                    <a href="subjects.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- 3. Filter & Search Toolbar -->
<div class="filter-toolbar">
    <form method="GET" action="subjects.php">
        <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search code or subject name..." 
            value="<?= escape($search) ?>" 
            style="max-width: 260px;"
        >
        <select name="department_id" class="form-select" style="max-width: 220px;">
            <option value="">All Departments</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?= $dept['id'] ?>" <?= $deptFilter == $dept['id'] ? 'selected' : '' ?>>
                    <?= escape($dept['department_code']) ?> - <?= escape($dept['department_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select" style="max-width: 150px;">
            <option value="">All Statuses</option>
            <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active Only</option>
            <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive Only</option>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (!empty($search) || $deptFilter > 0 || !empty($statusFilter)): ?>
            <a href="subjects.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>

        <div style="margin-left: auto; font-size: 12.5px; color: var(--text-muted);">
            Total: <strong><?= number_format($totalRows) ?></strong> subject<?= $totalRows === 1 ? '' : 's' ?>
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
                        <th style="width: 110px;">Code</th>
                        <th>Subject Title</th>
                        <th>Department</th>
                        <th style="width: 130px;" class="text-center">Enrolled Students</th>
                        <th style="width: 130px;" class="text-center">Attendance Sessions</th>
                        <th style="width: 100px;" class="text-center">Status</th>
                        <th style="width: 140px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon">📚</div>
                                    <div class="empty-state-title">No Subjects Found</div>
                                    <div class="empty-state-text">No curriculum courses match your filter criteria, or none have been added yet.</div>
                                    <a href="subjects.php?action=add" class="btn btn-primary btn-sm">➕ Create Subject</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-secondary" style="font-weight: 700; font-size: 12px;">
                                        <?= escape($sub['subject_code']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= escape($sub['subject_name']) ?></strong>
                                    <?php if (!empty($sub['description'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted); max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= escape($sub['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?= escape($sub['department_code']) ?></span>
                                    <span style="font-size: 12px; margin-left: 4px; color: var(--text-muted);"><?= escape($sub['department_name']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?= number_format($sub['enrolled_students_count']) ?> students</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary"><?= number_format($sub['attendance_records_count']) ?></span>
                                </td>
                                <td class="text-center"><?= render_status_badge($sub['status']) ?></td>
                                <td class="text-right">
                                    <div class="table-actions">
                                        <a href="subjects.php?action=edit&id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline" title="Edit Subject">
                                            ✏️ Edit
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('subjects.php?action=delete&id=<?= $sub['id'] ?>', 'subject <?= escape($sub['subject_name']) ?>')"
                                            title="Delete Subject"
                                        >
                                            🗑️ Delete
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
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> subjects
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
