<?php
// ============================================================
// view_grades.php — View & Lock All Grades (Admin)
// ============================================================

require_once '../middleware/AdminMiddleware.php';
require_once '../models/Grade.php';
require_once '../helpers/Auth.php';

AdminMiddleware::handle();

$db         = DB::getInstance();
$gradeModel = new Grade();
$success    = '';
$error      = '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        switch ($action) {
            case 'toggle_lock':
                $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);
                if ($gradeModel->toggleLock($enrollmentId)) {
                    Auth::logAction("Toggled grade lock for enrollment {$enrollmentId}");
                    $success = 'Grade lock status updated.';
                } else {
                    $error = 'Failed to update lock status.';
                }
                break;

            case 'lock_all':
                $subjectId  = (int) ($_POST['subject_id']  ?? 0);
                $semesterId = (int) ($_POST['semester_id'] ?? 0);
                if ($gradeModel->lockAll($subjectId, $semesterId)) {
                    Auth::logAction(
                        "Locked all grades for subject {$subjectId} sem {$semesterId}"
                    );
                    $success = 'All grades locked successfully.';
                } else {
                    $error = 'Failed to lock grades.';
                }
                break;
        }
    }
}

// ── Filters ───────────────────────────────────────────────────
$activeSemester   = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$filterSemesterId = (int) ($_GET['semester_id']
    ?? $activeSemester['id']
    ?? 0);

$filterSubjectId  = (int) ($_GET['subject_id']  ?? 0);
$filterSearch     = trim($_GET['search']         ?? '');

