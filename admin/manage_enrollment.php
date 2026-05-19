<?php
// ============================================================
// manage_enrollment.php — Enroll Students Into Subjects
// ============================================================

require_once '../middleware/AdminMiddleware.php';
require_once '../models/Student.php';
require_once '../helpers/Auth.php';

AdminMiddleware::handle();

$db      = DB::getInstance();
$success = '';
$error   = '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {

        switch ($action) {

            // ── Enroll Single Student ─────────────────────────
            case 'enroll':
                $studentId  = (int) ($_POST['student_id']  ?? 0);
                $subjectId  = (int) ($_POST['subject_id']  ?? 0);
                $semesterId = (int) ($_POST['semester_id'] ?? 0);

                if (!$studentId || !$subjectId || !$semesterId) {
                    $error = 'Please fill all fields.';
                } else {
                    // Check duplicate
                    $stmt = $db->prepare(
                        "SELECT COUNT(*) FROM enrollments
                         WHERE student_id  = :student_id
                         AND   subject_id  = :subject_id
                         AND   semester_id = :semester_id"
                    );
                    $stmt->execute([
                        ':student_id'  => $studentId,
                        ':subject_id'  => $subjectId,
                        ':semester_id' => $semesterId,
                    ]);

                    if ((int) $stmt->fetchColumn() > 0) {
                        $error = 'Student is already enrolled in this subject.';
                    } else {
                        $stmt = $db->prepare(
                            "INSERT INTO enrollments
                                (student_id, subject_id, semester_id, status)
                             VALUES
                                (:student_id, :subject_id, :semester_id, 'enrolled')"
                        );
                        if ($stmt->execute([
                            ':student_id'  => $studentId,
                            ':subject_id'  => $subjectId,
                            ':semester_id' => $semesterId,
                        ])) {
                            Auth::logAction(
                                "Enrolled student {$studentId} in subject {$subjectId}"
                            );
                            $success = 'Student enrolled successfully.';
                        } else {
                            $error = 'Failed to enroll student.';
                        }
                    }
                }
                break;

            // ── Bulk Enroll ───────────────────────────────────
            case 'bulk_enroll':
                $subjectId   = (int) ($_POST['subject_id']   ?? 0);
                $semesterId  = (int) ($_POST['semester_id']  ?? 0);
                $studentIds  = $_POST['student_ids']         ?? [];

                if (!$subjectId || !$semesterId || empty($studentIds)) {
                    $error = 'Please select subject, semester and students.';
                } else {
                    $enrolled = 0;
                    $skipped  = 0;

                    foreach ($studentIds as $sId) {
                        $sId = (int) $sId;

                        // Check duplicate
                        $check = $db->prepare(
                            "SELECT COUNT(*) FROM enrollments
                             WHERE student_id  = :sid
                             AND   subject_id  = :subid
                             AND   semester_id = :semid"
                        );
                        $check->execute([
                            ':sid'   => $sId,
                            ':subid' => $subjectId,
                            ':semid' => $semesterId,
                        ]);

                        if ((int) $check->fetchColumn() > 0) {
                            $skipped++;
                            continue;
                        }

                        $stmt = $db->prepare(
                            "INSERT INTO enrollments
                                (student_id, subject_id, semester_id, status)
                             VALUES
                                (:sid, :subid, :semid, 'enrolled')"
                        );
                        if ($stmt->execute([
                            ':sid'   => $sId,
                            ':subid' => $subjectId,
                            ':semid' => $semesterId,
                        ])) {
                            $enrolled++;
                        }
                    }

                    Auth::logAction(
                        "Bulk enrolled {$enrolled} students in subject {$subjectId}"
                    );
                    $success = "{$enrolled} student(s) enrolled.";
                    if ($skipped > 0) {
                        $success .= " {$skipped} skipped (already enrolled).";
                    }
                }
                break;

            // ── Remove Enrollment ─────────────────────────────
            case 'remove':
                $enrollmentId = (int) ($_POST['enrollment_id'] ?? 0);

                // Check if has grade
                $check = $db->prepare(
                    "SELECT COUNT(*) FROM grades WHERE enrollment_id = :id"
                );
                $check->execute([':id' => $enrollmentId]);

                if ((int) $check->fetchColumn() > 0) {
                    $error = 'Cannot remove enrollment with existing grades.';
                } else {
                    $stmt = $db->prepare(
                        "DELETE FROM enrollments WHERE id = :id"
                    );
                    if ($stmt->execute([':id' => $enrollmentId])) {
                        Auth::logAction("Removed enrollment ID: {$enrollmentId}");
                        $success = 'Enrollment removed.';
                    } else {
                        $error = 'Failed to remove enrollment.';
                    }
                }
                break;
        }
    }
}

