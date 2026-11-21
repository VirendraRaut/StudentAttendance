<?php

/**
 * Attendance History, View, Edit & Delete
 * Student Attendance Management System
 */

$pageTitle = 'Attendance History';
$currentPage = 'attendance_history';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

$departments = get_active_departments($pdo, false);

$action = $_GET['action'] ?? 'list';
$recordId = (int) ($_GET['id'] ?? 0);

// Filters
$startDate  = trim($_GET['start_date'] ?? '');
$endDate    = trim($_GET['end_date'] ?? '');
$deptFilter = (int) ($_GET['department_id'] ?? 0);
$secFilter  = (int) ($_GET['section_id'] ?? 0);
$subjFilter = (int) ($_GET['subject_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$search     = trim($_GET['search'] ?? '');

// Handle POST: Update Single Attendance Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrfToken)) {
        set_flash('danger', 'Invalid session security token.');
        redirect('admin/attendance-history.php');
    }

    $attId  = (int) ($_POST['attendance_id'] ?? 0);
    $status = $_POST['attendance_status'] ?? 'Present';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!in_array($status, ['Present', 'Absent'])) {
        $status = 'Present';
    }

    try {
        $updateStmt = $pdo->prepare(
            "UPDATE attendance 
             SET attendance_status = :status, remarks = :remarks, marked_by = :admin_id 
             WHERE id = :id"
        );
        $updateStmt->execute([
            ':status'   => $status,
            ':remarks'  => !empty($remarks) ? $remarks : null,
            ':admin_id' => $_SESSION['admin_id'],
            ':id'       => $attId
        ]);
        set_flash('success', 'Attendance record updated successfully.');
    } catch (PDOException $e) {
        error_log("Update attendance error: " . $e->getMessage());
        set_flash('danger', 'Database error occurred while updating attendance.');
    }

    // Preserve filter parameters
    $query = $_SERVER['QUERY_STRING'] ?? '';
    redirect('admin/attendance-history.php?' . $query);
}

// Handle GET: Delete Attendance Record
if ($action === 'delete' && $recordId > 0) {
    try {
        $delStmt = $pdo->prepare("DELETE FROM attendance WHERE id = :id");
        $delStmt->execute([':id' => $recordId]);
        set_flash('success', 'Attendance entry removed successfully.');
    } catch (PDOException $e) {
        error_log("Delete attendance error: " . $e->getMessage());
        set_flash('danger', 'Unable to delete attendance record.');
    }

    // Preserve filters
    $queryString = http_build_query([
        'start_date'    => $startDate,
        'end_date'      => $endDate,
        'department_id' => $deptFilter ?: '',
        'section_id'    => $secFilter ?: '',
        'subject_id'    => $subjFilter ?: '',
        'status'        => $statusFilter,
        'search'        => $search
    ]);
    redirect('admin/attendance-history.php?' . $queryString);
}

// Build Search and Filter Queries
$whereClauses = [];
$params = [];

if (!empty($startDate)) {
    $whereClauses[] = "a.attendance_date >= :start_date";
    $params[':start_date'] = $startDate;
}

if (!empty($endDate)) {
    $whereClauses[] = "a.attendance_date <= :end_date";
    $params[':end_date'] = $endDate;
}

if ($deptFilter > 0) {
    $whereClauses[] = "s.department_id = :dept_filter";
    $params[':dept_filter'] = $deptFilter;
}

if ($secFilter > 0) {
    $whereClauses[] = "s.section_id = :sec_filter";
    $params[':sec_filter'] = $secFilter;
}

if ($subjFilter > 0) {
    $whereClauses[] = "a.subject_id = :subj_filter";
    $params[':subj_filter'] = $subjFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "a.attendance_status = :status_filter";
    $params[':status_filter'] = $statusFilter;
}

if (!empty($search)) {
    $whereClauses[] = "(s.student_code LIKE :search OR s.roll_number LIKE :search OR s.first_name LIKE :search OR s.last_name LIKE :search OR sub.subject_code LIKE :search OR sub.subject_name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : '';

// 1. Calculate Filtered Aggregate Stats
$statsSql = "SELECT 
                COUNT(*) AS total_records,
                SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS total_present,
                SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS total_absent
             FROM attendance a
             JOIN students s ON a.student_id = s.id
             JOIN subjects sub ON a.subject_id = sub.id
             JOIN departments d ON s.department_id = d.id
             JOIN sections sec ON s.section_id = sec.id
             {$whereSql}";

$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);
$aggStats = $statsStmt->fetch();

