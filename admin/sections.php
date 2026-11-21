<?php

/**
 * Section Management
 * Student Attendance Management System
 */

$pageTitle = 'Sections';
$currentPage = 'sections';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$editSection = null;

// Search & Filter
$search = trim($_GET['search'] ?? '');
$deptFilter = (int) ($_GET['department_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

// Active departments for dropdown
$departments = get_active_departments($pdo, false);

// Handle POST: Add or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token. Please try again.');
        redirect('admin/sections.php');
    }

    $secId   = (int) ($_POST['section_id'] ?? 0);
    $deptId  = (int) ($_POST['department_id'] ?? 0);
    $secName = trim($_POST['section_name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $status  = $_POST['status'] ?? 'Active';

    // Validation
    if ($deptId <= 0 || empty($secName)) {
        set_flash('danger', 'Please select a department and enter a section name.');
        redirect($secId > 0 ? "admin/sections.php?action=edit&id={$secId}" : "admin/sections.php");
    }

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    try {
        if ($secId > 0) {
            // Check duplicate section in same department
            $checkStmt = $pdo->prepare(
                "SELECT id FROM sections 
                 WHERE department_id = :dept_id AND section_name = :name AND id != :id 
                 LIMIT 1"
            );
            $checkStmt->execute([':dept_id' => $deptId, ':name' => $secName, ':id' => $secId]);
            if ($checkStmt->fetch()) {
                set_flash('danger', "Section '{$secName}' already exists in the selected department.");
                redirect("admin/sections.php?action=edit&id={$secId}");
            }

            // Update
            $updateStmt = $pdo->prepare(
                "UPDATE sections 
                 SET department_id = :dept_id, section_name = :name, description = :desc, status = :status 
                 WHERE id = :id"
            );
            $updateStmt->execute([
                ':dept_id' => $deptId,
                ':name'    => $secName,
                ':desc'    => $desc ?: null,
                ':status'  => $status,
                ':id'      => $secId
            ]);
            set_flash('success', "Section '{$secName}' updated successfully.");

        } else {
            // Check duplicate
            $checkStmt = $pdo->prepare(
                "SELECT id FROM sections 
                 WHERE department_id = :dept_id AND section_name = :name 
                 LIMIT 1"
            );
            $checkStmt->execute([':dept_id' => $deptId, ':name' => $secName]);
            if ($checkStmt->fetch()) {
                set_flash('danger', "Section '{$secName}' already exists in this department.");
                redirect("admin/sections.php");
            }

            // Insert
            $insertStmt = $pdo->prepare(
                "INSERT INTO sections (department_id, section_name, description, status) 
                 VALUES (:dept_id, :name, :desc, :status)"
            );
            $insertStmt->execute([
                ':dept_id' => $deptId,
                ':name'    => $secName,
                ':desc'    => $desc ?: null,
                ':status'  => $status
            ]);
            set_flash('success', "Section '{$secName}' added successfully.");
        }

        redirect('admin/sections.php');

    } catch (PDOException $e) {
        error_log("Section save error: " . $e->getMessage());
        set_flash('danger', 'Database error occurred while saving the section.');
        redirect('admin/sections.php');
    }
}

// Handle GET: Delete
if ($action === 'delete' && $editId > 0) {
    try {
        // Safe check for students
        $chkStu = $pdo->prepare("SELECT COUNT(*) FROM students WHERE section_id = :id");
        $chkStu->execute([':id' => $editId]);
        $hasStudents = (int) $chkStu->fetchColumn();

        // Safe check for teacher_subjects
        $chkTea = $pdo->prepare("SELECT COUNT(*) FROM teacher_subjects WHERE section_id = :id");
        $chkTea->execute([':id' => $editId]);
        $hasTeacherAssignments = (int) $chkTea->fetchColumn();

        if ($hasStudents > 0 || $hasTeacherAssignments > 0) {
            $msg = "Cannot delete this section because it is currently assigned to: ";
            $deps = [];
            if ($hasStudents > 0) $deps[] = "{$hasStudents} student(s)";
            if ($hasTeacherAssignments > 0) $deps[] = "{$hasTeacherAssignments} teacher-subject assignment(s)";
            $msg .= implode(' and ', $deps) . ". Please reassign these records first.";
            set_flash('danger', $msg);
        } else {
            $delStmt = $pdo->prepare("DELETE FROM sections WHERE id = :id");
            $delStmt->execute([':id' => $editId]);
            set_flash('success', 'Section deleted successfully.');
        }

    } catch (PDOException $e) {
        error_log("Section delete error: " . $e->getMessage());
        set_flash('danger', 'Unable to delete section due to existing database relationships.');
    }
    redirect('admin/sections.php');
}

// Fetch record if editing
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editSection = $stmt->fetch();
    if (!$editSection) {
        set_flash('danger', 'Section not found.');
        redirect('admin/sections.php');
    }
}

// Fetch List with Search & Pagination
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(s.section_name LIKE :search OR d.department_name LIKE :search OR d.department_code LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($deptFilter > 0) {
    $whereClauses[] = "s.department_id = :dept_filter";
    $params[':dept_filter'] = $deptFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "s.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM sections s JOIN departments d ON s.department_id = d.id {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch page rows
$listSql = "SELECT s.*, d.department_code, d.department_name,
            (SELECT COUNT(*) FROM students st WHERE st.section_id = s.id) AS student_count
            FROM sections s 
            JOIN departments d ON s.department_id = d.id 
            {$whereSql} 
            ORDER BY d.department_name ASC, s.section_name ASC 
            LIMIT {$limit} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$sections = $listStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: <?= $editSection ? '1fr' : '360px 1fr' ?>; gap: 24px; align-items: start;">
    
    <!-- Add / Edit Section Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= $editSection ? 'Edit Section' : 'Add New Section' ?></h3>
                <div class="card-subtitle"><?= $editSection ? 'Modify section details' : 'Register a new class section' ?></div>
            </div>
            <?php if ($editSection): ?>
                <a href="sections.php" class="btn btn-sm btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="sections.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="section_id" value="<?= $editSection['id'] ?? 0 ?>">

                <div class="form-group">
                    <label class="form-label" for="department_id">Department <span class="required">*</span></label>
                    <select id="department_id" name="department_id" class="form-select" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($editSection['department_id'] ?? $deptFilter) == $dept['id'] ? 'selected' : '' ?>>
                                <?= escape($dept['department_name']) ?> (<?= escape($dept['department_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="section_name">Section Name <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="section_name" 
                        name="section_name" 
                        class="form-control" 
                        placeholder="e.g. Section A, Year 1 - CS-A" 
                        maxlength="100" 
                        required 
                        value="<?= escape($editSection['section_name'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        placeholder="Optional section notes..."
                    ><?= escape($editSection['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="Active" <?= ($editSection['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($editSection['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <?= $editSection ? 'Update Section' : 'Save Section' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Sections List Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">All Sections (<?= number_format($totalRows) ?>)</h3>
                <div class="card-subtitle">Organized by departmental divisions</div>
            </div>
        </div>

        <!-- Filter / Search Toolbar -->
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); background: #fafbfd;">
            <form method="GET" action="sections.php" class="filter-actions" style="margin: 0; display: flex; gap: 12px; flex-wrap: wrap;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search section or department..." 
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
                    <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if (!empty($search) || $deptFilter > 0 || !empty($statusFilter)): ?>
                    <a href="sections.php" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Section Name</th>
                            <th>Department</th>
                            <th>Enrolled Students</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sections)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📑</div>
                                        <div class="empty-state-title">No Sections Found</div>
                                        <div class="empty-state-text">Create sections to start assigning students and classes.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sections as $sec): ?>
                                <tr>
                                    <td>
                                        <strong><?= escape($sec['section_name']) ?></strong>
                                        <?php if (!empty($sec['description'])): ?>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?= escape($sec['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary"><?= escape($sec['department_code']) ?></span>
                                        <span style="font-size: 12.5px; margin-left: 4px;"><?= escape($sec['department_name']) ?></span>
                                    </td>
                                    <td><span class="badge badge-info"><?= number_format($sec['student_count']) ?> students</span></td>
                                    <td><?= render_status_badge($sec['status']) ?></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="sections.php?action=edit&id=<?= $sec['id'] ?>" class="btn btn-sm btn-outline">
                                            ✏️ Edit
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('sections.php?action=delete&id=<?= $sec['id'] ?>', 'section <?= escape($sec['section_name']) ?>')"
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
                    Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> sections
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