// ── Fetch Data ────────────────────────────────────────────────
// Active semester
$activeSemester = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$filterSemesterId = (int) ($_GET['semester_id']
    ?? $activeSemester['id']
    ?? 0);

$filterSubjectId  = (int) ($_GET['subject_id'] ?? 0);

// Semesters dropdown
$semesters = $db->query(
    "SELECT * FROM semesters ORDER BY school_year DESC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Subjects dropdown
$subjects = $db->query(
    "SELECT id, code, name FROM subjects WHERE is_active = 1 ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

// All students dropdown
$students = $db->query(
    "SELECT s.id, s.first_name, s.last_name, s.student_id AS student_number,
            s.year_level
     FROM   students s
     JOIN   users u ON u.id = s.user_id
     WHERE  u.is_active = 1
     ORDER  BY s.last_name, s.first_name"
)->fetchAll(PDO::FETCH_ASSOC);

// Current enrollments
$enrollmentsQuery = "
    SELECT e.*,
           s.first_name, s.last_name,
           s.student_id AS student_number,
           s.year_level,
           sub.name AS subject_name,
           sub.code AS subject_code,
           sem.name AS semester_name,
           sem.school_year,
           CASE WHEN g.id IS NOT NULL THEN 1 ELSE 0 END AS has_grade
    FROM   enrollments e
    JOIN   students s   ON s.id = e.student_id
    JOIN   subjects sub ON sub.id = e.subject_id
    JOIN   semesters sem ON sem.id = e.semester_id
    LEFT JOIN grades g  ON g.enrollment_id = e.id
    WHERE  1=1
";
$enrollParams = [];

if ($filterSemesterId) {
    $enrollmentsQuery .= " AND e.semester_id = :semester_id";
    $enrollParams[':semester_id'] = $filterSemesterId;
}
if ($filterSubjectId) {
    $enrollmentsQuery .= " AND e.subject_id = :subject_id";
    $enrollParams[':subject_id'] = $filterSubjectId;
}

$enrollmentsQuery .= " ORDER BY s.last_name, s.first_name";

$stmt = $db->prepare($enrollmentsQuery);
$stmt->execute($enrollParams);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = Auth::generateCsrf();
$pageTitle = 'Manage Enrollment';

include '../shared/header.php';
include '../shared/sidebar.php';
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
        <div class="topbar-title">Manage Enrollment</div>
        <div class="topbar-subtitle">
          Enroll students into subjects
        </div>
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
          <div class="breadcrumb-item active">Enrollment</div>
        </div>
        <h1>Manage Enrollment</h1>
        <p><?= count($enrollments) ?> enrollment records</p>
      </div>
      <div class="page-header-right">
        <button class="btn btn-secondary"
                onclick="Modal.open('bulk-enroll-modal')">
          <i class="fas fa-users"></i> Bulk Enroll
        </button>
        <button class="btn btn-primary"
                onclick="Modal.open('enroll-modal')">
          <i class="fas fa-plus"></i> Enroll Student
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="glass-card mb-5">
      <div class="glass-card-body">
        <form method="GET" class="d-flex gap-4 align-center flex-wrap">
          <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
            <label class="form-label">Filter by Semester</label>
            <select name="semester_id"
                    class="form-control"
                    onchange="this.form.submit()">
              <option value="">All Semesters</option>
              <?php foreach ($semesters as $sem): ?>
                <option value="<?= $sem['id'] ?>"
                  <?= $filterSemesterId == $sem['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sem['name']) ?>
                  (<?= htmlspecialchars($sem['school_year']) ?>)
                  <?= $sem['is_active'] ? '✓ Active' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
            <label class="form-label">Filter by Subject</label>
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
          <?php if ($filterSemesterId || $filterSubjectId): ?>
            <div style="margin-top:20px">
              <a href="manage_enrollment.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i> Clear
              </a>
            </div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Enrollments Table -->
    <div class="glass-card">
      <div class="glass-card-header">
        <h3><i class="fas fa-list"></i> Enrollment Records</h3>
        <div class="search-input-wrap">
          <i class="fas fa-search search-icon"></i>
          <input type="text"
                 class="form-control"
                 placeholder="Search..."
                 data-table-search="enrollments-table">
        </div>
      </div>

      <div class="table-wrapper">
        <table class="data-table" id="enrollments-table">
          <thead>
            <tr>
              <th>Student</th>
              <th class="sortable">ID No.</th>
              <th class="sortable">Subject</th>
              <th class="sortable">Semester</th>
              <th>Status</th>
              <th>Grade</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($enrollments)): ?>
              <tr>
                <td colspan="7">
                  <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h3>No Enrollments Found</h3>
                    <p>Try different filters or enroll a student.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($enrollments as $enroll): ?>
                <tr>
                  <td>
                    <div class="user-cell">
                      <div class="avatar avatar-sm avatar-primary">
                        <?= strtoupper(substr($enroll['first_name'], 0, 1)
                          . substr($enroll['last_name'],  0, 1)) ?>
                      </div>
                      <div class="user-cell-info">
                        <div class="name">
                          <?= htmlspecialchars(
                            $enroll['last_name'] . ', ' . $enroll['first_name']
                          ) ?>
                        </div>
                        <div class="sub">
                          Year <?= $enroll['year_level'] ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($enroll['student_number']) ?></td>
                  <td>
                    <strong>
                      <?= htmlspecialchars($enroll['subject_code']) ?>
                    </strong>
                    <div class="text-xs text-muted">
                      <?= htmlspecialchars($enroll['subject_name']) ?>
                    </div>
                  </td>
                  <td>
                    <?= htmlspecialchars($enroll['semester_name']) ?>
                    <div class="text-xs text-muted">
                      <?= htmlspecialchars($enroll['school_year']) ?>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-success">
                      <?= htmlspecialchars($enroll['status']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($enroll['has_grade']): ?>
                      <span class="badge badge-info">
                        <i class="fas fa-check"></i> Graded
                      </span>
                    <?php else: ?>
                      <span class="badge badge-muted">No grade</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!$enroll['has_grade']): ?>
                      <button class="btn btn-ghost-danger btn-sm btn-icon"
                              data-tooltip="Remove Enrollment"
                              onclick="confirmRemoveEnrollment(
                                <?= $enroll['id'] ?>,
                                '<?= htmlspecialchars(
                                  $enroll['first_name'] . ' ' . $enroll['last_name']
                                ) ?>'
                              )">
                        <i class="fas fa-times"></i>
                      </button>
                    <?php else: ?>
                      <span class="text-muted text-xs">Locked</span>
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

