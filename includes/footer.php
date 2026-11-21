<?php

/**
 * Admin Layout Footer
 * Student Attendance Management System
 */

?>
        </main>

        <!-- App Footer -->
        <footer class="app-footer">
            <div>
                &copy; <?= date('Y') ?> <strong><?= escape(APP_NAME) ?></strong>. All rights reserved.
            </div>
            <div>
                Version <?= escape(APP_VERSION) ?>
            </div>
        </footer>
    </div>
</div>

<!-- Global Delete Confirmation Modal -->
<div class="modal-backdrop" id="deleteConfirmModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" style="color: var(--danger);">Confirm Deletion</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('deleteConfirmModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 14px; margin-bottom: 12px;">Are you sure you want to delete <strong id="deleteItemName">this record</strong>?</p>
            <p style="font-size: 12.5px; color: var(--text-muted); background: var(--danger-light); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--danger-border);">
                ⚠️ <strong>Warning:</strong> This action cannot be reversed. If other records depend on this item, deletion will be blocked safely to preserve data integrity.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteConfirmModal')">Cancel</button>
            <a href="#" id="confirmDeleteLink" class="btn btn-danger">Yes, Delete Record</a>
        </div>
    </div>
</div>

<!-- Pure Vanilla JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>

</body>
</html>
