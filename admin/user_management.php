<?php
/* ============================================================
   GradeMS — Admin: User Management
   File: admin/user_management.php
   ============================================================ */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../config/DB.php';

Auth::startSession();
Auth::requireRole('admin');

$pageTitle  = 'User Management';
$activePage = '/admin/user_management.php';

$success = '';
$error   = '';

/* ── HANDLE POST ACTIONS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name  = trim($_POST['name']  ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $role  = $_POST['role']  ?? '';
            $pass  = $_POST['password'] ?? '';

            if (!$name || !$email || !$role || !$pass) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($pass) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif (!in_array($role, ['admin','teacher','student'])) {
                $error = 'Invalid role.';
            } else {
                $exists = DB::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
                if ($exists) {
                    $error = 'An account with that email already exists.';
                } else {
                    // SECURITY: bcrypt hash
                    $hash = Auth::hashPassword($pass);
                    DB::execute(
                        'INSERT INTO users (name, email, password, role, is_active, created_at)
                         VALUES (?, ?, ?, ?, 1, NOW())',
                        [$name, $email, $hash, $role]
                    );
                    Auth::logAction('Account Created', "Created {$role} account: {$email}");
                    $success = "Account for {$name} created successfully.";
                }
            }
        }

        if ($action === 'deactivate') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid && $uid !== (int)$_SESSION['user_id']) {
                DB::execute('UPDATE users SET is_active = 0 WHERE id = ?', [$uid]);
                Auth::logAction('Account Deactivated', "User ID: {$uid}");
                $success = 'Account deactivated.';
            }
        }

        if ($action === 'activate') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid) {
                DB::execute('UPDATE users SET is_active = 1 WHERE id = ?', [$uid]);
                Auth::logAction('Account Activated', "User ID: {$uid}");
                $success = 'Account activated.';
            }
        }

        if ($action === 'delete') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid && $uid !== (int)$_SESSION['user_id']) {
                DB::execute('DELETE FROM users WHERE id = ?', [$uid]);
                Auth::logAction('Account Deleted', "User ID: {$uid}");
                $success = 'Account deleted.';
            }
        }

        if ($action === 'reset_password') {
            $uid  = (int)($_POST['user_id'] ?? 0);
            $pass = $_POST['new_password'] ?? '';
            if ($uid && strlen($pass) >= 8) {
                $hash = Auth::hashPassword($pass);
                DB::execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $uid]);
                Auth::logAction('Password Reset', "User ID: {$uid}");
                $success = 'Password reset successfully.';
            } else {
                $error = 'Password must be at least 8 characters.';
            }
        }
    }
}

/* ── FETCH USERS ── */
$roleFilter   = $_GET['role']   ?? '';
$searchFilter = $_GET['search'] ?? '';

$sql    = 'SELECT * FROM users WHERE 1=1';
$params = [];

if ($roleFilter && in_array($roleFilter, ['admin','teacher','student'])) {
    $sql    .= ' AND role = ?';
    $params[] = $roleFilter;
}
if ($searchFilter) {
    $sql    .= ' AND (name LIKE ? OR email LIKE ?)';
    $params[] = "%{$searchFilter}%";
    $params[] = "%{$searchFilter}%";
}
$sql .= ' ORDER BY created_at DESC';
$users = DB::fetchAll($sql, $params);

include __DIR__ . '/../shared/header.php';
?>

<div class="topbar">
  <div>
    <div class="topbar-title">User Management</div>
    <div class="topbar-subtitle text-secondary">Manage all system accounts</div>
  </div>
  <div class="topbar-actions">
    <button class="btn btn-blue" onclick="showModal('modal-add-user')">+ Add User</button>
  </div>
</div>

<?php if ($success): ?>
  <div style="background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#4ade80;font-size:13.5px;">
    ✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div style="background:rgba(251,113,133,0.1);border:1px solid rgba(251,113,133,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#fb7185;font-size:13.5px;">
    ❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<div class="table-card">
  <div class="table-header">
    <div class="table-title">All Accounts (<?= count($users) ?>)</div>
    <div class="table-actions">
      <form method="GET" style="display:flex;gap:8px">
        <input class="search-input" name="search" placeholder="🔍  Search..." value="<?= htmlspecialchars($searchFilter, ENT_QUOTES, 'UTF-8') ?>">
        <select class="search-input" name="role" style="width:130px" onchange="this.form.submit()">
          <option value="">All Roles</option>
          <option value="admin"   <?= $roleFilter==='admin'   ? 'selected' : '' ?>>Admin</option>
          <option value="teacher" <?= $roleFilter==='teacher' ? 'selected' : '' ?>>Teacher</option>
          <option value="student" <?= $roleFilter==='student' ? 'selected' : '' ?>>Student</option>
        </select>
      </form>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Name</th><th>Email</th><th>Role</th>
        <th>Status</th><th>Created</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-secondary font-mono fs-13"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
          <td>
            <span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>">
              <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td class="text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <!-- Reset password -->
              <button class="btn btn-glass btn-sm btn-icon"
                onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>')"
                title="Reset Password">🔑</button>

              <!-- Deactivate / Activate -->
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <form method="POST" style="display:inline">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action"  value="<?= $u['is_active'] ? 'deactivate' : 'activate' ?>">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn btn-amber btn-sm btn-icon" type="submit"
                    title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                    <?= $u['is_active'] ? '🚫' : '✅' ?>
                  </button>
                </form>

                <!-- Delete -->
                <form method="POST" style="display:inline"
                  onsubmit="return confirm('Delete this account? This cannot be undone.')">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action"  value="delete">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn btn-rose btn-sm btn-icon" type="submit" title="Delete">🗑</button>
                </form>
              <?php else: ?>
                <span class="text-muted fs-12">(you)</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">No users found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="modal-add-user">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-add-user')">✕</button>
    <div class="modal-title">Add New User</div>
    <div class="modal-sub">Create a new account in the system</div>
    <form method="POST">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group form-full">
          <label class="form-label">Full Name</label>
          <input class="form-input" name="name" placeholder="Juan Dela Cruz" required>
        </div>
        <div class="form-group form-full">
          <label class="form-label">Email Address</label>
          <input class="form-input" type="email" name="email" placeholder="user@school.edu" required>
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select class="form-select" name="role" required>
            <option value="">Select role...</option>
            <option value="admin">Admin</option>
            <option value="teacher">Teacher</option>
            <option value="student">Student</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-input" type="password" name="password" placeholder="Min. 8 chars" required minlength="8">
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-glass" onclick="closeModal('modal-add-user')">Cancel</button>
          <button type="submit" class="btn btn-blue">Create Account</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="modal-reset-pass">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-reset-pass')">✕</button>
    <div class="modal-title">Reset Password</div>
    <div class="modal-sub" id="reset-modal-sub">Reset password for user</div>
    <form method="POST">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="reset-user-id">
      <div class="form-grid">
        <div class="form-group form-full">
          <label class="form-label">New Password</label>
          <input class="form-input" type="password" name="new_password" placeholder="Min. 8 characters" required minlength="8">
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-glass" onclick="closeModal('modal-reset-pass')">Cancel</button>
          <button type="submit" class="btn btn-amber">Reset Password</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openResetModal(id, name) {
  document.getElementById('reset-user-id').value = id;
  document.getElementById('reset-modal-sub').textContent = 'Reset password for ' + name;
  showModal('modal-reset-pass');
}
</script>

<?php include __DIR__ . '/../shared/footer.php'; ?>