// Semesters
$semesters = $db->query(
    "SELECT * FROM semesters ORDER BY school_year DESC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Subjects
$subjects = $db->query(
    "SELECT id, code, name FROM subjects WHERE is_active = 1 ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

// Grades
$grades = $gradeModel->getAll([
    'semester_id' => $filterSemesterId ?: null,
    'subject_id'  => $filterSubjectId  ?: null,
    'search'      => $filterSearch,
]);

// Summary
$summary = $filterSemesterId
    ? $gradeModel->getSummary($filterSemesterId)
    : [];

$csrfToken = Auth::generateCsrf();
$pageTitle = 'View Grades';

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
        <div class="topbar-title">View Grades</div>
        <div class="topbar-subtitle">Monitor and lock all grades</div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-secondary btn-sm"
              onclick="CSVExport.fromTable('grades-table', 'grades_export.csv')">
        <i class="fas fa-download"></i> Export CSV
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
          <div class="breadcrumb-item active">View Grades</div>
        </div>
        <h1>Grade Records</h1>
        <p><?= count($grades) ?> grade records</p>
      </div>
      <?php if ($filterSubjectId && $filterSemesterId): ?>
        <div class="page-header-right">
          <button class="btn btn-warning"
                  onclick="confirmLockAll()">
            <i class="fas fa-lock"></i> Lock All Grades
          </button>
        </div>
      <?php endif; ?>
    </div>

    <!-- Summary Stats -->
    <?php if (!empty($summary)): ?>
      <div class="stats-grid mb-6">
        <div class="stat-card success">
          <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Passed</div>
            <div class="stat-value">
              <?= number_format($summary['passed_count'] ?? 0) ?>
            </div>
          </div>
        </div>
        <div class="stat-card danger">
          <div class="stat-icon danger">
            <i class="fas fa-times-circle"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Failed</div>
            <div class="stat-value">
              <?= number_format($summary['failed_count'] ?? 0) ?>
            </div>
          </div>
        </div>
        <div class="stat-card primary">
          <div class="stat-icon primary">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Average Grade</div>
            <div class="stat-value">
              <?= number_format($summary['average_grade'] ?? 0, 2) ?>
            </div>
          </div>
        </div>
        <div class="stat-card warning">
          <div class="stat-icon warning">
            <i class="fas fa-lock"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Locked</div>
            <div class="stat-value">
              <?= number_format($summary['locked_count'] ?? 0) ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="glass-card mb-5">
      <div class="glass-card-body">
        <form method="GET" class="d-flex gap-4 align-center flex-wrap">
          <div class="form-group" style="flex:1;min-width:180px;margin-bottom:0">
            <label class="form-label">Semester</label>
            <select name="semester_id"
                    class="form-control"
                    onchange="this.form.submit()">
              <option value="">All Semesters</option>
              <?php foreach ($semesters as $sem): ?>
                <option value="<?= $sem['id'] ?>"
                  <?= $filterSemesterId == $sem['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sem['name']) ?>
                  (<?= htmlspecialchars($sem['school_year']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:1;min-width:180px;margin-bottom:0">
            <label class="form-label">Subject</label>
            <select name="subject_id"
                    class="form-control"
                    onchange="this.form.submit()">
              <option value="">All Subjects</option>
              <?php foreach ($subjects as $sub): ?>
                <option value="<?= $sub['id'] ?>"
                  <?= $filterSubjectId == $sub['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sub['code']) ?>
                  — <?= htmlspecialchars($sub['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:1;min-width:180px;margin-bottom:0">
            <label class="form-label">Search</label>
            <div class="search-input-wrap">
              <i class="fas fa-search search-icon"></i>
              <input type="text"
                     name="search"
                     class="form-control"
                     placeholder="Student name or ID..."
                     value="<?= htmlspecialchars($filterSearch) ?>">
            </div>
          </div>
          <div style="margin-top:20px">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fas fa-filter"></i> Filter
            </button>
            <a href="view_grades.php" class="btn btn-secondary btn-sm ml-2">
              Clear
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Grades Table -->
    <div class="glass-card">
      <div class="glass-card-header">
        <h3><i class="fas fa-graduation-cap"></i> Grade Records</h3>
        <span class="badge badge-primary">
          <?= count($grades) ?> records
        </span>
      </div>

      <div class="table-wrapper">
        <table class="data-table" id="grades-table">
          <thead>
            <tr>
              <th>Student</th>
              <th>Subject</th>
              <th>Semester</th>
              <th class="sortable">Prelim</th>
              <th class="sortable">Midterm</th>
              <th class="sortable">Pre-Final</th>
              <th class="sortable">Final Exam</th>
              <th class="sortable">Final Grade</th>
              <th>Standing</th>
              <th>Lock</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($grades)): ?>
              <tr>
                <td colspan="10">
                  <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <h3>No Grades Found</h3>
                    <p>No grade records match your filters.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($grades as $grade): ?>
                <tr>
                  <td>
                    <div class="user-cell">
                      <div class="avatar avatar-sm avatar-primary">
                        <?= strtoupper(
                          substr($grade['student_fname'], 0, 1)
                          . substr($grade['student_lname'], 0, 1)
                        ) ?>
                      </div>
                      <div class="user-cell-info">
                        <div class="name">
                          <?= htmlspecialchars(
                            $grade['student_lname'] . ', ' . $grade['student_fname']
                          ) ?>
                        </div>
                        <div class="sub">
                          <?= htmlspecialchars($grade['student_number']) ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <strong>
                      <?= htmlspecialchars($grade['subject_code']) ?>
                    </strong>
                    <div class="text-xs text-muted">
                      <?= htmlspecialchars($grade['subject_name']) ?>
                    </div>
                  </td>
                  <td>
                    <?= htmlspecialchars($grade['semester_name']) ?>
                    <div class="text-xs text-muted">
                      <?= htmlspecialchars($grade['school_year']) ?>
                    </div>
                  </td>
                  <td><?= $grade['prelim']     ?? '—' ?></td>
                  <td><?= $grade['midterm']    ?? '—' ?></td>
                  <td><?= $grade['prefinal']   ?? '—' ?></td>
                  <td><?= $grade['final_exam'] ?? '—' ?></td>
                  <td>
                    <?php if ($grade['final_grade'] !== null): ?>
                      <span class="grade-score"
                            data-score="<?= $grade['final_grade'] ?>">
                        <?= number_format($grade['final_grade'], 2) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                      $standing = $grade['standing'] ?? '';
                      $badgeMap = [
                        'Excellent'         => 'badge-excellent',
                        'Good'              => 'badge-good',
                        'Satisfactory'      => 'badge-good',
                        'Fair'              => 'badge-average',
                        'Needs Improvement' => 'badge-average',
                        'Failed'            => 'badge-failed',
                      ];
                      $badgeClass = $badgeMap[$standing] ?? 'badge-muted';
                    ?>
                    <span class="badge <?= $badgeClass ?>">
                      <?= htmlspecialchars($standing ?: '—') ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($grade['final_grade'] !== null): ?>
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="action"
                               value="toggle_lock">
                        <input type="hidden" name="csrf_token"
                               value="<?= $csrfToken ?>">
                        <input type="hidden" name="enrollment_id"
                               value="<?= $grade['enrollment_id'] ?>">
                        <button type="submit"
                                class="btn btn-sm <?= $grade['is_locked']
                                    ? 'btn-warning'
                                    : 'btn-secondary' ?>"
                                data-tooltip="<?= $grade['is_locked']
                                    ? 'Unlock Grade'
                                    : 'Lock Grade' ?>">
                          <i class="fas fa-<?= $grade['is_locked']
                              ? 'lock'
                              : 'lock-open' ?>">
                          </i>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted text-xs">No grade</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Lock All Form -->
<form method="POST" id="lock-all-form" style="display:none">
  <input type="hidden" name="action"      value="lock_all">
  <input type="hidden" name="csrf_token"  value="<?= $csrfToken ?>">
  <input type="hidden" name="subject_id"  value="<?= $filterSubjectId ?>">
  <input type="hidden" name="semester_id" value="<?= $filterSemesterId ?>">
</form>

<script>
function confirmLockAll() {
  Modal.confirm({
    title:   'Lock All Grades',
    message: 'This will lock ALL grades for the selected subject and semester. Teachers will no longer be able to edit them.',
    confirm: 'Lock All',
    type:    'warning',
    onConfirm() {
      document.getElementById('lock-all-form').submit();
    }
  });
}
</script>

<?php include '../shared/footer.php'; ?>