# Student Attendance Management System (SAMS)

A complete, robust, and functional Student Attendance Management System built with pure **Core PHP**, **MySQL/MariaDB (PDO)**, **HTML5**, **CSS3**, and **Vanilla JavaScript**. Strictly designed for **Admin-Only** institutional management with zero bloated third-party frameworks.

---

## 🛠️ Technology Stack

- **Server-Side:** PHP 7.4+ / PHP 8.x (Core PHP with PDO)
- **Database:** MySQL / MariaDB (InnoDB, utf8mb4)
- **Client-Side:** HTML5, CSS3, Vanilla JavaScript (ES6+)
- **Server Environment:** XAMPP (Apache + MySQL)
- **Optional Libraries:** PhpSpreadsheet (Excel), Dompdf (PDF) via Composer

---

## 🚀 Key Features

### 1. Admin Authentication & Security
- Single-role architecture (Admin only; strictly no student or teacher logins).
- Login via **Username** or **Email**.
- Secure password hashing using `password_hash(PASSWORD_DEFAULT)` and `password_verify()`.
- Session fixation protection with `session_regenerate_id(true)`.
- CSRF token verification on all POST forms.
- SQL injection protection via PDO prepared statements with strict parameter binding.
- Output escaping with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Safe setup locking: `setup-admin.php` automatically locks once the initial administrator is created.

### 2. Academic Structure Management (CRUD)
- **Departments:** Create, edit, search, filter, and delete academic departments. Prevents deletion if sections, subjects, students, or faculty are linked.
- **Sections:** Associate class sections with departments. Validates unique section names within each department.
- **Subjects:** Catalog curriculum subjects by department with unique subject codes.

### 3. Faculty & Student Management
- **Teachers:** Manage faculty details, contact information, and departmental affiliations. (Teachers do not require user accounts).
- **Students:** Complete student registration with student ID, unique roll number per section, contact details, date of birth, gender, and admission date.
- **Dependent Dynamic Validation:** JavaScript automatically refreshes available sections when selecting a department, backed by strict server-side validation ensuring the section belongs to the chosen department.
- **Student Profile View:** Displays student profile along with subject-wise attendance breakdown and overall percentage.

### 4. Course Enrollment & Faculty Allocation
- **Student-Subject Assignments:** Assign courses to individual students or use the **Batch Enroll by Section** feature to enroll all students in a section with one click. Enforces department compatibility and prevents duplicate enrollments.
- **Teacher-Subject Assignments:** Map teachers to specific subjects and sections. Supports multiple subjects per instructor and different instructors across sections.

### 5. Attendance Management (Core Engine)
- **Mark Attendance:** Select attendance date, department, section, and subject.
- **Eligibility Filter:** Only displays active students who are enrolled in the selected subject.
- **Duplicate Prevention:** Enforces one attendance record per student, subject, and date (`unique_student_subject_date`).
- **Update Mode:** If attendance for a date and subject was previously saved, the system alerts the admin and loads existing marks for editing.
- **Transactional Safety:** Uses database transactions (`beginTransaction()`, `commit()`, `rollBack()`) to ensure atomic, safe attendance saving.
- **Live Counter:** Dynamic Vanilla JS calculation of Total, Present, Absent, and Attendance Percentage in real time.
- **Bulk Actions:** Quick "Mark All Present" and "Mark All Absent" buttons.

### 6. History & Attendance Records
- Comprehensive searchable log of all recorded attendance sessions.
- Filter by date range, department, section, subject, student name/roll number, and status.
- Modal-based editing of individual attendance marks and remarks.
- Delete individual attendance logs with confirmation.

### 7. Analytics & Attendance Reports
- **Calculations:**
  - **Subject-Wise %:** `(Present records / Total recorded records for that subject) * 100`
  - **Overall %:** `(Total Present records across assigned subjects / Total attendance records across assigned subjects) * 100`
  - Division-by-zero protection.
- **Report Types:**
  1. *Daily Attendance Report*
  2. *Monthly Attendance Report*
  3. *Student Attendance Report*
  4. *Subject-Wise Report*
  5. *Section-Wise Report*
  6. *Department-Wise Report*
- **Exports:**
  - **Excel Export:** Automatically uses PhpSpreadsheet if installed; otherwise falls back to a clean, formatted UTF-8 CSV stream compatible with Excel.
  - **PDF Export:** Uses Dompdf if installed; otherwise serves a print-optimized layout with browser print integration.

---

## 📂 Project Directory Structure

