<?php

/**
 * Teacher Management
 * Student Attendance Management System
 */

$pageTitle = 'Teachers';
$currentPage = 'teachers';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$action = $_GET['action'] ?? 'list';
$editId = (int) ($_GET['id'] ?? 0);
$editTeacher = null;

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
        redirect('admin/teachers.php');
    }

    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    $code      = strtoupper(trim($_POST['teacher_code'] ?? ''));
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $deptId    = (int) ($_POST['department_id'] ?? 0);
    $status    = $_POST['status'] ?? 'Active';

    // Validation
    if (empty($code) || empty($firstName) || empty($lastName) || $deptId <= 0) {
        set_flash('danger', 'Teacher Code, First Name, Last Name, and Department are required.');
        redirect($teacherId > 0 ? "admin/teachers.php?action=edit&id={$teacherId}" : "admin/teachers.php");
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Please enter a valid email address.');
        redirect($teacherId > 0 ? "admin/teachers.php?action=edit&id={$teacherId}" : "admin/teachers.php");
    }

    if (!in_array($status, ['Active', 'Inactive'])) {
        $status = 'Active';
    }

    try {
        if ($teacherId > 0) {
            // Check duplicate teacher code or email for other records
            $checkSql = "SELECT id FROM teachers WHERE (teacher_code = :code";
            $checkParams = [':code' => $code, ':id' => $teacherId];
            if (!empty($email)) {
                $checkSql .= " OR email = :email";
                $checkParams[':email'] = $email;
            }
            $checkSql .= ") AND id != :id LIMIT 1";

            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute($checkParams);
            if ($checkStmt->fetch()) {
                set_flash('danger', 'Another teacher with this Code or Email already exists.');
                redirect("admin/teachers.php?action=edit&id={$teacherId}");
            }

            // Update
            $updateStmt = $pdo->prepare(
                "UPDATE teachers 
                 SET teacher_code = :code, first_name = :fname, last_name = :lname, 
                     email = :email, phone = :phone, department_id = :dept_id, status = :status 
                 WHERE id = :id"
            );
            $updateStmt->execute([
                ':code'    => $code,
                ':fname'   => $firstName,
                ':lname'   => $lastName,
                ':email'   => $email ?: null,
                ':phone'   => $phone ?: null,
                ':dept_id' => $deptId,
                ':status'  => $status,
                ':id'      => $teacherId
            ]);
            set_flash('success', "Teacher '{$firstName} {$lastName}' updated successfully.");

        } else {
            // Check duplicate
            $checkSql = "SELECT id FROM teachers WHERE teacher_code = :code";
            $checkParams = [':code' => $code];
            if (!empty($email)) {
                $checkSql .= " OR email = :email";
                $checkParams[':email'] = $email;
            }
            $checkSql .= " LIMIT 1";

            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute($checkParams);
            if ($checkStmt->fetch()) {
                set_flash('danger', 'A teacher with this Code or Email already exists.');
                redirect("admin/teachers.php");
            }

            // Insert
            $insertStmt = $pdo->prepare(
                "INSERT INTO teachers (teacher_code, first_name, last_name, email, phone, department_id, status) 
                 VALUES (:code, :fname, :lname, :email, :phone, :dept_id, :status)"
            );
            $insertStmt->execute([
                ':code'    => $code,
                ':fname'   => $firstName,
                ':lname'   => $lastName,
                ':email'   => $email ?: null,
                ':phone'   => $phone ?: null,
                ':dept_id' => $deptId,
                ':status'  => $status
            ]);
            set_flash('success', "Teacher '{$firstName} {$lastName}' added successfully.");
        }

        redirect('admin/teachers.php');

    } catch (PDOException $e) {
        error_log("Teacher save error: " . $e->getMessage());
        set_flash('danger', 'Database error occurred while saving teacher record.');
        redirect('admin/teachers.php');
    }
}

// Handle GET: Delete
if ($action === 'delete' && $editId > 0) {
    try {
        $chkAssign = $pdo->prepare("SELECT COUNT(*) FROM teacher_subjects WHERE teacher_id = :id");
        $chkAssign->execute([':id' => $editId]);
        $hasAssignments = (int) $chkAssign->fetchColumn();

        if ($hasAssignments > 0) {
            set_flash('danger', "Cannot delete this teacher because they are currently assigned to {$hasAssignments} subject/section(s). Please reassign or delete those assignments first.");
        } else {
            $delStmt = $pdo->prepare("DELETE FROM teachers WHERE id = :id");
            $delStmt->execute([':id' => $editId]);
            set_flash('success', 'Teacher deleted successfully.');
        }

    } catch (PDOException $e) {
        error_log("Teacher delete error: " . $e->getMessage());
        set_flash('danger', 'Unable to delete teacher. Foreign key constraints protect this record.');
    }
    redirect('admin/teachers.php');
}

