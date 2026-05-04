// BatangPortal — Main JavaScript

document.addEventListener('DOMContentLoaded', function () {

    // ============================================================
    // Highlight active sidebar link
    // ============================================================
    const currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar-link').forEach(link => {
        if (link.getAttribute('href') && currentPath.endsWith(link.getAttribute('href').split('/').pop())) {
            link.classList.add('active');
        }
    });

    // ============================================================
    // Auto-dismiss alerts after 5 seconds
    // ============================================================
    document.querySelectorAll('.alert.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // ============================================================
    // Confirm delete dialogs
    // ============================================================
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            const msg = this.dataset.confirm || 'Are you sure?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // ============================================================
    // File input label update
    // ============================================================
    document.querySelectorAll('.custom-file-input').forEach(input => {
        input.addEventListener('change', function () {
            const label = this.nextElementSibling;
            if (label) label.textContent = this.files[0]?.name || 'Choose file';
        });
    });

    // ============================================================
    // Search filter for tables
    // ============================================================
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            document.querySelectorAll('table tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        });
    }

    // ============================================================
    // Print button
    // ============================================================
    document.querySelectorAll('[data-print]').forEach(btn => {
        btn.addEventListener('click', () => window.print());
    });

    // ============================================================
    // LRN formatter (12 digits)
    // ============================================================
    const lrnInput = document.getElementById('lrn');
    if (lrnInput) {
        lrnInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 12);
        });
    }

    // ============================================================
    // Grade input validation (60-100 or blank)
    // ============================================================
    document.querySelectorAll('.grade-input').forEach(input => {
        input.addEventListener('blur', function () {
            const val = parseFloat(this.value);
            if (this.value !== '' && (isNaN(val) || val < 60 || val > 100)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    });

    // ============================================================
    // Tooltip initialization
    // ============================================================
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // ============================================================
    // Sidebar toggle (mobile)
    // ============================================================
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('d-none');
        });
    }
});
