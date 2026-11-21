<?php

/**
 * Excel Export Handler
 * Student Attendance Management System
 * Uses PhpSpreadsheet with automatic CSV fallback if library is not installed
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
$stuFilter   = (int) ($_GET['student_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$rows = [];
$filename = "attendance_report_{$reportType}_" . date('Ymd_His');

// Query data based on report type
try {
    switch ($reportType) {
        case 'daily':
            $headers = ['Roll No', 'Student Code', 'Student Name', 'Gender', 'Department', 'Section', 'Subject Code', 'Subject Name', 'Date', 'Status', 'Remarks'];
            $where = ["a.attendance_date = :att_date"];
            $params = [':att_date' => $date];
            if ($deptFilter > 0) { $where[] = "s.department_id = :dept"; $params[':dept'] = $deptFilter; }
            if ($secFilter > 0) { $where[] = "s.section_id = :sec"; $params[':sec'] = $secFilter; }
            if ($subjFilter > 0) { $where[] = "a.subject_id = :subj"; $params[':subj'] = $subjFilter; }
            if (!empty($statusFilter)) { $where[] = "a.attendance_status = :status"; $params[':status'] = $statusFilter; }

            $sql = "SELECT s.roll_number, s.student_code, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.gender,
                    d.department_code, sec.section_name, sub.subject_code, sub.subject_name,
                    a.attendance_date, a.attendance_status, a.remarks
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
            $headers = ['Roll No', 'Student Code', 'Student Name', 'Department', 'Section', 'Total Classes', 'Present', 'Absent', 'Attendance Percentage'];
            $monthStart = $month . '-01';
            $monthEnd   = date('Y-m-t', strtotime($monthStart));
            $where = ["a.attendance_date BETWEEN :start AND :end"];
            $params = [':start' => $monthStart, ':end' => $monthEnd];
            if ($deptFilter > 0) { $where[] = "s.department_id = :dept"; $params[':dept'] = $deptFilter; }
            if ($secFilter > 0) { $where[] = "s.section_id = :sec"; $params[':sec'] = $secFilter; }
            if ($subjFilter > 0) { $where[] = "a.subject_id = :subj"; $params[':subj'] = $subjFilter; }

            $sql = "SELECT s.roll_number, s.student_code, CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    d.department_code, sec.section_name,
                    COUNT(a.id) AS total_sessions,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
                    FROM students s
                    JOIN departments d ON s.department_id = d.id
                    JOIN sections sec ON s.section_id = sec.id
                    JOIN attendance a ON s.id = a.student_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY s.id, s.student_code, s.roll_number, s.first_name, s.last_name, d.department_code, sec.section_name
                    ORDER BY d.department_code ASC, sec.section_name ASC, s.roll_number ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetchAll();
            foreach ($res as $r) {
                $pct = calculate_percentage($r['present_count'], $r['total_sessions']) . '%';
                $rows[] = [$r['roll_number'], $r['student_code'], $r['student_name'], $r['department_code'], $r['section_name'], $r['total_sessions'], $r['present_count'], $r['absent_count'], $pct];
            }
            break;

        case 'subject':
            $headers = ['Subject Code', 'Subject Name', 'Department Code', 'Dates Conducted', 'Total Student Attendances', 'Present', 'Absent', 'Attendance Percentage'];
            $where = ["1=1"];
            $params = [];
            if (!empty($startDate)) { $where[] = "a.attendance_date >= :start"; $params[':start'] = $startDate; }
            if (!empty($endDate)) { $where[] = "a.attendance_date <= :end"; $params[':end'] = $endDate; }
            if ($deptFilter > 0) { $where[] = "sub.department_id = :dept"; $params[':dept'] = $deptFilter; }

            $sql = "SELECT sub.subject_code, sub.subject_name, d.department_code,
                    COUNT(DISTINCT a.attendance_date) AS total_dates_conducted,
                    COUNT(a.id) AS total_student_sessions,
                    SUM(CASE WHEN a.attendance_status = 'Present' THEN 1 ELSE 0 END) AS total_present,
                    SUM(CASE WHEN a.attendance_status = 'Absent' THEN 1 ELSE 0 END) AS total_absent
                    FROM subjects sub
                    JOIN departments d ON sub.department_id = d.id
                    LEFT JOIN attendance a ON sub.id = a.subject_id AND " . implode(' AND ', $where) . "
                    WHERE sub.status = 'Active' " . ($deptFilter > 0 ? "AND sub.department_id = :d_target" : "") . "
                    GROUP BY sub.id, sub.subject_code, sub.subject_name, d.department_code
                    ORDER BY d.department_code ASC, sub.subject_code ASC";
            if ($deptFilter > 0) $params[':d_target'] = $deptFilter;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetchAll();
            foreach ($res as $r) {
                $pct = calculate_percentage($r['total_present'], $r['total_student_sessions']) . '%';
                $rows[] = [$r['subject_code'], $r['subject_name'], $r['department_code'], $r['total_dates_conducted'], $r['total_student_sessions'], $r['total_present'], $r['total_absent'], $pct];
            }
            break;

        default:
            $headers = ['Department Code', 'Department Name', 'Total Sessions', 'Present', 'Absent', 'Percentage'];
            $sql = "SELECT d.department_code, d.department_name,
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
                $rows[] = [$r['department_code'], $r['department_name'], $r['total_records'], $r['total_present'], $r['total_absent'], $pct];
            }
            break;
    }
} catch (PDOException $e) {
    die("Database error during export: " . $e->getMessage());
}

// Check if PhpSpreadsheet is installed via Composer
$autoloadFile = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
    if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(ucfirst($reportType), 0, 30));

        // Set Headers
        $colIndex = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $h);
            $colIndex++;
        }

        // Set Rows
        $rowIndex = 2;
        foreach ($rows as $r) {
            $cIndex = 1;
            foreach ($r as $val) {
                $sheet->setCellValueByColumnAndRow($cIndex, $rowIndex, $val);
                $cIndex++;
            }
            $rowIndex++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

// Fallback: Standard CSV formatted stream compatible with Excel
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, $headers);

foreach ($rows as $r) {
    fputcsv($output, $r);
}

fclose($output);
exit;
