<?php

/**
 * Department Management - Redesigned
 * Student Attendance Management System
 */

$pageTitle = 'Departments';
$currentPage = 'departments';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

<!-- Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title"><?= $pageTitle ?></h2>
        <p class="page-subtitle">Manage departments and related entities</p>
    </div>
</div>

// Handle Actions: Add, Edit, Delete
$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$editDept = null;
$showForm = ($action === 'add' || $action === 'edit');

// Search & Filter
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Handle POST: Add or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token. Please try again.');
        redirect('admin/departments.php');
    }

    $deptId = (int) ($_POST['dept_id'] ?? 0);
    $code   = strtoupper(trim($_POST['department_code'] ?? ''));
    $name   = trim($_POST['department_name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    // Validation
    if (empty($code) || empty($name)) {
        set_flash('danger', 'Department Code and Department Name are required.');
        redirect($deptId > 0 ? "admin/departments.php?action=edit&id={$deptId}" : "admin/departments.php?action=add");
    }

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    try {
        if ($deptId > 0) {
            $checkStmt = $pdo->prepare(
                "SELECT id FROM departments 
                 WHERE (department_code = :code OR department_name = :name) AND id != :id 
                 LIMIT 1"
            );
            $checkStmt->execute([':code' => $code, ':name' => $name, ':id' => $deptId]);
            if ($checkStmt->fetch()) {
                set_flash('danger', 'Another department with this code or name already exists.');
                redirect("admin/departments.php?action=edit&id={$deptId}");
            }

            $updateStmt = $pdo->prepare(
                "UPDATE departments 
                 SET department_code = :code, department_name = :name, description = :desc, status = :status 
                 WHERE id = :id"
            );
            $updateStmt->execute([
                ':code'   => $code,
                ':name'   => $name,
                ':desc'   => $desc ?: null,
                ':status' => $status,
                ':id'     => $deptId
            ]);
            set_flash('success', "Department '{$name}' updated successfully.");

        } else {
            $checkStmt = $pdo->prepare(
                "SELECT id FROM departments 
                 WHERE department_code = :code OR department_name = :name 
                 LIMIT 1"
            );
            $checkStmt->execute([':code' => $code, ':name' => $name]);
            if ($checkStmt->fetch()) {
                set_flash('danger', 'A department with this code or name already exists.');
                redirect("admin/departments.php?action=add");
            }

            $insertStmt = $pdo->prepare(
                "INSERT INTO departments (department_code, department_name, description, status) 
                 VALUES (:code, :name, :desc, :status)"
            );
            $insertStmt->execute([
                ':code'   => $code,
                ':name'   => $name,
                ':desc'   => $desc ?: null,
                ':status' => $status
            ]);
            set_flash('success', "Department '{$name}' created successfully.");
        }

        redirect('admin/departments.php');

    } catch (PDOException $e) {
        error_log("Department save error: " . $e->getMessage());
        set_flash('danger', 'Database error occurred while saving department.');
        redirect('admin/departments.php');
    }
}

// Handle GET: Delete
if ($action === 'delete' && $editId > 0) {
    try {
        $chkSec = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE department_id = :id");
        $chkSec->execute([':id' => $editId]);
        $hasSections = (int) $chkSec->fetchColumn();

        $chkStu = $pdo->prepare("SELECT COUNT(*) FROM students WHERE department_id = :id");
        $chkStu->execute([':id' => $editId]);
        $hasStudents = (int) $chkStu->fetchColumn();

        $chkTea = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE department_id = :id");
        $chkTea->execute([':id' => $editId]);
        $hasTeachers = (int) $chkTea->fetchColumn();

        $chkSub = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE department_id = :id");
        $chkSub->execute([':id' => $editId]);
        $hasSubjects = (int) $chkSub->fetchColumn();

        if ($hasSections > 0 || $hasStudents > 0 || $hasTeachers > 0 || $hasSubjects > 0) {
            $deps = [];
            if ($hasSections > 0) $deps[] = "{$hasSections} section(s)";
            if ($hasStudents > 0) $deps[] = "{$hasStudents} student(s)";
            if ($hasTeachers > 0) $deps[] = "{$hasTeachers} teacher(s)";
            if ($hasSubjects > 0) $deps[] = "{$hasSubjects} subject(s)";
            set_flash('danger', "Cannot delete department: Linked to " . implode(', ', $deps) . ". Reassign records first.");
        } else {
            $delStmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
            $delStmt->execute([':id' => $editId]);
            set_flash('success', 'Department deleted successfully.');
        }

    } catch (PDOException $e) {
        error_log("Department delete error: " . $e->getMessage());
        set_flash('danger', 'Cannot delete department due to database relationships.');
    }
    redirect('admin/departments.php');
}

// Fetch record if editing
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editDept = $stmt->fetch();
    if (!$editDept) {
        set_flash('danger', 'Department not found.');
        redirect('admin/departments.php');
    }
}

