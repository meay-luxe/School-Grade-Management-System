<?php
// ============================================================
// manage_subjects.php — Subjects + Teacher Assignment
// ============================================================

require_once '../middleware/AdminMiddleware.php';
require_once '../models/Subject.php';
require_once '../models/Teacher.php';
require_once '../helpers/Auth.php';

AdminMiddleware::handle();

$subjectModel = new Subject();
$teacherModel = new Teacher();

$success = '';
$error   = '';

// ── Handle POST Actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF check
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {

        switch ($action) {

            // ── Create Subject ────────────────────────────────
            case 'create':
                $data = [
                    'code'        => trim($_POST['code']        ?? ''),
                    'name'        => trim($_POST['name']        ?? ''),
                    'units'       => (int) ($_POST['units']     ?? 3),
                    'description' => trim($_POST['description'] ?? ''),
                ];

                if (empty($data['code']) || empty($data['name'])) {
                    $error = 'Subject code and name are required.';
                } elseif ($subjectModel->codeExists($data['code'])) {
                    $error = 'Subject code already exists.';
                } else {
                    $result = $subjectModel->create($data);
                    if ($result) {
                        Auth::logAction('Created subject: ' . $data['code']);
                        $success = 'Subject created successfully.';
                    } else {
                        $error = 'Failed to create subject. Please try again.';
                    }
                }
                break;

            // ── Update Subject ────────────────────────────────
            case 'update':
                $id   = (int) ($_POST['subject_id'] ?? 0);
                $data = [
                    'code'        => trim($_POST['code']        ?? ''),
                    'name'        => trim($_POST['name']        ?? ''),
                    'units'       => (int) ($_POST['units']     ?? 3),
                    'description' => trim($_POST['description'] ?? ''),
                    'is_active'   => isset($_POST['is_active']) ? 1 : 0,
                ];

                if (empty($data['code']) || empty($data['name'])) {
                    $error = 'Subject code and name are required.';
                } elseif ($subjectModel->codeExists($data['code'], $id)) {
                    $error = 'Subject code already exists.';
                } else {
                    $result = $subjectModel->update($id, $data);
                    if ($result) {
                        Auth::logAction('Updated subject ID: ' . $id);
                        $success = 'Subject updated successfully.';
                    } else {
                        $error = 'Failed to update subject.';
                    }
                }
                break;

            // ── Delete Subject ────────────────────────────────
            case 'delete':
                $id = (int) ($_POST['subject_id'] ?? 0);
                $result = $subjectModel->delete($id);
                if ($result) {
                    Auth::logAction('Deleted subject ID: ' . $id);
                    $success = 'Subject deleted successfully.';
                } else {
                    $error = 'Cannot delete subject with existing enrollments.';
                }
                break;

            // ── Assign Teacher ────────────────────────────────
            case 'assign_teacher':
                $subjectId  = (int) ($_POST['subject_id']  ?? 0);
                $teacherId  = (int) ($_POST['teacher_id']  ?? 0);
                $semesterId = (int) ($_POST['semester_id'] ?? 0);

                if (!$subjectId || !$teacherId || !$semesterId) {
                    $error = 'Please fill all fields.';
                } else {
                    $result = $subjectModel->assignTeacher(
                        $subjectId,
                        $teacherId,
                        $semesterId
                    );
                    if ($result) {
                        Auth::logAction("Assigned teacher {$teacherId} to subject {$subjectId}");
                        $success = 'Teacher assigned successfully.';
                    } else {
                        $error = 'Failed to assign teacher.';
                    }
                }
                break;

            // ── Remove Teacher ────────────────────────────────
            case 'remove_teacher':
                $subjectId  = (int) ($_POST['subject_id']  ?? 0);
                $teacherId  = (int) ($_POST['teacher_id']  ?? 0);
                $semesterId = (int) ($_POST['semester_id'] ?? 0);

                $result = $subjectModel->removeTeacher(
                    $subjectId,
                    $teacherId,
                    $semesterId
                );
                if ($result) {
                    Auth::logAction("Removed teacher {$teacherId} from subject {$subjectId}");
                    $success = 'Teacher removed from subject.';
                } else {
                    $error = 'Failed to remove teacher.';
                }
                break;
        }
    }
}

// ── Fetch Data ────────────────────────────────────────────────
$subjects  = $subjectModel->getAll();
$teachers  = $teacherModel->getDropdownList();

