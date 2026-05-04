/**
 * BatangPortal — Bulk Actions
 * Reusable bulk-select system for admin tables.
 *
 * Usage: call initBulkSelect(config) after DOM ready.
 */

function initBulkSelect(config) {
    const {
        tableId,        // id of the <table>
        checkboxName,   // name attr of row checkboxes  e.g. 'ids[]'
        counterId,      // id of the element showing selected count
        toolbarId,      // id of the bulk-action toolbar
        selectAllId,    // id of the "select all" header checkbox
    } = config;

    const table     = document.getElementById(tableId);
    const toolbar   = document.getElementById(toolbarId);
    const counter   = document.getElementById(counterId);
    const selectAll = document.getElementById(selectAllId);

    if (!table || !toolbar) return;

    function getCheckboxes() {
        return Array.from(table.querySelectorAll(`input[name="${checkboxName}"]`));
    }

    function getChecked() {
        return getCheckboxes().filter(cb => cb.checked);
    }

    function updateToolbar() {
        const checked = getChecked();
        const count   = checked.length;

        if (counter) counter.textContent = count;

        if (count > 0) {
            toolbar.classList.remove('d-none');
        } else {
            toolbar.classList.add('d-none');
        }

        // Update select-all state
        if (selectAll) {
            const all = getCheckboxes();
            selectAll.indeterminate = count > 0 && count < all.length;
            selectAll.checked       = all.length > 0 && count === all.length;
        }
    }

    // Select-all toggle
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            getCheckboxes().forEach(cb => {
                cb.checked = this.checked;
                cb.closest('tr').classList.toggle('table-active', this.checked);
            });
            updateToolbar();
        });
    }

    // Individual checkbox change
    table.addEventListener('change', function (e) {
        if (e.target.name === checkboxName) {
            e.target.closest('tr').classList.toggle('table-active', e.target.checked);
            updateToolbar();
        }
    });

    // Clicking a row (not on a link/button/checkbox) toggles its checkbox
    table.addEventListener('click', function (e) {
        const row = e.target.closest('tr');
        if (!row) return;
        if (e.target.closest('a, button, input, label, form')) return;
        const cb = row.querySelector(`input[name="${checkboxName}"]`);
        if (cb) {
            cb.checked = !cb.checked;
            row.classList.toggle('table-active', cb.checked);
            updateToolbar();
        }
    });

    // Bulk action form submission — inject selected IDs
    document.querySelectorAll('[data-bulk-form]').forEach(form => {
        form.addEventListener('submit', function (e) {
            const checked = getChecked();
            if (checked.length === 0) {
                e.preventDefault();
                alert('Please select at least one item.');
                return;
            }

            const action = this.querySelector('[name="bulk_action"]')?.value;
            const label  = this.querySelector('[name="bulk_action"] option:checked')?.text
                        || this.dataset.confirmLabel
                        || 'perform this action on';

            if (!confirm(`${label} ${checked.length} selected item(s)?`)) {
                e.preventDefault();
                return;
            }

            // Remove any previously injected hidden inputs
            this.querySelectorAll('.bulk-id-input').forEach(el => el.remove());

            // Inject selected IDs
            checked.forEach(cb => {
                const hidden = document.createElement('input');
                hidden.type  = 'hidden';
                hidden.name  = 'bulk_ids[]';
                hidden.value = cb.value;
                hidden.className = 'bulk-id-input';
                this.appendChild(hidden);
            });
        });
    });

    // Initial state
    updateToolbar();
}
