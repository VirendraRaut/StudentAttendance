<?php

/**
 * Department Management
 * Student Attendance Management System
 */

$pageTitle = 'Departments';
$currentPage = 'departments';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

// Handle Actions: Add, Edit, Delete
$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$editDept = null;

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
        redirect($deptId > 0 ? "admin/departments.php?action=edit&id={$deptId}" : "admin/departments.php");
    }

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    try {
        if ($deptId > 0) {
            // Check duplicate code or name for other records
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

            // Update
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
            // Check duplicate code or name
            $checkStmt = $pdo->prepare(
                "SELECT id FROM departments 
                 WHERE department_code = :code OR department_name = :name 
                 LIMIT 1"
            );
            $checkStmt->execute([':code' => $code, ':name' => $name]);
            if ($checkStmt->fetch()) {
                set_flash('danger', 'A department with this code or name already exists.');
                redirect("admin/departments.php");
            }

            // Insert
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
        set_flash('danger', 'Database error occurred while saving the department.');
        redirect('admin/departments.php');
    }
}

// Handle GET: Delete
if ($action === 'delete' && $editId > 0) {
    // Check CSRF from query string or verify
    try {
        // Safe foreign key reference checks before deleting
        $secCount = (int) $pdo->prepare("SELECT COUNT(*) FROM sections WHERE department_id = :id")->execute([':id' => $editId]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
        
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
            $msg = "Cannot delete this department because it contains active dependencies: ";
            $deps = [];
            if ($hasSections > 0) $deps[] = "{$hasSections} section(s)";
            if ($hasStudents > 0) $deps[] = "{$hasStudents} student(s)";
            if ($hasTeachers > 0) $deps[] = "{$hasTeachers} teacher(s)";
            if ($hasSubjects > 0) $deps[] = "{$hasSubjects} subject(s)";
            $msg .= implode(', ', $deps) . ". Please reassign or delete these records first.";
            set_flash('danger', $msg);
        } else {
            $delStmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
            $delStmt->execute([':id' => $editId]);
            set_flash('success', 'Department deleted successfully.');
        }

    } catch (PDOException $e) {
        error_log("Department delete error: " . $e->getMessage());
        set_flash('danger', 'Unable to delete department. Foreign key constraint prevents deletion.');
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

<div style="display: grid; grid-template-columns: <?= $editDept ? '1fr' : '360px 1fr' ?>; gap: 24px; align-items: start;">
    
    <!-- Add / Edit Department Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= $editDept ? 'Edit Department' : 'Add New Department' ?></h3>
                <div class="card-subtitle"><?= $editDept ? 'Modify department details' : 'Register a new academic department' ?></div>
            </div>
            <?php if ($editDept): ?>
                <a href="departments.php" class="btn btn-sm btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="departments.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="dept_id" value="<?= $editDept['id'] ?? 0 ?>">

                <div class="form-group">
                    <label class="form-label" for="department_code">Department Code <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="department_code" 
                        name="department_code" 
                        class="form-control" 
                        placeholder="e.g. CSE, ECE, MECH" 
                        maxlength="30" 
                        required 
                        value="<?= escape($editDept['department_code'] ?? '') ?>"
                    >
                    <div class="form-text">Short unique code for the department.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="department_name">Department Name <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="department_name" 
                        name="department_name" 
                        class="form-control" 
                        placeholder="e.g. Computer Science and Engineering" 
                        maxlength="150" 
                        required 
                        value="<?= escape($editDept['department_name'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        placeholder="Optional details about this department..."
                    ><?= escape($editDept['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="Active" <?= ($editDept['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($editDept['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <?= $editDept ? 'Update Department' : 'Save Department' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Departments List Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">All Departments (<?= number_format($totalRows) ?>)</h3>
                <div class="card-subtitle">Manage college academic faculties and branches</div>
            </div>
        </div>

        <!-- Filter / Search Toolbar -->
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); background: #fafbfd;">
            <form method="GET" action="departments.php" class="filter-actions" style="margin: 0; display: flex; gap: 12px; flex-wrap: wrap;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search by code or name..." 
                    value="<?= escape($search) ?>" 
                    style="max-width: 260px;"
                >
                <select name="status" class="form-select" style="max-width: 150px;">
                    <option value="">All Statuses</option>
                    <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="departments.php" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Sections</th>
                            <th>Students</th>
                            <th>Subjects</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">🏛️</div>
                                        <div class="empty-state-title">No Departments Found</div>
                                        <div class="empty-state-text">Use the form to create your first academic department.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><strong><?= escape($dept['department_code']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($dept['department_name']) ?></strong>
                                        <?php if (!empty($dept['description'])): ?>
                                            <div style="font-size: 11px; color: var(--text-muted); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?= escape($dept['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-secondary"><?= number_format($dept['section_count']) ?></span></td>
                                    <td><span class="badge badge-info"><?= number_format($dept['student_count']) ?></span></td>
                                    <td><span class="badge badge-secondary"><?= number_format($dept['subject_count']) ?></span></td>
                                    <td><?= render_status_badge($dept['status']) ?></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="departments.php?action=edit&id=<?= $dept['id'] ?>" class="btn btn-sm btn-outline">
                                            ✏️ Edit
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('departments.php?action=delete&id=<?= $dept['id'] ?>', 'department <?= escape($dept['department_name']) ?>')"
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
