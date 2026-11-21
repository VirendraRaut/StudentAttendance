<?php

/**
 * PDF Export Handler
 * Student Attendance Management System
 * Uses Dompdf with elegant printable HTML view fallback
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();

// Filters from GET
$reportType = $_GET['report_type'] ?? 'daily';
$date        = trim($_GET['date'] ?? date('Y-m-d'));
$month       = trim($_GET['month'] ?? date('Y-m'));
$startDate   = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate     = trim($_GET['end_date'] ?? date('Y-m-d'));
$deptFilter  = (int) ($_GET['department_id'] ?? 0);
$secFilter   = (int) ($_GET['section_id'] ?? 0);
$subjFilter  = (int) ($_GET['subject_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$rows = [];
$filename = "attendance_report_{$reportType}_" . date('Ymd_His');

// Fetch department name if filtered
$deptName = 'All Departments';
if ($deptFilter > 0) {
    $dStmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = :id");
    $dStmt->execute([':id' => $deptFilter]);
    $deptName = $dStmt->fetchColumn() ?: 'Department #' . $deptFilter;
}

try {
    switch ($reportType) {
        case 'daily':
            $headers = ['Roll', 'Student Code', 'Student Name', 'Section', 'Subject', 'Status', 'Remarks'];
            $where = ["a.attendance_date = :att_date"];
            $params = [':att_date' => $date];
            if ($deptFilter > 0) { $where[] = "s.department_id = :dept"; $params[':dept'] = $deptFilter; }
            if ($secFilter > 0) { $where[] = "s.section_id = :sec"; $params[':sec'] = $secFilter; }
            if ($subjFilter > 0) { $where[] = "a.subject_id = :subj"; $params[':subj'] = $subjFilter; }
            if (!empty($statusFilter)) { $where[] = "a.attendance_status = :status"; $params[':status'] = $statusFilter; }

            $sql = "SELECT s.roll_number, s.student_code, CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    sec.section_name, sub.subject_code, a.attendance_status, a.remarks
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    JOIN subjects sub ON a.subject_id = sub.id
                    JOIN departments d ON s.department_id = d.id
                    JOIN sections sec ON s.section_id = sec.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY d.department_code ASC, sec.section_name ASC, s.roll_number ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);
            break;

        case 'monthly':
            $headers = ['Roll', 'Student Code', 'Student Name', 'Section', 'Classes', 'Present', 'Absent', 'Percentage'];
            $monthStart = $month . '-01';
            $monthEnd   = date('Y-m-t', strtotime($monthStart));
            $where = ["a.attendance_date BETWEEN :start AND :end"];
            $params = [':start' => $monthStart, ':end' => $monthEnd];
            if ($deptFilter > 0) { $where[] = "s.department_id = :dept"; $params[':dept'] = $deptFilter; }
            if ($secFilter > 0) { $where[] = "s.section_id = :sec"; $params[':sec'] = $secFilter; }
            if ($subjFilter > 0) { $where[] = "a.subject_id = :subj"; $params[':subj'] = $subjFilter; }

            $sql = "SELECT s.roll_number, s.student_code, CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    sec.section_name,
                    COUNT(a.id) AS total_sessions,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
                    FROM students s
                    JOIN departments d ON s.department_id = d.id
                    JOIN sections sec ON s.section_id = sec.id
                    JOIN attendance a ON s.id = a.student_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY s.id, s.student_code, s.roll_number, s.first_name, s.last_name, sec.section_name
                    ORDER BY sec.section_name ASC, s.roll_number ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetchAll();
            foreach ($res as $r) {
                $pct = calculate_percentage($r['present_count'], $r['total_sessions']) . '%';
                $rows[] = [$r['roll_number'], $r['student_code'], $r['student_name'], $r['section_name'], $r['total_sessions'], $r['present_count'], $r['absent_count'], $pct];
            }
            break;

        default:
            $headers = ['Department', 'Code', 'Total Classes', 'Present Marks', 'Absent Marks', 'Percentage'];
            $sql = "SELECT d.department_name, d.department_code,
                    COUNT(a.id) AS total_records,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS total_present,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS total_absent
                    FROM departments d
                    LEFT JOIN students s ON d.id = s.department_id
                    LEFT JOIN attendance a ON s.id = a.student_id
                    WHERE d.status = 'Active'
                    GROUP BY d.id, d.department_code, d.department_name
                    ORDER BY d.department_name ASC";
            $res = $pdo->query($sql)->fetchAll();
            foreach ($res as $r) {
                $pct = calculate_percentage($r['total_present'], $r['total_records']) . '%';
                $rows[] = [$r['department_name'], $r['department_code'], $r['total_records'], $r['total_present'], $r['total_absent'], $pct];
            }
            break;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Build Clean HTML Document for PDF/Print
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= escape(APP_NAME) ?> - Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1e293b; margin: 20px; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #2563eb; padding-bottom: 15px; }
        .title { font-size: 20px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #64748b; }
        .meta { display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f1f5f9; color: #334155; font-weight: bold; text-align: left; padding: 8px 10px; border: 1px solid #cbd5e1; font-size: 11px; text-transform: uppercase; }
        td { padding: 8px 10px; border: 1px solid #cbd5e1; font-size: 11.5px; }
        tr:nth-child(even) { background: #f8fafc; }
        .badge-present { color: #166534; font-weight: bold; }
        .badge-absent { color: #991b1b; font-weight: bold; }
        .footer { margin-top: 30px; text-align: right; font-size: 10px; color: #94a3b8; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print" style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
    <div>
        <strong>Report Ready to Print / Save:</strong> You can click the button on the right to Print or Save as PDF.
        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
            To enable headless Dompdf rendering, execute <code>composer require dompdf/dompdf</code> in your project directory.
        </div>
    </div>
    <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">
        🖨️ Print / Save as PDF
    </button>
</div>

<div class="header">
    <div class="title"><?= escape(APP_NAME) ?></div>
    <div class="subtitle"><?= ucfirst(escape($reportType)) ?> Attendance Report &bull; Department: <?= escape($deptName) ?></div>
</div>

<div class="meta">
    <div><strong>Generated On:</strong> <?= date('d M Y, h:i A') ?></div>
    <div><strong>Total Rows:</strong> <?= count($rows) ?> records</div>
</div>

<table>
    <thead>
        <tr>
            <?php foreach ($headers as $h): ?>
                <th><?= escape($h) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="<?= count($headers) ?>" style="text-align: center; padding: 20px;">No attendance records found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <?php foreach ($r as $val): 
                        $statusClass = ($val === 'Present') ? 'badge-present' : (($val === 'Absent') ? 'badge-absent' : '');
                    ?>
                        <td class="<?= $statusClass ?>"><?= escape((string)$val) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    Report generated by Student Attendance Management System &bull; Strictly Official Record
</div>

<script class="no-print">
    // Optional auto-print trigger if requested
    if (window.location.search.includes('autoprint=1')) {
        window.print();
    }
</script>

</body>
</html>
<?php
$html = ob_get_clean();

// Check if Dompdf is installed via Composer
$autoloadFile = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
    if (class_exists('Dompdf\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,
            'defaultFont' => 'Helvetica'
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf', ['Attachment' => 1]);
        exit;
    }
}

// Fallback: Output the clean styled HTML printable page
echo $html;
exit;