// Fetch record if editing
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editTeacher = $stmt->fetch();
    if (!$editTeacher) {
        set_flash('danger', 'Teacher record not found.');
        redirect('admin/teachers.php');
    }
}

// Fetch List with Search & Pagination
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(t.teacher_code LIKE :search OR t.first_name LIKE :search OR t.last_name LIKE :search OR t.email LIKE :search OR t.phone LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($deptFilter > 0) {
    $whereClauses[] = "t.department_id = :dept_filter";
    $params[':dept_filter'] = $deptFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "t.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM teachers t JOIN departments d ON t.department_id = d.id {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch page rows
$listSql = "SELECT t.*, d.department_code, d.department_name,
            (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.teacher_id = t.id) AS assigned_subjects_count
            FROM teachers t 
            JOIN departments d ON t.department_id = d.id 
            {$whereSql} 
            ORDER BY t.first_name ASC, t.last_name ASC 
            LIMIT {$limit} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$teachers = $listStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: grid; grid-template-columns: <?= $editTeacher ? '1fr' : '360px 1fr' ?>; gap: 24px; align-items: start;">
    
    <!-- Add / Edit Teacher Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= $editTeacher ? 'Edit Teacher' : 'Add New Teacher' ?></h3>
                <div class="card-subtitle"><?= $editTeacher ? 'Update faculty details' : 'Register a faculty member' ?></div>
            </div>
            <?php if ($editTeacher): ?>
                <a href="teachers.php" class="btn btn-sm btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="teachers.php" autocomplete="off">
                <?= csrf_input() ?>
                <input type="hidden" name="teacher_id" value="<?= $editTeacher['id'] ?? 0 ?>">

                <div class="form-group">
                    <label class="form-label" for="teacher_code">Teacher Code <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="teacher_code" 
                        name="teacher_code" 
                        class="form-control" 
                        placeholder="e.g. TCH101" 
                        maxlength="50" 
                        required 
                        value="<?= escape($editTeacher['teacher_code'] ?? '') ?>"
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-control" 
                            placeholder="First Name" 
                            maxlength="100" 
                            required 
                            value="<?= escape($editTeacher['first_name'] ?? '') ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="form-control" 
                            placeholder="Last Name" 
                            maxlength="100" 
                            required 
                            value="<?= escape($editTeacher['last_name'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="department_id">Department <span class="required">*</span></label>
                    <select id="department_id" name="department_id" class="form-select" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($editTeacher['department_id'] ?? $deptFilter) == $dept['id'] ? 'selected' : '' ?>>
                                <?= escape($dept['department_name']) ?> (<?= escape($dept['department_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="teacher@college.edu" 
                        maxlength="150" 
                        value="<?= escape($editTeacher['email'] ?? '') ?>"
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
                        value="<?= escape($editTeacher['phone'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="Active" <?= ($editTeacher['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($editTeacher['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <?= $editTeacher ? 'Update Teacher' : 'Save Teacher' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Teachers List Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">All Faculty (<?= number_format($totalRows) ?>)</h3>
                <div class="card-subtitle">Instructors and lecturers list</div>
            </div>
        </div>

        <!-- Filter / Search Toolbar -->
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); background: #fafbfd;">
            <form method="GET" action="teachers.php" class="filter-actions" style="margin: 0; display: flex; gap: 12px; flex-wrap: wrap;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="Search name, code, email..." 
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
                    <a href="teachers.php" class="btn btn-outline">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Teacher Name</th>
                            <th>Department</th>
                            <th>Contact</th>
                            <th>Assigned Classes</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($teachers)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">👨‍🏫</div>
                                        <div class="empty-state-title">No Faculty Found</div>
                                        <div class="empty-state-text">Add teachers to assign them to subjects and class sections.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($teachers as $tch): ?>
                                <tr>
                                    <td><strong><?= escape($tch['teacher_code']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($tch['first_name'] . ' ' . $tch['last_name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary"><?= escape($tch['department_code']) ?></span>
                                    </td>
                                    <td style="font-size: 12px;">
                                        <?php if (!empty($tch['email'])): ?>
                                            <div>📧 <?= escape($tch['email']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($tch['phone'])): ?>
                                            <div>📞 <?= escape($tch['phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if (empty($tch['email']) && empty($tch['phone'])): ?>
                                            <span style="color: var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-info"><?= number_format($tch['assigned_subjects_count']) ?> assigned</span></td>
                                    <td><?= render_status_badge($tch['status']) ?></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="teachers.php?action=edit&id=<?= $tch['id'] ?>" class="btn btn-sm btn-outline">
                                            ✏️ Edit
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('teachers.php?action=delete&id=<?= $tch['id'] ?>', 'teacher <?= escape($tch['first_name'] . ' ' . $tch['last_name']) ?>')"
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
                    Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> teachers
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
