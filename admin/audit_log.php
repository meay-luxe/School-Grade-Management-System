<?php
// ============================================================
// audit_log.php — Full System Audit Trail (Admin)
// ============================================================

require_once '../middleware/AdminMiddleware.php';
require_once '../helpers/Auth.php';

AdminMiddleware::handle();

$db = DB::getInstance();

// ── Filters ───────────────────────────────────────────────────
$filterUser   = trim($_GET['user']   ?? '');
$filterAction = trim($_GET['action'] ?? '');
$filterDate   = trim($_GET['date']   ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

// ── Build Query ───────────────────────────────────────────────
$where  = "WHERE 1=1";
$params = [];

if ($filterUser) {
    $where .= " AND (u.username LIKE :user
                OR   CONCAT(
                       COALESCE(s.first_name,''),
                       COALESCE(t.first_name,'')
                     ) LIKE :user)";
    $params[':user'] = '%' . $filterUser . '%';
}

if ($filterAction) {
    $where .= " AND al.action LIKE :action";
    $params[':action'] = '%' . $filterAction . '%';
}

if ($filterDate) {
    $where .= " AND DATE(al.created_at) = :date";
    $params[':date'] = $filterDate;
}

// Total count
$countStmt = $db->prepare(
    "SELECT COUNT(*) FROM audit_logs al
     JOIN users u ON u.id = al.user_id
     LEFT JOIN students s ON s.user_id = u.id
     LEFT JOIN teachers t ON t.user_id = u.id
     {$where}"
);
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();
$totalPages   = (int) ceil($totalRecords / $perPage);

// Logs
$logStmt = $db->prepare(
    "SELECT al.*,
            u.username,
            u.role,
            COALESCE(
              CONCAT(s.first_name, ' ', s.last_name),
              CONCAT(t.first_name, ' ', t.last_name),
              u.username
            ) AS full_name
     FROM   audit_logs al
     JOIN   users u ON u.id = al.user_id
     LEFT JOIN students s ON s.user_id = u.id
     LEFT JOIN teachers t ON t.user_id = u.id
     {$where}
     ORDER  BY al.created_at DESC
     LIMIT  :limit OFFSET :offset"
);
$params[':limit']  = $perPage;
$params[':offset'] = $offset;
$logStmt->execute($params);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Clear Logs Action ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'clear_logs'
    && Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {

    $days = (int) ($_POST['days'] ?? 30);
    $db->prepare(
        "DELETE FROM audit_logs
         WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)"
    )->execute([':days' => $days]);

    Auth::logAction("Cleared audit logs older than {$days} days");
    header('Location: audit_log.php');
    exit();
}

$csrfToken = Auth::getCsrf();
$pageTitle = 'Audit Log';

include '../shared/header.php';
include '../shared/sidebar.php';
?>

