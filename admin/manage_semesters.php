<?php
// ============================================================
// manage_semesters.php — School Year / Semester Management
// ============================================================

require_once '../middleware/AdminMiddleware.php';
require_once '../helpers/Auth.php';

AdminMiddleware::handle();

$db      = DB::getInstance();
$success = '';
$error   = '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {

        switch ($action) {

            // ── Create Semester ───────────────────────────────
            case 'create':
                $name       = trim($_POST['name']        ?? '');
                $schoolYear = trim($_POST['school_year'] ?? '');
                $startDate  = $_POST['start_date']       ?? null;
                $endDate    = $_POST['end_date']         ?? null;
                $isActive   = isset($_POST['is_active']) ? 1 : 0;

                if (empty($name) || empty($schoolYear)) {
                    $error = 'Semester name and school year are required.';
                } else {
                    // If setting as active, deactivate others
                    if ($isActive) {
                        $db->query("UPDATE semesters SET is_active = 0");
                    }

                    $stmt = $db->prepare(
                        "INSERT INTO semesters
                            (name, school_year, start_date, end_date, is_active)
                         VALUES
                            (:name, :school_year, :start_date, :end_date, :is_active)"
                    );
                    $result = $stmt->execute([
                        ':name'        => $name,
                        ':school_year' => $schoolYear,
                        ':start_date'  => $startDate ?: null,
                        ':end_date'    => $endDate   ?: null,
                        ':is_active'   => $isActive,
                    ]);

                    if ($result) {
                        Auth::logAction("Created semester: {$name} {$schoolYear}");
                        $success = 'Semester created successfully.';
                    } else {
                        $error = 'Failed to create semester.';
                    }
                }
                break;

            // ── Update Semester ───────────────────────────────
            case 'update':
                $id         = (int) ($_POST['semester_id'] ?? 0);
                $name       = trim($_POST['name']          ?? '');
                $schoolYear = trim($_POST['school_year']   ?? '');
                $startDate  = $_POST['start_date']         ?? null;
                $endDate    = $_POST['end_date']           ?? null;
                $isActive   = isset($_POST['is_active'])   ? 1 : 0;

                if (empty($name) || empty($schoolYear)) {
                    $error = 'Semester name and school year are required.';
                } else {
                    if ($isActive) {
                        $db->query("UPDATE semesters SET is_active = 0");
                    }

                    $stmt = $db->prepare(
                        "UPDATE semesters
                         SET    name        = :name,
                                school_year = :school_year,
                                start_date  = :start_date,
                                end_date    = :end_date,
                                is_active   = :is_active
                         WHERE  id = :id"
                    );
                    $result = $stmt->execute([
                        ':name'        => $name,
                        ':school_year' => $schoolYear,
                        ':start_date'  => $startDate ?: null,
                        ':end_date'    => $endDate   ?: null,
                        ':is_active'   => $isActive,
                        ':id'          => $id,
                    ]);

                    if ($result) {
                        Auth::logAction("Updated semester ID: {$id}");
                        $success = 'Semester updated successfully.';
                    } else {
                        $error = 'Failed to update semester.';
                    }
                }
                break;

            // ── Set Active ────────────────────────────────────
            case 'set_active':
                $id = (int) ($_POST['semester_id'] ?? 0);
                $db->query("UPDATE semesters SET is_active = 0");
                $stmt = $db->prepare(
                    "UPDATE semesters SET is_active = 1 WHERE id = :id"
                );
                if ($stmt->execute([':id' => $id])) {
                    Auth::logAction("Set active semester ID: {$id}");
                    $success = 'Active semester updated.';
                } else {
                    $error = 'Failed to set active semester.';
                }
                break;

            // ── Delete Semester ───────────────────────────────
            case 'delete':
                $id = (int) ($_POST['semester_id'] ?? 0);

                // Check if has enrollments
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM enrollments WHERE semester_id = :id"
                );
                $stmt->execute([':id' => $id]);

                if ((int) $stmt->fetchColumn() > 0) {
                    $error = 'Cannot delete semester with existing enrollments.';
                } else {
                    $stmt = $db->prepare(
                        "DELETE FROM semesters WHERE id = :id"
                    );
                    if ($stmt->execute([':id' => $id])) {
                        Auth::logAction("Deleted semester ID: {$id}");
                        $success = 'Semester deleted successfully.';
                    } else {
                        $error = 'Failed to delete semester.';
                    }
                }
                break;
        }
    }
}

