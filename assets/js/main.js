/**
 * Main Application JavaScript
 * Student Attendance Management System
 * Pure Vanilla JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Sidebar Toggle for Mobile & Responsive
    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (toggleSidebarBtn && sidebar) {
        toggleSidebarBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('show');
            }
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        });
    }

    // 2. Auto-dismiss Flash Alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                alert.style.opacity = '0';
                setTimeout(function () { alert.remove(); }, 250);
            });
        }
        setTimeout(function () {
            if (alert && alert.parentElement) {
                alert.style.transition = 'opacity 0.4s ease';
                alert.style.opacity = '0';
                setTimeout(function () { alert.remove(); }, 400);
            }
        }, 6000);
    });

    // 3. User Menu Dropdown Toggle
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });
        document.addEventListener('click', function () {
            userDropdown.classList.remove('active');
        });
    }

    // 4. Modal Backdrop Close Handlers
    document.querySelectorAll('.modal-backdrop').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    // 5. Dependent Department -> Sections & Subjects Dropdowns
    setupDependentDropdowns();
});

/**
 * Open a modal by ID
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Close a modal by ID
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

/**
 * Confirm delete modal action
 */
function confirmDelete(deleteUrl, itemName = 'this item') {
    const modal = document.getElementById('deleteConfirmModal');
    const deleteLink = document.getElementById('confirmDeleteLink');
    const itemSpan = document.getElementById('deleteItemName');

    if (modal && deleteLink) {
        deleteLink.setAttribute('href', deleteUrl);
        if (itemSpan) {
            itemSpan.textContent = itemName;
        }
        openModal('deleteConfirmModal');
    } else {
        if (confirm('Are you sure you want to delete ' + itemName + '? This action cannot be undone.')) {
            window.location.href = deleteUrl;
        }
    }
}

/**
 * Setup dynamic AJAX loading for Department -> Sections and Department -> Subjects
 */
function setupDependentDropdowns() {
    const deptSelect = document.getElementById('department_select');
    const sectionSelect = document.getElementById('section_select');
    const subjectSelect = document.getElementById('subject_select');

    if (deptSelect) {
        deptSelect.addEventListener('change', function () {
            const deptId = this.value;

            // Fetch Sections
            if (sectionSelect) {
                sectionSelect.innerHTML = '<option value="">Loading sections...</option>';
                if (!deptId) {
                    sectionSelect.innerHTML = '<option value="">-- Select Department First --</option>';
                } else {
                    fetch('../admin/api.php?action=get_sections&department_id=' + encodeURIComponent(deptId))
                        .then(response => response.json())
                        .then(data => {
                            let options = '<option value="">-- Select Section --</option>';
                            if (data.success && data.data.length > 0) {
                                data.data.forEach(sec => {
                                    options += `<option value="${sec.id}">${escapeHtml(sec.section_name)}</option>`;
                                });
                            } else {
                                options = '<option value="">No sections found for this department</option>';
                            }
                            sectionSelect.innerHTML = options;
                        })
                        .catch(err => {
                            console.error('Error fetching sections:', err);
                            sectionSelect.innerHTML = '<option value="">Error loading sections</option>';
                        });
                }
            }

            // Fetch Subjects
            if (subjectSelect) {
                subjectSelect.innerHTML = '<option value="">Loading subjects...</option>';
                if (!deptId) {
                    subjectSelect.innerHTML = '<option value="">-- Select Department First --</option>';
                } else {
                    fetch('../admin/api.php?action=get_subjects&department_id=' + encodeURIComponent(deptId))
                        .then(response => response.json())
                        .then(data => {
                            let options = '<option value="">-- Select Subject --</option>';
                            if (data.success && data.data.length > 0) {
                                data.data.forEach(sub => {
                                    options += `<option value="${sub.id}">${escapeHtml(sub.subject_code)} - ${escapeHtml(sub.subject_name)}</option>`;
                                });
                            } else {
                                options = '<option value="">No subjects found for this department</option>';
                            }
                            subjectSelect.innerHTML = options;
                        })
                        .catch(err => {
                            console.error('Error fetching subjects:', err);
                            subjectSelect.innerHTML = '<option value="">Error loading subjects</option>';
                        });
                }
            }
        });
    }
}

/**
 * Escape HTML in string
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function (m) { return map[m]; });
}