// Fetch semesters for assignment dropdown
$db        = DB::getInstance();
$semesters = $db->query(
    "SELECT * FROM semesters ORDER BY school_year DESC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = Auth::getCsrf();
$pageTitle = 'Manage Subjects';

include '../shared/header.php';
include '../shared/sidebar.php';
?>

<!-- Flash Messages -->
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
        <div class="topbar-title">Manage Subjects</div>
        <div class="topbar-subtitle">
          Subjects & teacher assignments
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="topbar-btn" data-tooltip="Notifications">
        <i class="fas fa-bell"></i>
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

    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-left">
        <div class="breadcrumb">
          <div class="breadcrumb-item">
            <a href="dashboard.php">Dashboard</a>
          </div>
          <span class="breadcrumb-separator">›</span>
          <div class="breadcrumb-item active">Subjects</div>
        </div>
        <h1>Manage Subjects</h1>
        <p>
          <?= count($subjects) ?> subjects registered
        </p>
      </div>
      <div class="page-header-right">
        <button class="btn btn-primary"
                onclick="Modal.open('create-subject-modal')">
          <i class="fas fa-plus"></i> Add Subject
        </button>
      </div>
    </div>

    <!-- Subjects Table -->
    <div class="glass-card">
      <div class="glass-card-header">
        <h3><i class="fas fa-book"></i> All Subjects</h3>
        <div class="d-flex gap-3">
          <div class="search-input-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text"
                   class="form-control"
                   placeholder="Search subjects..."
                   data-table-search="subjects-table">
          </div>
        </div>
      </div>

      <div class="table-wrapper">
        <table class="data-table" id="subjects-table">
          <thead>
            <tr>
              <th class="sortable">Code</th>
              <th class="sortable">Name</th>
              <th class="sortable">Units</th>
              <th>Description</th>
              <th class="sortable">Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($subjects)): ?>
              <tr>
                <td colspan="6">
                  <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <h3>No Subjects Found</h3>
                    <p>Start by adding your first subject.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($subjects as $subject): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($subject['code']) ?></strong>
                  </td>
                  <td><?= htmlspecialchars($subject['name']) ?></td>
                  <td>
                    <span class="badge badge-info">
                      <?= $subject['units'] ?> units
                    </span>
                  </td>
                  <td class="text-truncate" style="max-width:200px">
                    <?= htmlspecialchars($subject['description'] ?? '—') ?>
                  </td>
                  <td>
                    <?php if ($subject['is_active']): ?>
                      <span class="badge badge-success">Active</span>
                    <?php else: ?>
                      <span class="badge badge-muted">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-2">
                      <!-- Edit -->
                      <button class="btn btn-secondary btn-sm btn-icon"
                              data-tooltip="Edit"
                              onclick="openEditSubject(<?= htmlspecialchars(json_encode($subject)) ?>)">
                        <i class="fas fa-edit"></i>
                      </button>
                      <!-- Assign Teacher -->
                      <button class="btn btn-info btn-sm btn-icon"
                              data-tooltip="Assign Teacher"
                              onclick="openAssignTeacher(<?= $subject['id'] ?>, '<?= htmlspecialchars($subject['name']) ?>')">
                        <i class="fas fa-chalkboard-teacher"></i>
                      </button>
                      <!-- Delete -->
                      <button class="btn btn-ghost-danger btn-sm btn-icon"
                              data-tooltip="Delete"
                              onclick="confirmDeleteSubject(<?= $subject['id'] ?>, '<?= htmlspecialchars($subject['name']) ?>')">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /page-content -->
</div><!-- /main-content -->

