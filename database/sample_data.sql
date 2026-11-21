-- =====================================================
-- OPTIONAL SAMPLE SEED DATA
-- Student Attendance Management System
-- Use to populate test data safely without dropping tables
-- =====================================================

USE student_attendance;

-- 1. Sample Departments
INSERT IGNORE INTO departments (id, department_code, department_name, description, status) VALUES
(1, 'CSE', 'Computer Science and Engineering', 'Department of Computer Science and Engineering', 'Active'),
(2, 'IT', 'Information Technology', 'Department of Information Technology', 'Active'),
(3, 'ECE', 'Electronics and Communication Engineering', 'Department of Electronics and Communication', 'Active'),
(4, 'MECH', 'Mechanical Engineering', 'Department of Mechanical Engineering', 'Active');

-- 2. Sample Sections
INSERT IGNORE INTO sections (id, department_id, section_name, description, status) VALUES
(1, 1, 'CSE-A', 'Computer Science Section A', 'Active'),
(2, 1, 'CSE-B', 'Computer Science Section B', 'Active'),
(3, 2, 'IT-A', 'Information Technology Section A', 'Active'),
(4, 4, 'MECH-A', 'Mechanical Engineering Section A', 'Active');

-- 3. Sample Teachers
INSERT IGNORE INTO teachers (id, teacher_code, first_name, last_name, email, phone, department_id, status) VALUES
(1, 'TCH101', 'Alan', 'Turing', 'turing@college.edu', '+91 9876543201', 1, 'Active'),
(2, 'TCH102', 'Ada', 'Lovelace', 'lovelace@college.edu', '+91 9876543202', 1, 'Active'),
(3, 'TCH201', 'Grace', 'Hopper', 'hopper@college.edu', '+91 9876543203', 2, 'Active'),
(4, 'TCH301', 'Nikola', 'Tesla', 'tesla@college.edu', '+91 9876543204', 3, 'Active');

-- 4. Sample Subjects
INSERT IGNORE INTO subjects (id, subject_code, subject_name, description, department_id, status) VALUES
(1, 'CS101', 'Data Structures and Algorithms', 'Core computer science fundamentals', 1, 'Active'),
(2, 'CS102', 'Database Management Systems', 'Relational database architecture & SQL', 1, 'Active'),
(3, 'CS103', 'Operating Systems', 'Process scheduling and memory management', 1, 'Active'),
(4, 'IT201', 'Web Technologies', 'Modern full-stack web development', 2, 'Active'),
(5, 'IT202', 'Computer Networks', 'OSI model, TCP/IP, and network routing', 2, 'Active');

-- 5. Sample Students
INSERT IGNORE INTO students (id, student_code, roll_number, first_name, last_name, email, phone, gender, date_of_birth, address, department_id, section_id, admission_date, status) VALUES
(1, 'STU202601', '101', 'John', 'Doe', 'john.doe@college.edu', '+91 9876500001', 'Male', '2004-05-15', '123 University Campus Road', 1, 1, '2026-08-01', 'Active'),
(2, 'STU202602', '102', 'Jane', 'Smith', 'jane.smith@college.edu', '+91 9876500002', 'Female', '2004-07-22', '456 College Lane', 1, 1, '2026-08-01', 'Active'),
(3, 'STU202603', '103', 'Michael', 'Brown', 'michael.b@college.edu', '+91 9876500003', 'Male', '2003-11-09', '789 Academic Avenue', 1, 1, '2026-08-01', 'Active'),
(4, 'STU202604', '104', 'Emily', 'Davis', 'emily.d@college.edu', '+91 9876500004', 'Female', '2004-02-18', '101 Student Colony', 1, 1, '2026-08-01', 'Active'),
(5, 'STU202605', '105', 'Robert', 'Wilson', 'robert.w@college.edu', '+91 9876500005', 'Male', '2004-09-30', '202 Scholar Street', 1, 1, '2026-08-01', 'Active'),
(6, 'STU202606', '201', 'Sophia', 'Taylor', 'sophia.t@college.edu', '+91 9876500006', 'Female', '2004-04-12', '303 Knowledge Park', 1, 2, '2026-08-01', 'Active'),
(7, 'STU202607', '202', 'David', 'Anderson', 'david.a@college.edu', '+91 9876500007', 'Male', '2003-12-05', '404 Science Drive', 1, 2, '2026-08-01', 'Active'),
(8, 'STU202608', '301', 'Olivia', 'Thomas', 'olivia.t@college.edu', '+91 9876500008', 'Female', '2004-06-25', '505 Tech Boulevard', 2, 3, '2026-08-01', 'Active');

-- 6. Sample Student-Subject Assignments
INSERT IGNORE INTO student_subjects (student_id, subject_id, status) VALUES
(1, 1, 'Active'), (1, 2, 'Active'), (1, 3, 'Active'),
(2, 1, 'Active'), (2, 2, 'Active'), (2, 3, 'Active'),
(3, 1, 'Active'), (3, 2, 'Active'), (3, 3, 'Active'),
(4, 1, 'Active'), (4, 2, 'Active'), (4, 3, 'Active'),
(5, 1, 'Active'), (5, 2, 'Active'), (5, 3, 'Active'),
(6, 1, 'Active'), (6, 2, 'Active'),
(7, 1, 'Active'), (7, 2, 'Active'),
(8, 4, 'Active'), (8, 5, 'Active');

-- 7. Sample Teacher-Subject Assignments
INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id, section_id, status) VALUES
(1, 1, 1, 'Active'),
(1, 1, 2, 'Active'),
(2, 2, 1, 'Active'),
(2, 3, 1, 'Active'),
(3, 4, 3, 'Active'),
(3, 5, 3, 'Active');
