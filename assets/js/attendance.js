/**
 * Attendance Marking Page Script
 * Student Attendance Management System
 */

document.addEventListener('DOMContentLoaded', function () {
    const markAllPresentBtn = document.getElementById('markAllPresentBtn');
    const markAllAbsentBtn = document.getElementById('markAllAbsentBtn');
    const attendanceForm = document.getElementById('attendanceForm');

    // Recalculate stats whenever any attendance radio button changes
    const radios = document.querySelectorAll('input[type="radio"][name^="attendance["]');
    radios.forEach(radio => {
        radio.addEventListener('change', updateAttendanceCounters);
    });

    if (markAllPresentBtn) {
        markAllPresentBtn.addEventListener('click', function () {
            document.querySelectorAll('input[type="radio"][value="Present"]').forEach(radio => {
                radio.checked = true;
            });
            updateAttendanceCounters();
        });
    }

    if (markAllAbsentBtn) {
        markAllAbsentBtn.addEventListener('click', function () {
            document.querySelectorAll('input[type="radio"][value="Absent"]').forEach(radio => {
                radio.checked = true;
            });
            updateAttendanceCounters();
        });
    }

    // Client-side quick filter / search for students in attendance sheet
    const studentSearchInput = document.getElementById('student_search_input');
    if (studentSearchInput) {
        studentSearchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.attendance-student-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Initial calculation on page load
    updateAttendanceCounters();
});

/**
 * Dynamically count total, present, absent and percentage
 */
function updateAttendanceCounters() {
    const presentRadios = document.querySelectorAll('input[type="radio"][value="Present"]:checked');
    const absentRadios = document.querySelectorAll('input[type="radio"][value="Absent"]:checked');

    const totalStudents = document.querySelectorAll('.attendance-student-row').length;
    const presentCount = presentRadios.length;
    const absentCount = absentRadios.length;

    const totalMarked = presentCount + absentCount;
    const percentage = totalMarked > 0 ? ((presentCount / totalMarked) * 100).toFixed(1) : '0.0';

    const countPresentEl = document.getElementById('count_present');
    const countAbsentEl = document.getElementById('count_absent');
    const countTotalEl = document.getElementById('count_total');
    const countPercentageEl = document.getElementById('count_percentage');

    if (countPresentEl) countPresentEl.textContent = presentCount;
    if (countAbsentEl) countAbsentEl.textContent = absentCount;
    if (countTotalEl) countTotalEl.textContent = totalStudents;
    if (countPercentageEl) countPercentageEl.textContent = percentage + '%';
}