<!-- ── Create Subject Modal ───────────────────────────────────── -->
<div class="modal-overlay" id="create-subject-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">
        <div class="modal-icon">
          <i class="fas fa-plus"></i>
        </div>
        Add New Subject
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
              Subject Code <span class="required">*</span>
            </label>
            <input type="text"
                   name="code"
                   class="form-control"
                   placeholder="e.g. CS101"
                   maxlength="20"
                   required>
          </div>
          <div class="form-group">
            <label class="form-label">
              Units <span class="required">*</span>
            </label>
            <select name="units" class="form-control" required>
              <option value="1">1 Unit</option>
              <option value="2">2 Units</option>
              <option value="3" selected>3 Units</option>
              <option value="4">4 Units</option>
              <option value="5">5 Units</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            Subject Name <span class="required">*</span>
          </label>
          <input type="text"
                 name="name"
                 class="form-control"
                 placeholder="e.g. Introduction to Computer Science"
                 required>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description"
                    class="form-control"
                    rows="3"
                    placeholder="Optional description..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button"
                class="btn btn-secondary"
                data-modal-close>
          Cancel
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Save Subject
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Subject Modal ─────────────────────────────────────── -->
<div class="modal-overlay" id="edit-subject-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">
        <div class="modal-icon">
          <i class="fas fa-edit"></i>
        </div>
        Edit Subject
      </div>
      <button class="modal-close" data-modal-close>
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"     value="update">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="subject_id" id="edit-subject-id">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">
              Subject Code <span class="required">*</span>
            </label>
            <input type="text"
                   name="code"
                   id="edit-subject-code"
                   class="form-control"
                   maxlength="20"
                   required>
          </div>
          <div class="form-group">
            <label class="form-label">Units</label>
            <select name="units" id="edit-subject-units" class="form-control">
              <option value="1">1 Unit</option>
              <option value="2">2 Units</option>
              <option value="3">3 Units</option>
              <option value="4">4 Units</option>
              <option value="5">5 Units</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            Subject Name <span class="required">*</span>
          </label>
          <input type="text"
                 name="name"
                 id="edit-subject-name"
                 class="form-control"
                 required>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description"
                    id="edit-subject-desc"
                    class="form-control"
                    rows="3"></textarea>
        </div>

        <div class="form-group">
          <label class="checkbox-wrap">
            <input type="checkbox"
                   name="is_active"
                   id="edit-subject-active"
                   value="1">
            <span class="checkbox-custom"></span>
            <span class="checkbox-label">Active</span>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button"
                class="btn btn-secondary"
                data-modal-close>
          Cancel
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Update Subject
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Assign Teacher Modal ───────────────────────────────────── -->
<div class="modal-overlay" id="assign-teacher-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">
        <div class="modal-icon">
          <i class="fas fa-chalkboard-teacher"></i>
        </div>
        Assign Teacher
      </div>
      <button class="modal-close" data-modal-close>
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"     value="assign_teacher">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="subject_id" id="assign-subject-id">

        <div class="alert alert-info mb-4">
          <span class="alert-icon"><i class="fas fa-info-circle"></i></span>
          <div class="alert-body">
            Assigning teacher to:
            <strong id="assign-subject-name"></strong>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            Semester <span class="required">*</span>
          </label>
          <select name="semester_id" class="form-control" required>
            <option value="">— Select Semester —</option>
            <?php foreach ($semesters as $sem): ?>
              <option value="<?= $sem['id'] ?>">
                <?= htmlspecialchars($sem['name']) ?>
                (<?= htmlspecialchars($sem['school_year']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">
            Teacher <span class="required">*</span>
          </label>
          <select name="teacher_id" class="form-control" required>
            <option value="">— Select Teacher —</option>
            <?php foreach ($teachers as $teacher): ?>
              <option value="<?= $teacher['id'] ?>">
                <?= htmlspecialchars($teacher['full_name']) ?>
                <?php if ($teacher['department']): ?>
                  — <?= htmlspecialchars($teacher['department']) ?>
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button"
                class="btn btn-secondary"
                data-modal-close>
          Cancel
        </button>
        <button type="submit" class="btn btn-success">
          <i class="fas fa-check"></i> Assign Teacher
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="delete-subject-form" style="display:none">
  <input type="hidden" name="action"     value="delete">
  <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
  <input type="hidden" name="subject_id" id="delete-subject-id">
</form>

<script>
function openEditSubject(subject) {
  document.getElementById('edit-subject-id').value    = subject.id;
  document.getElementById('edit-subject-code').value  = subject.code;
  document.getElementById('edit-subject-name').value  = subject.name;
  document.getElementById('edit-subject-units').value = subject.units;
  document.getElementById('edit-subject-desc').value  = subject.description || '';
  document.getElementById('edit-subject-active').checked = subject.is_active == 1;
  Modal.open('edit-subject-modal');
}

function openAssignTeacher(subjectId, subjectName) {
  document.getElementById('assign-subject-id').value   = subjectId;
  document.getElementById('assign-subject-name').textContent = subjectName;
  Modal.open('assign-teacher-modal');
}

function confirmDeleteSubject(id, name) {
  Modal.confirm({
    title:   'Delete Subject',
    message: `Are you sure you want to delete <strong>${name}</strong>? This cannot be undone.`,
    confirm: 'Delete',
    type:    'danger',
    onConfirm() {
      document.getElementById('delete-subject-id').value = id;
      document.getElementById('delete-subject-form').submit();
    }
  });
}
</script>

<?php include '../shared/footer.php'; ?>