<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-mobile-menu">
        <i class="fas fa-bars"></i>
      </button>
      <div>
        <div class="topbar-title">Audit Log</div>
        <div class="topbar-subtitle">
          Full system activity trail
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-secondary btn-sm"
              onclick="CSVExport.fromTable('audit-table','audit_log.csv')">
        <i class="fas fa-download"></i> Export
      </button>
      <button class="btn btn-ghost-danger btn-sm"
              onclick="Modal.open('clear-logs-modal')">
        <i class="fas fa-trash"></i> Clear Old
      </button>
      <div class="dropdown">
        <button class="topbar-btn" id="user-menu-btn">
          <i class="fas fa-user-circle"></i>
        </button>
        <div class="dropdown-menu" id="user-dropdown">
          <a href="../profile.php" class="dropdown-item">
            <i class="fas fa-user"></i> Profile
          </a>
          <div class="dropdown-divider"></div>
          <a href="../auth/logout.php" class="dropdown-item danger">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-content">

    <div class="page-header">
      <div class="page-header-left">
        <div class="breadcrumb">
          <div class="breadcrumb-item">
            <a href="dashboard.php">Dashboard</a>
          </div>
          <span class="breadcrumb-separator">›</span>
          <div class="breadcrumb-item active">Audit Log</div>
        </div>
        <h1>System Audit Log</h1>
        <p><?= number_format($totalRecords) ?> total records</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="glass-card mb-5">
      <div class="glass-card-body">
        <form method="GET" class="d-flex gap-4 align-center flex-wrap">
          <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0">
            <label class="form-label">Search User</label>
            <div class="search-input-wrap">
              <i class="fas fa-search search-icon"></i>
              <input type="text"
                     name="user"
                     class="form-control"
                     placeholder="Username..."
                     value="<?= htmlspecialchars($filterUser) ?>">
            </div>
          </div>
          <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0">
            <label class="form-label">Search Action</label>
            <div class="search-input-wrap">
              <i class="fas fa-search search-icon"></i>
              <input type="text"
                     name="action"
                     class="form-control"
                     placeholder="Action keyword..."
                     value="<?= htmlspecialchars($filterAction) ?>">
            </div>
          </div>
          <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0">
            <label class="form-label">Date</label>
            <input type="date"
                   name="date"
                   class="form-control"
                   value="<?= htmlspecialchars($filterDate) ?>">
          </div>
          <div style="margin-top:20px;display:flex;gap:8px">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fas fa-filter"></i> Filter
            </button>
            <a href="audit_log.php" class="btn btn-secondary btn-sm">
              Clear
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Logs Table -->
    <div class="glass-card">
      <div class="glass-card-header">
        <h3>
          <i class="fas fa-history"></i> Activity Log
        </h3>
        <span class="badge badge-primary">
          Page <?= $page ?> of <?= max(1, $totalPages) ?>
        </span>
      </div>

      <div class="table-wrapper">
        <table class="data-table" id="audit-table">
          <thead>
            <tr>
              <th class="sortable">Date & Time</th>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($logs)): ?>
              <tr>
                <td colspan="5">
                  <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>No Logs Found</h3>
                    <p>No audit records match your filters.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
                <?php
                  $roleColors = [
                    'admin'   => 'badge-primary',
                    'teacher' => 'badge-info',
                    'student' => 'badge-success',
                  ];
                  $roleBadge = $roleColors[$log['role']] ?? 'badge-muted';

                  // Determine action icon
                  $action = strtolower($log['action']);
                  if (str_contains($action, 'login')) {
                      $icon = 'fa-sign-in-alt';
                      $color = 'var(--success)';
                  } elseif (str_contains($action, 'logout')) {
                      $icon = 'fa-sign-out-alt';
                      $color = 'var(--text-muted)';
                  } elseif (str_contains($action, 'delete') || str_contains($action, 'remove')) {
                      $icon = 'fa-trash';
                      $color = 'var(--danger)';
                  } elseif (str_contains($action, 'create') || str_contains($action, 'add')) {
                      $icon = 'fa-plus-circle';
                      $color = 'var(--accent-teal)';
                  } elseif (str_contains($action, 'update') || str_contains($action, 'edit')) {
                      $icon = 'fa-edit';
                      $color = 'var(--warning)';
                  } elseif (str_contains($action, 'lock')) {
                      $icon = 'fa-lock';
                      $color = 'var(--accent-pink)';
                  } else {
                      $icon = 'fa-info-circle';
                      $color = 'var(--info)';
                  }
                ?>
                <tr>
                  <td>
                    <div class="text-sm">
                      <?= date('M d, Y', strtotime($log['created_at'])) ?>
                    </div>
                    <div class="text-xs text-muted">
                      <?= date('h:i:s A', strtotime($log['created_at'])) ?>
                    </div>
                  </td>
                  <td>
                    <div class="user-cell">
                      <div class="avatar avatar-sm avatar-primary">
                        <?= strtoupper(substr($log['username'], 0, 2)) ?>
                      </div>
                      <div class="user-cell-info">
                        <div class="name">
                          <?= htmlspecialchars($log['full_name']) ?>
                        </div>
                        <div class="sub">
                          @<?= htmlspecialchars($log['username']) ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge <?= $roleBadge ?>">
                      <?= ucfirst($log['role']) ?>
                    </span>
                  </td>
                  <td>
                    <span style="color:<?= $color ?>; margin-right:8px">
                      <i class="fas <?= $icon ?>"></i>
                    </span>
                    <?= htmlspecialchars($log['action']) ?>
                  </td>
                  <td>
                    <code style="font-size:0.78rem;color:var(--text-muted)">
                      <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                    </code>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <div class="pagination-info">
            Showing <?= ($offset + 1) ?>–<?= min($offset + $perPage, $totalRecords) ?>
            of <?= number_format($totalRecords) ?> records
          </div>
          <div class="pagination-controls">
            <?php if ($page > 1): ?>
              <a href="?page=<?= $page - 1 ?>&user=<?= urlencode($filterUser) ?>&action=<?= urlencode($filterAction) ?>&date=<?= urlencode($filterDate) ?>"
                 class="page-btn">
                <i class="fas fa-chevron-left"></i>
              </a>
            <?php endif; ?>

            <?php
              $start = max(1, $page - 2);
              $end   = min($totalPages, $page + 2);
              for ($p = $start; $p <= $end; $p++):
            ?>
              <a href="?page=<?= $p ?>&user=<?= urlencode($filterUser) ?>&action=<?= urlencode($filterAction) ?>&date=<?= urlencode($filterDate) ?>"
                 class="page-btn <?= $p === $page ? 'active' : '' ?>">
                <?= $p ?>
              </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
              <a href="?page=<?= $page + 1 ?>&user=<?= urlencode($filterUser) ?>&action=<?= urlencode($filterAction) ?>&date=<?= urlencode($filterDate) ?>"
                 class="page-btn">
                <i class="fas fa-chevron-right"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal-overlay" id="clear-logs-modal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">
        <div class="modal-icon"
             style="background:var(--danger-bg);color:var(--danger)">
          <i class="fas fa-trash"></i>
        </div>
        Clear Old Logs
      </div>
      <button class="modal-close" data-modal-close>
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"     value="clear_logs">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <p class="text-secondary mb-4">
          Delete audit logs older than the selected number of days.
          This action cannot be undone.
        </p>

        <div class="form-group">
          <label class="form-label">Delete logs older than</label>
          <select name="days" class="form-control">
            <option value="30">30 days</option>
            <option value="60">60 days</option>
            <option value="90">90 days</option>
            <option value="180">6 months</option>
            <option value="365">1 year</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>
          Cancel
        </button>
        <button type="submit" class="btn btn-danger">
          <i class="fas fa-trash"></i> Clear Logs
        </button>
      </div>
    </form>
  </div>
</div>

<?php include '../shared/footer.php'; ?>