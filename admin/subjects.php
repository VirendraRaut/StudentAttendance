<?php

/**
 * Subject Management
 * Student Attendance Management System
 */

$pageTitle = 'Subjects';
$currentPage = 'subjects';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$editSubject = null;

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

    // Validation
    if (empty($code) || empty($name) || $deptId <= 0) {
        set_flash('danger', 'Subject Code, Subject Name, and Department are required.');
        redirect($subjectId > 0 ? "admin/subjects.php?action=edit&id={$subjectId}" : "admin/subjects.php");
    }

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    try {
        if ($subjectId > 0) {
            // Check duplicate code
            $chkCode = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = :code AND id != :id LIMIT 1");
            $chkCode->execute([':code' => $code, ':id' => $subjectId]);
            if ($chkCode->fetch()) {
                set_flash('danger', 'Another subject with this Subject Code already exists.');
                redirect("admin/subjects.php?action=edit&id={$subjectId}");
            }

            // Check duplicate name in department
            $chkName = $pdo->prepare("SELECT id FROM subjects WHERE department_id = :dept_id AND subject_name = :name AND id != :id LIMIT 1");
            $chkName->execute([':dept_id' => $deptId, ':name' => $name, ':id' => $subjectId]);
            if ($chkName->fetch()) {
                set_flash('danger', "Subject '{$name}' already exists in the selected department.");
                redirect("admin/subjects.php?action=edit&id={$subjectId}");
            }

            // Update
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
            // Check duplicate code
            $chkCode = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = :code LIMIT 1");
            $chkCode->execute([':code' => $code]);
            if ($chkCode->fetch()) {
                set_flash('danger', 'A subject with this Subject Code already exists.');
                redirect("admin/subjects.php");
            }

            // Check duplicate name in department
            $chkName = $pdo->prepare("SELECT id FROM subjects WHERE department_id = :dept_id AND subject_name = :name LIMIT 1");
            $chkName->execute([':dept_id' => $deptId, ':name' => $name]);
            if ($chkName->fetch()) {
                set_flash('danger', "Subject '{$name}' already exists in the selected department.");
                redirect("admin/subjects.php");
            }

            // Insert
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
            $msg = "Cannot delete this subject because it has active dependencies: ";
            $deps = [];
            if ($hasStudents > 0) $deps[] = "{$hasStudents} student enrollment(s)";
            if ($hasTeachers > 0) $deps[] = "{$hasTeachers} teacher assignment(s)";
            if ($hasAttendance > 0) $deps[] = "{$hasAttendance} recorded attendance session(s)";
            $msg .= implode(', ', $deps) . ". Please remove dependent assignments first.";
            set_flash('danger', $msg);
        } else {
            $delStmt = $pdo->prepare("DELETE FROM subjects WHERE id = :id");
            $delStmt->execute([':id' => $editId]);
            set_flash('success', 'Subject deleted successfully.');
        }

    } catch (PDOException $e) {
        error_log("Subject delete error: " . $e->getMessage());
        set_flash('danger', 'Unable to delete subject. Foreign key relationships prevent deletion.');
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

<div style="display: grid; grid-template-columns: <?= $editSubject ? '1fr' : '360px 1fr' ?>; gap: 24px; align-items: start;">
    
    <!-- Add / Edit Subject Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= $editSubject ? 'Edit Subject' : 'Add New Subject' ?></h3>
                <div class="card-subtitle"><?= $editSubject ? 'Update subject details' : 'Create a curriculum subject' ?></div>
            </div>
            <?php if ($editSubject): ?>
                <a href="subjects.php" class="btn btn-sm btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="subjects.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="subject_id" value="<?= $editSubject['id'] ?? 0 ?>">

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

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        placeholder="Optional subject description or syllabus overview..."
                    ><?= escape($editSubject['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="Active" <?= ($editSubject['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($editSubject['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <?= $editSubject ? 'Update Subject' : 'Save Subject' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Subjects List Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">All Subjects (<?= number_format($totalRows) ?>)</h3>
                <div class="card-subtitle">Active subjects catalog by department</div>
            </div>
        </div>

        <!-- Filter / Search Toolbar -->
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); background: #fafbfd;">
            <form method="GET" action="subjects.php" class="filter-actions" style="margin: 0; display: flex; gap: 12px; flex-wrap: wrap;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search code or subject..." 
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
                    <a href="subjects.php" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Name</th>
                            <th>Department</th>
                            <th>Enrolled Students</th>
                            <th>Attendance Sessions</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">📚</div>
                                        <div class="empty-state-title">No Subjects Found</div>
                                        <div class="empty-state-text">Create curriculum subjects to assign students and teachers.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $sub): ?>
                                <tr>
                                    <td><strong><?= escape($sub['subject_code']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($sub['subject_name']) ?></strong>
                                        <?php if (!empty($sub['description'])): ?>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?= escape($sub['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary"><?= escape($sub['department_code']) ?></span>
                                    </td>
                                    <td><span class="badge badge-info"><?= number_format($sub['enrolled_students_count']) ?> students</span></td>
                                    <td><span class="badge badge-secondary"><?= number_format($sub['attendance_records_count']) ?> sessions</span></td>
                                    <td><?= render_status_badge($sub['status']) ?></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="subjects.php?action=edit&id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline">
                                            ✏️ Edit
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('subjects.php?action=delete&id=<?= $sub['id'] ?>', 'subject <?= escape($sub['subject_name']) ?>')"
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
