<?php

/**
 * Common Helper Functions
 * Student Attendance Management System
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Escape HTML output to prevent XSS
 */
function escape(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Alias for escape()
 */
function e(?string $value): string {
    return escape($value);
}

/**
 * Set flash message in session
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_notification'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Get flash message and clear it from session
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash_notification'])) {
        $flash = $_SESSION['flash_notification'];
        unset($_SESSION['flash_notification']);
        return $flash;
    }
    return null;
}

/**
 * Redirect to a specific URL
 */
function redirect(string $url): void {
    // If not an absolute URL, prepend BASE_URL if relative to web root
    if (!preg_match('/^https?:\/\//i', $url)) {
        if (strpos($url, '/') === 0) {
            $url = BASE_URL . $url;
        } else {
            $url = BASE_URL . '/' . $url;
        }
    }
    header("Location: " . $url);
    exit;
}

/**
 * Calculate attendance percentage safely handling division by zero
 */
function calculate_percentage($present, $total): float {
    $present = (int) $present;
    $total   = (int) $total;

    if ($total <= 0) {
        return 0.00;
    }

    $percentage = ($present / $total) * 100;
    return round($percentage, 2);
}

/**
 * Format date for display
 */
function format_date(?string $date, string $format = 'd M Y'): string {
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '—';
}

/**
 * Render a styled badge for status
 */
function render_status_badge(string $status): string {
    $statusClass = strtolower($status) === 'active' || strtolower($status) === 'present' 
        ? 'badge-success' 
        : 'badge-danger';

    return '<span class="badge ' . $statusClass . '">' . escape($status) . '</span>';
}

/**
 * Fetch all active departments
 */
function get_active_departments(PDO $pdo, bool $onlyActive = true): array {
    try {
        $sql = "SELECT id, department_code, department_name FROM departments";
        if ($onlyActive) {
            $sql .= " WHERE status = 'Active'";
        }
        $sql .= " ORDER BY department_name ASC";
        return $pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch sections by department ID
 */
function get_sections_by_department(PDO $pdo, int $departmentId, bool $onlyActive = true): array {
    try {
        $sql = "SELECT id, section_name, department_id FROM sections WHERE department_id = :dept_id";
        if ($onlyActive) {
            $sql .= " AND status = 'Active'";
        }
        $sql .= " ORDER BY section_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dept_id' => $departmentId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching sections: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch subjects by department ID
 */
function get_subjects_by_department(PDO $pdo, int $departmentId, bool $onlyActive = true): array {
    try {
        $sql = "SELECT id, subject_code, subject_name FROM subjects WHERE department_id = :dept_id";
        if ($onlyActive) {
            $sql .= " AND status = 'Active'";
        }
        $sql .= " ORDER BY subject_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dept_id' => $departmentId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching subjects: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch teachers, optionally filtered by department
 */
function get_teachers(PDO $pdo, ?int $departmentId = null, bool $onlyActive = true): array {
    try {
        $sql = "SELECT t.id, t.teacher_code, t.first_name, t.last_name, t.email, t.phone, t.department_id, d.department_name 
                FROM teachers t
                LEFT JOIN departments d ON t.department_id = d.id";
        $conditions = [];
        $params = [];

        if ($onlyActive) {
            $conditions[] = "t.status = 'Active'";
        }
        if ($departmentId !== null) {
            $conditions[] = "t.department_id = :dept_id";
            $params[':dept_id'] = $departmentId;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        $sql .= " ORDER BY t.first_name ASC, t.last_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching teachers: " . $e->getMessage());
        return [];
    }
}
