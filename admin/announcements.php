<?php
$pageTitle = 'Announcements';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin', 'teacher']);

$db = getDB();

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $aid       = (int)($_POST['ann_id'] ?? 0);
        $title     = sanitize($_POST['title'] ?? '');
        $content   = trim($_POST['content'] ?? '');
        $category  = sanitize($_POST['category'] ?? 'general');
        $audience  = sanitize($_POST['target_audience'] ?? 'all');
        $published = isset($_POST['is_published']) ? 1 : 0;

        if (empty($title) || empty($content)) {
            setFlash('error', 'Title and content are required.');
        } else {
            if ($aid) {
                $db->prepare("UPDATE announcements SET title=?,content=?,category=?,target_audience=?,is_published=? WHERE id=?")
                   ->execute([$title, $content, $category, $audience, $published, $aid]);
                setFlash('success', 'Announcement updated.');
            } else {
                $db->prepare("INSERT INTO announcements (title,content,category,target_audience,is_published,posted_by) VALUES (?,?,?,?,?,?)")
                   ->execute([$title, $content, $category, $audience, $published, $_SESSION['user_id']]);
                setFlash('success', 'Announcement posted.');
            }
        }
        redirect(APP_URL . '/admin/announcements.php');
    }

    if ($action === 'delete' && isAdmin()) {
        $db->prepare("DELETE FROM announcements WHERE id=?")->execute([(int)$_POST['ann_id']]);
        setFlash('success', 'Announcement deleted.');
        redirect(APP_URL . '/admin/announcements.php');
    }

    // ── Bulk actions ──────────────────────────────────────
    if ($action === 'bulk' && isset($_POST['bulk_action'], $_POST['bulk_ids'])) {
        $ids        = array_map('intval', (array)$_POST['bulk_ids']);
        $bulkAction = sanitize($_POST['bulk_action']);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'delete' && isAdmin()) {
                $db->prepare("DELETE FROM announcements WHERE id IN ($placeholders)")->execute($ids);
                setFlash('success', count($ids) . " announcement(s) deleted.");
            } elseif ($bulkAction === 'publish') {
                $db->prepare("UPDATE announcements SET is_published=1 WHERE id IN ($placeholders)")->execute($ids);
                setFlash('success', count($ids) . " announcement(s) published.");
            } elseif ($bulkAction === 'unpublish') {
                $db->prepare("UPDATE announcements SET is_published=0 WHERE id IN ($placeholders)")->execute($ids);
                setFlash('success', count($ids) . " announcement(s) unpublished.");
            }
            auditLog('BULK_ANNOUNCEMENTS', 'announcements', null, "Action=$bulkAction IDs=".implode(',',$ids));
        }
        redirect(APP_URL . '/admin/announcements.php');
    }
}

$editAnn = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM announcements WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editAnn = $stmt->fetch();
}

$announcements = $db->query("SELECT a.*, u.full_name as poster FROM announcements a LEFT JOIN users u ON a.posted_by=u.id ORDER BY a.created_at DESC")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-megaphone-fill me-2 text-primary"></i>Announcements</h1>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#annModal">
        <i class="bi bi-plus me-1"></i>New Announcement
    </button>
</div>

<?php showFlash(); ?>

<!-- Bulk Toolbar -->
<div id="bulkToolbar" class="d-none mb-3">
    <div class="card border-primary">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-700 text-primary small"><i class="bi bi-check2-square me-1"></i><span id="bulkCount">0</span> selected</span>
            <form method="POST" data-bulk-form>
                <input type="hidden" name="action" value="bulk">
                <select name="bulk_action" class="form-select form-select-sm d-inline-block w-auto me-1">
                    <option value="">— Action —</option>
                    <option value="publish">Publish</option>
                    <option value="unpublish">Unpublish</option>
                    <?php if (isAdmin()): ?><option value="delete">Delete</option><?php endif; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
            </form>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="deselectAll()"><i class="bi bi-x me-1"></i>Clear</button>
        </div>
    </div>
</div>