<!-- ── Enroll Modal ───────────────────────────────────────────── -->
<div class="modal-overlay" id="enroll-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">
        <div class="modal-icon"><i class="fas fa-plus"></i></div>
        Enroll Student
      </div>
      <button class="modal-close" data-modal-close>
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"     value="enroll">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="form-group">
          <label class="form-label">
            Student <span class="required">*</span>
          </label>
          <select name="student_id" class="form-control" required>
            <option value="">— Select Student —</option>
            <?php foreach ($students as $stu): ?>
              <option value="<?= $stu['id'] ?>">
                <?= htmlspecialchars(
                  $stu['last_name'] . ', ' . $stu['first_name']
                ) ?>
                (<?= htmlspecialchars($stu['student_number']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">
            Subject <span class="required">*</span>
          </label>
          <select name="subject_id" class="form-control" required>
            <option value="">— Select Subject —</option>
            <?php foreach ($subjects as $sub): ?>
              <option value="<?= $sub['id'] ?>">
                <?= htmlspecialchars($sub['code']) ?>
                — <?= htmlspecialchars($sub['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">
            Semester <span class="required">*</span>
          </label>
          <select name="semester_id" class="form-control" required>
            <option value="">— Select Semester —</option>
            <?php foreach ($semesters as $sem): ?>
              <option value="<?= $sem['id'] ?>"
                <?= ($activeSemester && $sem['id'] == $activeSemester['id'])
                    ? 'selected' : '' ?>>
                <?= htmlspecialchars($sem['name']) ?>
                (<?= htmlspecialchars($sem['school_year']) ?>)
                <?= $sem['is_active'] ? '✓' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>
          Cancel
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-check"></i> Enroll
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Bulk Enroll Modal ──────────────────────────────────────── -->
<div class="modal-overlay" id="bulk-enroll-modal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <div class="modal-icon"><i class="fas fa-users"></i></div>
        Bulk Enroll Students
      </div>
      <button class="modal-close" data-modal-close>
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action"     value="bulk_enroll">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">
              Subject <span class="required">*</span>
            </label>
            <select name="subject_id" class="form-control" required>
              <option value="">— Select Subject —</option>
              <?php foreach ($subjects as $sub): ?>
                <option value="<?= $sub['id'] ?>">
                  <?= htmlspecialchars($sub['code']) ?>
                  — <?= htmlspecialchars($sub['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">
              Semester <span class="required">*</span>
            </label>
            <select name="semester_id" class="form-control" required>
              <option value="">— Select Semester —</option>
              <?php foreach ($semesters as $sem): ?>
                <option value="<?= $sem['id'] ?>"
                  <?= ($activeSemester && $sem['id'] == $activeSemester['id'])
                      ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sem['name']) ?>
                  (<?= htmlspecialchars($sem['school_year']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            Select Students <span class="required">*</span>
          </label>
          <div class="mb-2">
            <label class="checkbox-wrap">
              <input type="checkbox" id="select-all-students">
              <span class="checkbox-custom"></span>
              <span class="checkbox-label font-semibold">
                Select All Students
              </span>
            </label>
          </div>
          <div style="max-height:250px;overflow-y:auto;
                      border:1px solid var(--glass-border);
                      border-radius:var(--radius-md);padding:var(--space-3)">
            <?php foreach ($students as $stu): ?>
              <label class="checkbox-wrap mb-2">
                <input type="checkbox"
                       name="student_ids[]"
                       class="student-checkbox"
                       value="<?= $stu['id'] ?>">
                <span class="checkbox-custom"></span>
                <span class="checkbox-label">
                  <?= htmlspecialchars(
                    $stu['last_name'] . ', ' . $stu['first_name']
                  ) ?>
                  <span class="text-muted text-xs">
                    (<?= htmlspecialchars($stu['student_number']) ?>
                    — Year <?= $stu['year_level'] ?>)
                  </span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>
          Cancel
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-users"></i> Bulk Enroll
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Remove Form -->
<form method="POST" id="remove-enrollment-form" style="display:none">
  <input type="hidden" name="action"        value="remove">
  <input type="hidden" name="csrf_token"    value="<?= $csrfToken ?>">
  <input type="hidden" name="enrollment_id" id="remove-enrollment-id">
</form>

<script>
// Select all students checkbox
document.getElementById('select-all-students')?.addEventListener('change', function () {
  document.querySelectorAll('.student-checkbox')
    .forEach(cb => cb.checked = this.checked);
});

function confirmRemoveEnrollment(id, name) {
  Modal.confirm({
    title:   'Remove Enrollment',
    message: `Remove <strong>${name}</strong> from this subject?`,
    confirm: 'Remove',
    type:    'danger',
    onConfirm() {
      document.getElementById('remove-enrollment-id').value = id;
      document.getElementById('remove-enrollment-form').submit();
    }
  });
}
</script>

<?php include '../shared/footer.php'; ?>