$filteredTotal   = (int) ($aggStats['total_records'] ?? 0);
$filteredPresent = (int) ($aggStats['total_present'] ?? 0);
$filteredAbsent  = (int) ($aggStats['total_absent'] ?? 0);
$filteredPct     = calculate_percentage($filteredPresent, $filteredTotal);

// 2. Pagination
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;
$totalPages = ceil($filteredTotal / $limit);

// 3. Fetch Records
$listSql = "SELECT a.*, 
            s.student_code, s.roll_number, s.first_name, s.last_name,
            d.department_code, d.department_name,
            sec.section_name,
            sub.subject_code, sub.subject_name,
            adm.full_name AS marked_by_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN subjects sub ON a.subject_id = sub.id
            JOIN departments d ON s.department_id = d.id
            JOIN sections sec ON s.section_id = sec.id
            JOIN admins adm ON a.marked_by = adm.id
            {$whereSql}
            ORDER BY a.attendance_date DESC, d.department_code ASC, sec.section_name ASC, s.roll_number ASC
            LIMIT {$limit} OFFSET {$offset}";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$attendanceRecords = $listStmt->fetchAll();

// Subjects list for filter
$subjectsForFilter = $deptFilter > 0 ? get_subjects_by_department($pdo, $deptFilter, false) : [];
$sectionsForFilter = $deptFilter > 0 ? get_sections_by_department($pdo, $deptFilter, false) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header Block -->
<div class="page-header-block">
    <h1 class="page-title">Attendance History</h1>
    <p class="page-subtitle">View, edit, and manage attendance logs with filters.</p>
