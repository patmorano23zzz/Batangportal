<?php
$pageTitle = 'User Accounts';
require_once __DIR__ . '/../includes/header.php';
requireRole(['admin']);

$db = getDB();

// Handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $uid      = (int)($_POST['user_id'] ?? 0);
        $username = sanitize($_POST['username'] ?? '');
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $contact  = sanitize($_POST['contact_number'] ?? '');
        $role     = sanitize($_POST['role'] ?? 'parent');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        $errors = [];
        if (empty($username)) $errors[] = 'Username required.';
        if (empty($fullName)) $errors[] = 'Full name required.';

        // Check username uniqueness
        $check = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
        $check->execute([$username, $uid]);
        if ($check->fetch()) $errors[] = 'Username already taken.';

        if (empty($errors)) {
            if ($action === 'add') {
                if (empty($password)) { setFlash('error', 'Password required for new user.'); redirect(APP_URL . '/admin/users.php'); }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, role, full_name, email, contact_number, is_active) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$username, $hash, $role, $fullName, $email, $contact, $isActive]);
                auditLog('ADD_USER', 'users', $db->lastInsertId(), "Added user: $username");
                setFlash('success', 'User account created.');
            } else {
                $sets = "username=?, role=?, full_name=?, email=?, contact_number=?, is_active=?";
                $vals = [$username, $role, $fullName, $email, $contact, $isActive];
                if (!empty($password)) {
                    $sets .= ", password=?";
                    $vals[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $vals[] = $uid;
                $db->prepare("UPDATE users SET $sets WHERE id=?")->execute($vals);
                auditLog('UPDATE_USER', 'users', $uid, "Updated user: $username");
                setFlash('success', 'User account updated.');
            }
        } else {
            setFlash('error', implode(' ', $errors));
        }
        redirect(APP_URL . '/admin/users.php');
    }

    if ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id=? AND id!=?")->execute([$uid, $_SESSION['user_id']]);
        setFlash('success', 'User status updated.');
        redirect(APP_URL . '/admin/users.php');
    }

    // ── Bulk actions ──────────────────────────────────────
    if ($action === 'bulk' && isset($_POST['bulk_action'], $_POST['bulk_ids'])) {
        $ids        = array_map('intval', (array)$_POST['bulk_ids']);
        $bulkAction = sanitize($_POST['bulk_action']);
        // Never allow acting on own account
        $ids = array_filter($ids, fn($id) => $id != $_SESSION['user_id']);
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'activate') {
                $db->prepare("UPDATE users SET is_active=1 WHERE id IN ($placeholders)")->execute($ids);
                setFlash('success', count($ids) . " user(s) activated.");
            } elseif ($bulkAction === 'deactivate') {
                $db->prepare("UPDATE users SET is_active=0 WHERE id IN ($placeholders)")->execute($ids);
                setFlash('success', count($ids) . " user(s) deactivated.");
            }
            auditLog('BULK_USERS', 'users', null, "Action=$bulkAction IDs=" . implode(',', $ids));
        }
        redirect(APP_URL . '/admin/users.php');
    }
}

$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editUser = $stmt->fetch();
}

$users = $db->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-person-gear me-2 text-primary"></i>User Accounts</h1>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Users</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
        <i class="bi bi-person-plus me-1"></i>Add User
    </button>
</div>

<?php showFlash(); ?>

<!-- Bulk Toolbar -->
<div id="bulkToolbar" class="d-none mb-2">
    <div class="card border-primary">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-700 text-primary small"><i class="bi bi-check2-square me-1"></i><span id="bulkCount">0</span> selected</span>
            <form method="POST" data-bulk-form>
                <input type="hidden" name="action" value="bulk">
                <select name="bulk_action" class="form-select form-select-sm d-inline-block w-auto me-1">
                    <option value="">— Action —</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
            </form>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="deselectAll()"><i class="bi bi-x me-1"></i>Clear</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="usersTable">
                <thead>
                    <tr>
                        <th width="36"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <input type="checkbox" class="form-check-input" name="ids[]" value="<?= $u['id'] ?>">
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
                                <span class="fw-600"><?= htmlspecialchars($u['full_name']) ?></span>
                            </div>
                        </td>
                        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                        <td>
                            <?php $roleColors = ['admin'=>'danger','teacher'=>'primary','parent'=>'success']; ?>
                            <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>"><?= ucfirst($u['role']) ?></span>
                        </td>
                        <td class="small"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                        <td class="small"><?= htmlspecialchars($u['contact_number'] ?? '—') ?></td>
                        <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="users.php?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                            data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?">
                                        <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editUser ? 'edit' : 'add' ?>">
                <?php if ($editUser): ?><input type="hidden" name="user_id" value="<?= $editUser['id'] ?>"><?php endif; ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editUser ? 'Edit User' : 'Add New User' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($editUser['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <?= $editUser ? '(leave blank to keep current)' : '<span class="text-danger">*</span>' ?></label>
                        <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="admin" <?= ($editUser['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="teacher" <?= ($editUser['role'] ?? '') == 'teacher' ? 'selected' : '' ?>>Teacher</option>
                            <option value="parent" <?= ($editUser['role'] ?? 'parent') == 'parent' ? 'selected' : '' ?>>Parent/Guardian</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($editUser['contact_number'] ?? '') ?>">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?= ($editUser['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active Account</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><?= $editUser ? 'Update' : 'Create' ?> User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editUser): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('userModal')).show();
});
</script>
<?php endif; ?>

<script>
initBulkSelect({ tableId:'usersTable', checkboxName:'ids[]', counterId:'bulkCount', toolbarId:'bulkToolbar', selectAllId:'selectAll' });
function deselectAll() {
    document.querySelectorAll('#usersTable input[name="ids[]"]').forEach(cb => { cb.checked=false; cb.closest('tr').classList.remove('table-active'); });
    document.getElementById('selectAll').checked=false;
    document.getElementById('bulkToolbar').classList.add('d-none');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