<div class="row g-3" id="announcementsGrid">
    <?php if ($announcements): foreach ($announcements as $a): ?>
    <div class="col-md-6">
        <div class="card h-100 ann-card" data-id="<?= $a['id'] ?>" style="cursor:pointer">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input ann-check" name="ann_ids[]"
                               value="<?= $a['id'] ?>" onclick="event.stopPropagation()">
                        <div>
                            <span class="badge bg-info me-1"><?= ucfirst($a['category']) ?></span>
                            <span class="badge bg-secondary"><?= ucfirst($a['target_audience']) ?></span>
                            <?= $a['is_published'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-warning text-dark">Draft</span>' ?>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <a href="announcements.php?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="ann_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this announcement?"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <h6 class="fw-700"><?= htmlspecialchars($a['title']) ?></h6>
                <p class="small text-muted mb-2"><?= nl2br(htmlspecialchars(substr($a['content'], 0, 200))) ?><?= strlen($a['content']) > 200 ? '...' : '' ?></p>
                <div class="small text-muted">
                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($a['poster'] ?? 'System') ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-clock me-1"></i><?= formatDateTime($a['created_at']) ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; else: ?>
    <div class="col-12 text-center text-muted py-5">No announcements yet.</div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="annModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <?php if ($editAnn): ?><input type="hidden" name="ann_id" value="<?= $editAnn['id'] ?>"><?php endif; ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editAnn ? 'Edit Announcement' : 'New Announcement' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($editAnn['title'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="6" required><?= htmlspecialchars($editAnn['content'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <?php foreach (['general','academic','event','health','other'] as $cat): ?>
                                    <option value="<?= $cat ?>" <?= ($editAnn['category'] ?? 'general') == $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Target Audience</label>
                            <select name="target_audience" class="form-select">
                                <?php foreach (['all','parents','teachers','admin'] as $aud): ?>
                                    <option value="<?= $aud ?>" <?= ($editAnn['target_audience'] ?? 'all') == $aud ? 'selected' : '' ?>><?= ucfirst($aud) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_published" class="form-check-input" id="pubCheck" <?= ($editAnn['is_published'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pubCheck">Publish immediately</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editAnn): ?>
<script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('annModal')).show());</script>
<?php endif; ?>

<script>
// Announcements bulk select (card-based, not table-based)
(function () {
    const toolbar  = document.getElementById('bulkToolbar');
    const counter  = document.getElementById('bulkCount');

    function getChecked() {
        return Array.from(document.querySelectorAll('.ann-check:checked'));
    }

    function updateToolbar() {
        const n = getChecked().length;
        if (counter) counter.textContent = n;
        toolbar.classList.toggle('d-none', n === 0);
    }

    // Checkbox change
    document.querySelectorAll('.ann-check').forEach(cb => {
        cb.addEventListener('change', function () {
            this.closest('.ann-card').classList.toggle('border-primary', this.checked);
            this.closest('.ann-card').classList.toggle('shadow', this.checked);
            updateToolbar();
        });
    });

    // Clicking the card body (not buttons/links) toggles checkbox
    document.querySelectorAll('.ann-card').forEach(card => {
        card.addEventListener('click', function (e) {
            if (e.target.closest('a, button, form, input')) return;
            const cb = this.querySelector('.ann-check');
            if (cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); }
        });
    });

    // Bulk form submit — inject selected IDs
    document.querySelectorAll('[data-bulk-form]').forEach(form => {
        form.addEventListener('submit', function (e) {
            const checked = getChecked();
            if (checked.length === 0) { e.preventDefault(); alert('Select at least one announcement.'); return; }

            const action = this.querySelector('[name="bulk_action"]')?.value || this.dataset.confirmLabel || 'perform this action on';
            if (!confirm(`Apply to ${checked.length} selected announcement(s)?`)) { e.preventDefault(); return; }

            this.querySelectorAll('.bulk-id-input').forEach(el => el.remove());
            checked.forEach(cb => {
                const h = document.createElement('input');
                h.type = 'hidden'; h.name = 'bulk_ids[]'; h.value = cb.value; h.className = 'bulk-id-input';
                this.appendChild(h);
            });
        });
    });
})();

function deselectAll() {
    document.querySelectorAll('.ann-check').forEach(cb => {
        cb.checked = false;
        cb.closest('.ann-card').classList.remove('border-primary', 'shadow');
    });
    document.getElementById('bulkToolbar').classList.add('d-none');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