</div>
<!-- Filter Card -->
<div class="filter-card">
    <form method="GET" action="attendance-history.php" id="historyFilterForm">
        <div class="form-row-4" style="margin-bottom: 14px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="start_date">From Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?= escape($startDate) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="end_date">To Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?= escape($endDate) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="department_select">Department</label>
                <select id="department_select" name="department_id" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $deptFilter == $dept['id'] ? 'selected' : '' ?>>
                            <?= escape($dept['department_name']) ?> (<?= escape($dept['department_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="section_select">Section</label>
                <select id="section_select" name="section_id" class="form-select">
                    <option value="">All Sections</option>
                    <?php foreach ($sectionsForFilter as $sec): ?>
                        <option value="<?= $sec['id'] ?>" <?= $secFilter == $sec['id'] ? 'selected' : '' ?>>
                            <?= escape($sec['section_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row-3">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="subject_select">Subject</label>
                <select id="subject_select" name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjectsForFilter as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= $subjFilter == $sub['id'] ? 'selected' : '' ?>>
                            <?= escape($sub['subject_code']) ?> - <?= escape($sub['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="status">Attendance Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All (Present & Absent)</option>
                    <option value="Present" <?= $statusFilter === 'Present' ? 'selected' : '' ?>>Present Only</option>
                    <option value="Absent" <?= $statusFilter === 'Absent' ? 'selected' : '' ?>>Absent Only</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="search">Search Keywords</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Search student, roll, or code..." value="<?= escape($search) ?>">
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 16px;">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="attendance-history.php" class="btn btn-outline">Clear Filters</a>
            <a href="reports.php" class="btn btn-secondary" style="margin-left: auto;">📊 Advanced Analytics & Exports</a>
        </div>
    </form>
</div>

<!-- Aggregated Summary Bar for Filtered Results -->
<div class="attendance-summary-bar">
    <div class="summary-pill">
        <span>Filtered Records:</span>
        <span class="summary-pill-num badge badge-secondary"><?= number_format($filteredTotal) ?></span>
    </div>
    <div class="summary-pill">
        <span>Total Present:</span>
        <span class="summary-pill-num badge badge-success"><?= number_format($filteredPresent) ?></span>
    </div>
    <div class="summary-pill">
        <span>Total Absent:</span>
        <span class="summary-pill-num badge badge-danger"><?= number_format($filteredAbsent) ?></span>
    </div>
    <div class="summary-pill">
        <span>Filtered Attendance %:</span>
        <span class="summary-pill-num badge <?= $filteredPct >= 75 ? 'badge-success' : ($filteredPct >= 60 ? 'badge-warning' : 'badge-danger') ?>">
            <?= number_format($filteredPct, 1) ?>%
        </span>
    </div>
</div>

<!-- Attendance Records Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Attendance Logs (<?= number_format($filteredTotal) ?>)</h3>
            <div class="card-subtitle">Audited attendance historical logs</div>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Roll No</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Marked By</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendanceRecords)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div class="empty-state-icon">🕒</div>
                                    <div class="empty-state-title">No Attendance Records Found</div>
                                    <div class="empty-state-text">No attendance logs matched the applied filter criteria.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendanceRecords as $rec): ?>
                            <tr>
                                <td><strong><?= format_date($rec['attendance_date'], 'd M Y') ?></strong></td>
                                <td>
                                    <strong><?= escape($rec['first_name'] . ' ' . $rec['last_name']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= escape($rec['student_code']) ?></div>
                                </td>
                                <td><span class="badge badge-secondary"><?= escape($rec['roll_number']) ?></span></td>
                                <td>
                                    <span class="badge badge-secondary"><?= escape($rec['department_code']) ?></span>
                                    <div style="font-size: 11px;"><?= escape($rec['section_name']) ?></div>
                                </td>
                                <td>
                                    <strong><?= escape($rec['subject_code']) ?></strong>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= escape($rec['subject_name']) ?></div>
                                </td>
                                <td><?= render_status_badge($rec['attendance_status']) ?></td>
                                <td style="font-size: 12px;"><?= !empty($rec['remarks']) ? escape($rec['remarks']) : '—' ?></td>
                                <td style="font-size: 11px; color: var(--text-muted);"><?= escape($rec['marked_by_name']) ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline" 
                                        onclick="openEditAttendanceModal(<?= $rec['id'] ?>, '<?= escape($rec['attendance_status']) ?>', '<?= escape($rec['remarks'] ?? '') ?>', '<?= escape($rec['first_name'] . ' ' . $rec['last_name']) ?>', '<?= escape($rec['attendance_date']) ?>')"
                                    >
                                        ✏️ Edit
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-danger" 
                                        onclick="confirmDelete('attendance-history.php?action=delete&id=<?= $rec['id'] ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&department_id=<?= $deptFilter ?>&status=<?= urlencode($statusFilter) ?>', 'attendance record for <?= escape($rec['first_name'] . ' ' . $rec['last_name']) ?> on <?= escape($rec['attendance_date']) ?>')"
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

    <?php if ($totalPages > 1): 
        $queryBase = $_GET;
        unset($queryBase['p']);
        $qs = http_build_query($queryBase);
    ?>
        <div class="card-footer">
            <div style="font-size: 12px; color: var(--text-muted);">
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $filteredTotal) ?> of <?= $filteredTotal ?> records
            </div>
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?p=<?= $page - 1 ?>&<?= $qs ?>">Prev</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?p=<?= $i ?>&<?= $qs ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?p=<?= $page + 1 ?>&<?= $qs ?>">Next</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- Modal for Editing Single Attendance Record -->
<div class="modal-backdrop" id="editAttendanceModal">
    <div class="modal-dialog">
        <form method="POST" action="attendance-history.php?<?= escape($_SERVER['QUERY_STRING'] ?? '') ?>">
            <?= csrf_input() ?>
            <input type="hidden" name="update_attendance" value="1">
            <input type="hidden" name="attendance_id" id="modal_attendance_id" value="0">

            <div class="modal-header">
                <h3 class="modal-title">Edit Attendance Status</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('editAttendanceModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 13px;">
                    <div>Student: <strong id="modal_student_name">—</strong></div>
                    <div>Date: <strong id="modal_attendance_date">—</strong></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="modal_attendance_status">Attendance Status <span class="required">*</span></label>
                    <select id="modal_attendance_status" name="attendance_status" class="form-select" required>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="modal_remarks">Remarks</label>
                    <input type="text" id="modal_remarks" name="remarks" class="form-control" placeholder="e.g. Leave, Medical, Excused, Late arrival">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editAttendanceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Record</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditAttendanceModal(id, status, remarks, studentName, date) {
    document.getElementById('modal_attendance_id').value = id;
    document.getElementById('modal_attendance_status').value = status;
    document.getElementById('modal_remarks').value = remarks;
    document.getElementById('modal_student_name').textContent = studentName;
    document.getElementById('modal_attendance_date').textContent = date;
    openModal('editAttendanceModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