```text
StudentAttendance/
├── .htaccess                     # Directory protection & charset headers
├── composer.json                 # Optional PhpSpreadsheet & Dompdf dependencies
├── index.php                     # Admin login page
├── logout.php                    # Secure session destruction & logout
├── setup-admin.php               # Initial admin account setup wizard
├── README.md                     # Documentation & setup guide
│
├── config/
│   ├── config.php                # App constants, session configs, base URL helper
│   └── database.php              # PDO MySQL connection
│
├── database/
│   ├── database.sql              # Master database schema (9 tables, InnoDB, utf8mb4)
│   ├── migration.sql             # Safe IF NOT EXISTS migration script
│   └── sample_data.sql           # Optional test data (departments, classes, students)
│
├── includes/
│   ├── auth.php                  # Authentication, CSRF, and session helpers
│   ├── functions.php             # Helpers, security escaping, percentage calculations
│   ├── header.php                # Top navigation, page header, and layout start
│   ├── sidebar.php               # Sidebar navigation menu
│   └── footer.php                # Layout footer, global delete modal, scripts
│
├── admin/
│   ├── api.php                   # AJAX endpoints for dynamic dependent dropdowns
│   ├── dashboard.php             # Stat cards, recent activities, charts/bars
│   ├── departments.php           # Department CRUD
│   ├── sections.php              # Section CRUD
│   ├── teachers.php              # Teacher CRUD
│   ├── students.php              # Student CRUD & profile view
│   ├── subjects.php              # Subject CRUD
│   ├── student-subjects.php      # Student enrollment & batch assignment
│   ├── teacher-subjects.php      # Teacher-subject mapping
│   ├── attendance.php            # Attendance roll-call marking sheet
│   ├── attendance-history.php    # Searchable attendance log & inline editing
│   ├── reports.php               # Comprehensive reporting suite
│   └── profile.php               # Admin profile & password change
│
├── exports/
│   ├── export-excel.php          # PhpSpreadsheet / CSV export engine
│   └── export-pdf.php            # Dompdf / Printable PDF generator
│
└── assets/
    ├── css/
    │   └── style.css             # Pure CSS3 custom design system
    └── js/
        ├── main.js               # Vanilla JS UI interactions & AJAX loaders
        └── attendance.js         # Dynamic attendance counters & bulk toggles
```

---

## ⚙️ How to Configure & Run in XAMPP

### Step 1: Start XAMPP
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.

### Step 2: Database Setup
1. Open your browser and navigate to `http://localhost/phpmyadmin`.
2. Click the **Import** tab.
3. Choose the file `database/database.sql` from this project folder.
4. Click **Import**.
   - This creates the database `student_attendance` with all 9 tables, indexes, and foreign keys.
5. *(Optional)* If you would like sample test data (departments, sections, subjects, teachers, students):
   - Import `database/sample_data.sql`.

### Step 3: Verify Database Connection
- Check `config/database.php`:
  ```php
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'student_attendance');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  ```
  *(Default XAMPP credentials `root` with no password will connect immediately).*

### Step 4: Create the Administrator Account
1. Open your browser and visit:
   `http://localhost/StudentAttendance/setup-admin.php`
2. Fill in:
   - Full Name
   - Username (e.g. `admin`)
   - Email Address (e.g. `admin@college.edu`)
   - Password (minimum 10 characters)
3. Click **Create Admin Account**.
4. The setup will lock itself automatically once the account is created.

### Step 5: Sign In
1. Visit: `http://localhost/StudentAttendance/index.php`
2. Log in using your admin username/email and password.
3. You will be redirected to the **Admin Dashboard** (`admin/dashboard.php`).

---

## 📦 Optional: Installing Composer Libraries (PhpSpreadsheet & Dompdf)

The system works out of the box with built-in fallbacks (CSV Excel downloads and clean printable browser PDFs). If you wish to enable native `.xlsx` and server-rendered `.pdf` files:

1. Open a terminal or Command Prompt in `E:\Projects\Full Stack\StudentAttendance`.
2. Run:
   ```bash
   composer install
   ```
   Or:
   ```bash
   composer require phpoffice/phpspreadsheet dompdf/dompdf
   ```
3. Once the `vendor/` directory is generated, the export engine will automatically detect and use the libraries.

---

## 🔒 Security Best Practices Implemented

- All SQL queries use PDO prepared statements with parameter binding.
- Passwords hashed using industry-standard bcrypt via `password_hash()`.
- CSRF token generated and verified on every POST request.
- HTML output escaped via `htmlspecialchars()` to eliminate XSS vulnerabilities.
- Foreign key constraints (`ON DELETE RESTRICT`) prevent accidental deletion of referenced records and protect historical attendance logs.
- Sensitive files (`.sql`, `.env`, `composer.json`) blocked from web access via `.htaccess`.