// Fetch List with Search & Pagination
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(d.department_code LIKE :search OR d.department_name LIKE :search OR d.description LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if (!empty($statusFilter)) {
    $whereClauses[] = "d.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM departments d {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch page rows
$listSql = "SELECT d.*, 
            (SELECT COUNT(*) FROM sections s WHERE s.department_id = d.id) AS section_count,
            (SELECT COUNT(*) FROM students st WHERE st.department_id = d.id) AS student_count,
            (SELECT COUNT(*) FROM subjects sub WHERE sub.department_id = d.id) AS subject_count
            FROM departments d 
            {$whereSql} 
            ORDER BY d.department_name ASC 
            LIMIT {$limit} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$departments = $listStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Page Header Block -->
<div class="page-header-block">
    <div>
        <h2 class="page-title">Departments</h2>
        <p class="page-subtitle">Manage academic faculties, departmental codes, and curriculum branches</p>
    </div>
    <div class="page-header-actions">
        <?php if (!$showForm): ?>
            <a href="departments.php?action=add" class="btn btn-primary">
                ➕ Add Department
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Add / Edit Form Card (Displayed when adding or editing) -->
<?php if ($showForm): ?>
    <div class="card" style="border-left: 4px solid var(--primary); margin-bottom: 24px;">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= $editDept ? 'Edit Department' : 'Create New Department' ?></h3>
                <div class="card-subtitle"><?= $editDept ? 'Update department code, title, and status' : 'Enter the code and name for the new academic department' ?></div>
            </div>
            <a href="departments.php" class="btn btn-sm btn-outline">✕ Cancel</a>
        </div>
        <div class="card-body">
            <form method="POST" action="departments.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="dept_id" value="<?= $editDept['id'] ?? 0 ?>">

                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label" for="department_code">Department Code <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="department_code" 
                            name="department_code" 
                            class="form-control" 
                            placeholder="e.g. CSE, IT, MECH" 
                            maxlength="30" 
                            required 
                            value="<?= escape($editDept['department_code'] ?? '') ?>"
                        >
                        <div class="form-text">Unique short abbreviation.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="department_name">Department Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="department_name" 
                            name="department_name" 
                            class="form-control" 
                            placeholder="e.g. Computer Science & Engineering" 
                            maxlength="150" 
                            required 
                            value="<?= escape($editDept['department_name'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="Active" <?= ($editDept['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($editDept['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="description">Description / Notes</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        placeholder="Optional details or syllabus focus for this department..."
                    ><?= escape($editDept['description'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editDept ? 'Save Changes' : 'Create Department' ?>
                    </button>
                    <a href="departments.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- 3. Filter & Search Toolbar -->
<div class="filter-toolbar">
    <form method="GET" action="departments.php">
        <input 
            type="text" 
            name="search" 
            class="form-control" 
            placeholder="Search by code or name..." 
            value="<?= escape($search) ?>" 
            style="max-width: 280px;"
        >
        <select name="status" class="form-select" style="max-width: 160px;">
            <option value="">All Statuses</option>
            <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active Only</option>
            <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive Only</option>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (!empty($search) || !empty($statusFilter)): ?>
            <a href="departments.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>

        <div style="margin-left: auto; font-size: 12.5px; color: var(--text-muted);">
            Total: <strong><?= number_format($totalRows) ?></strong> department<?= $totalRows === 1 ? '' : 's' ?>
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
                        <th style="width: 100px;">Code</th>
                        <th>Department Name</th>
                        <th style="width: 110px;" class="text-center">Sections</th>
                        <th style="width: 110px;" class="text-center">Students</th>
                        <th style="width: 110px;" class="text-center">Subjects</th>
                        <th style="width: 100px;" class="text-center">Status</th>
                        <th style="width: 140px;" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-state-icon">🏛️</div>
                                    <div class="empty-state-title">No Departments Found</div>
                                    <div class="empty-state-text">No departments match your filter criteria, or none have been added yet.</div>
                                    <a href="departments.php?action=add" class="btn btn-primary btn-sm">➕ Create Department</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-secondary" style="font-weight: 700; font-size: 12px;">
                                        <?= escape($dept['department_code']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= escape($dept['department_name']) ?></strong>
                                    <?php if (!empty($dept['description'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted); max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= escape($dept['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary"><?= number_format($dept['section_count']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?= number_format($dept['student_count']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary"><?= number_format($dept['subject_count']) ?></span>
                                </td>
                                <td class="text-center"><?= render_status_badge($dept['status']) ?></td>
                                <td class="text-right">
                                    <div class="table-actions">
                                        <a href="departments.php?action=edit&id=<?= $dept['id'] ?>" class="btn btn-sm btn-outline" title="Edit Department">
                                            ✏️ Edit
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('departments.php?action=delete&id=<?= $dept['id'] ?>', 'department <?= escape($dept['department_name']) ?>')"
                                            title="Delete Department"
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
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> departments
            </div>
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?p=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">Prev</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?p=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">Next</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