// ── Fetch Semesters ───────────────────────────────────────────
$semesters = $db->query(
    "SELECT s.*,
            COUNT(DISTINCT e.student_id) AS enrolled_students,
            COUNT(DISTINCT e.subject_id) AS active_subjects
     FROM   semesters s
     LEFT JOIN enrollments e ON e.semester_id = s.id
     GROUP  BY s.id
     ORDER  BY s.school_year DESC, s.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = Auth::generateCsrf();
$pageTitle = 'Manage Semesters';

include '../shared/header.php';
?>

<div id="flash-messages"
     data-success="<?= htmlspecialchars($success) ?>"
     data-error="<?= htmlspecialchars($error) ?>">
</div>

<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-mobile-menu">
        <i class="fas fa-bars"></i>
      </button>
      <div>
        <div class="topbar-title">Manage Semesters</div>
        <div class="topbar-subtitle">School year & semester settings</div>
      </div>
    </div>
    <div class="topbar-right">
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
          <div class="breadcrumb-item active">Semesters</div>
        </div>
        <h1>Manage Semesters</h1>
        <p><?= count($semesters) ?> semester(s) on record</p>
      </div>
      <div class="page-header-right">
        <button class="btn btn-primary"
                onclick="Modal.open('create-semester-modal')">
          <i class="fas fa-plus"></i> Add Semester
        </button>
      </div>
    </div>

    <!-- Semesters Grid -->
    <div class="grid-auto-2 stagger-children">
      <?php foreach ($semesters as $sem): ?>
        <div class="glass-card">
          <div class="glass-card-body">
            <div class="d-flex align-center justify-between mb-4">
              <div class="d-flex align-center gap-3">
                <div class="stat-icon <?= $sem['is_active'] ? 'primary' : 'muted' ?>"
                     style="<?= !$sem['is_active'] ? 'background:rgba(255,255,255,0.06);color:var(--text-muted)' : '' ?>">
                  <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                  <div class="font-bold text-primary">
                    <?= htmlspecialchars($sem['name']) ?>
                  </div>
                  <div class="text-xs text-muted">
                    <?= htmlspecialchars($sem['school_year']) ?>
                  </div>
                </div>
              </div>
              <?php if ($sem['is_active']): ?>
                <span class="badge badge-success">
                  <i class="fas fa-circle" style="font-size:6px"></i>
                  Active
                </span>
              <?php else: ?>
                <span class="badge badge-muted">Inactive</span>
              <?php endif; ?>
            </div>

            <!-- Stats Row -->
            <div class="d-flex gap-4 mb-4">
              <div class="text-center">
                <div class="font-bold text-xl text-primary">
                  <?= $sem['enrolled_students'] ?>
                </div>
                <div class="text-xs text-muted">Students</div>
              </div>
              <div class="text-center">
                <div class="font-bold text-xl text-primary">
                  <?= $sem['active_subjects'] ?>
                </div>
                <div class="text-xs text-muted">Subjects</div>
              </div>
              <?php if ($sem['start_date']): ?>
                <div>
                  <div class="text-xs text-muted">Period</div>
                  <div class="text-sm">
                    <?= date('M d, Y', strtotime($sem['start_date'])) ?>
                    <?php if ($sem['end_date']): ?>
                      — <?= date('M d, Y', strtotime($sem['end_date'])) ?>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2">
              <?php if (!$sem['is_active']): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"
                         value="set_active">
                  <input type="hidden" name="csrf_token"
                         value="<?= $csrfToken ?>">
                  <input type="hidden" name="semester_id"
                         value="<?= $sem['id'] ?>">
                  <button type="submit" class="btn btn-success btn-sm">
                    <i class="fas fa-check"></i> Set Active
                  </button>
                </form>
              <?php endif; ?>
              <button class="btn btn-secondary btn-sm"
                      onclick="openEditSemester(<?= htmlspecialchars(json_encode($sem)) ?>)">
                <i class="fas fa-edit"></i> Edit
              </button>
              <?php if (!$sem['is_active']): ?>
                <button class="btn btn-ghost-danger btn-sm"
                        onclick="confirmDeleteSemester(<?= $sem['id'] ?>, '<?= htmlspecialchars($sem['name']) ?>')">
                  <i class="fas fa-trash"></i>
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (empty($semesters)): ?>
        <div class="glass-card" style="grid-column:1/-1">
          <div class="glass-card-body">
            <div class="empty-state">
              <div class="empty-state-icon">📅</div>
              <h3>No Semesters Yet</h3>
              <p>Create your first semester to get started.</p>
              <button class="btn btn-primary mt-4"
                      onclick="Modal.open('create-semester-modal')">
                <i class="fas fa-plus"></i> Add Semester
              </button>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ── Create Semester Modal ──────────────────────────────────── -->
<div class="modal-overlay" id="create-semester-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">
        <div class="modal-icon">
          <i class="fas fa-plus"></i>
        </div>
        Add Semester
      </div>
      <button class="modal-close" data-modal-close>
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"     value="create">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">
              Semester Name <span class="required">*</span>
            </label>
            <select name="name" class="form-control" required>
              <option value="">— Select —</option>
              <option value="1st Semester">1st Semester</option>
              <option value="2nd Semester">2nd Semester</option>
              <option value="Summer">Summer</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">
              School Year <span class="required">*</span>
            </label>
            <input type="text"
                   name="school_year"
                   class="form-control"
                   placeholder="e.g. 2024-2025"
                   pattern="\d{4}-\d{4}"
                   required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="checkbox-wrap">
            <input type="checkbox" name="is_active" value="1">
            <span class="checkbox-custom"></span>
            <span class="checkbox-label">
              Set as active semester
            </span>
          </label>
          <span class="form-hint">
            Only one semester can be active at a time.
          </span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>
          Cancel
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Create Semester
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Semester Modal ────────────────────────────────────── -->
<div class="modal-overlay" id="edit-semester-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">
        <div class="modal-icon"><i class="fas fa-edit"></i></div>
        Edit Semester
      </div>
      <button class="modal-close" data-modal-close>
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"      value="update">
        <input type="hidden" name="csrf_token"  value="<?= $csrfToken ?>">
        <input type="hidden" name="semester_id" id="edit-sem-id">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Semester Name</label>
            <select name="name" id="edit-sem-name" class="form-control">
              <option value="1st Semester">1st Semester</option>
              <option value="2nd Semester">2nd Semester</option>
              <option value="Summer">Summer</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">School Year</label>
            <input type="text"
                   name="school_year"
                   id="edit-sem-year"
                   class="form-control"
                   pattern="\d{4}-\d{4}">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <input type="date"
                   name="start_date"
                   id="edit-sem-start"
                   class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">End Date</label>
            <input type="date"
                   name="end_date"
                   id="edit-sem-end"
                   class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="checkbox-wrap">
            <input type="checkbox"
                   name="is_active"
                   id="edit-sem-active"
                   value="1">
            <span class="checkbox-custom"></span>
            <span class="checkbox-label">Set as active semester</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>
          Cancel
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Update Semester
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form -->
<form method="POST" id="delete-sem-form" style="display:none">
  <input type="hidden" name="action"      value="delete">
  <input type="hidden" name="csrf_token"  value="<?= $csrfToken ?>">
  <input type="hidden" name="semester_id" id="delete-sem-id">
</form>

<script>
function openEditSemester(sem) {
  document.getElementById('edit-sem-id').value    = sem.id;
  document.getElementById('edit-sem-name').value  = sem.name;
  document.getElementById('edit-sem-year').value  = sem.school_year;
  document.getElementById('edit-sem-start').value = sem.start_date || '';
  document.getElementById('edit-sem-end').value   = sem.end_date   || '';
  document.getElementById('edit-sem-active').checked = sem.is_active == 1;
  Modal.open('edit-semester-modal');
}

function confirmDeleteSemester(id, name) {
  Modal.confirm({
    title:   'Delete Semester',
    message: `Delete <strong>${name}</strong>? This cannot be undone.`,
    confirm: 'Delete',
    type:    'danger',
    onConfirm() {
      document.getElementById('delete-sem-id').value = id;
      document.getElementById('delete-sem-form').submit();
    }
  });
}
</script>

<?php include '../shared/footer.php'; ?>