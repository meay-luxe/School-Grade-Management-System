<?php
// ============================================================
// teacher/my_subjects.php — Subjects Assigned To Teacher
// ============================================================

require_once '../middleware/TeacherMiddleware.php';
require_once '../models/Teacher.php';
require_once '../helpers/Auth.php';

TeacherMiddleware::handle();

$db           = DB::getInstance();
$teacherModel = new Teacher();

$teacher = $teacherModel->getByUserId(Auth::getUserId());
if (!$teacher) {
    header('Location: ../auth/login.php');
    exit();
}

$teacherId = $teacher['id'];

// ── Semesters Dropdown ────────────────────────────────────────
$semesters = $db->query(
    "SELECT * FROM semesters ORDER BY school_year DESC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$activeSemester   = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$filterSemesterId = (int) ($_GET['semester_id']
    ?? $activeSemester['id']
    ?? 0);

// ── My Subjects ───────────────────────────────────────────────
$mySubjects = $teacherModel->getSubjects($teacherId, $filterSemesterId ?: null);

// ── Per-subject stats ─────────────────────────────────────────
foreach ($mySubjects as &$sub) {
    $stmt = $db->prepare(
        "SELECT
            COUNT(e.id)                         AS total_enrolled,
            COUNT(g.id)                         AS total_graded,
            AVG(g.final_grade)                  AS avg_grade,
            SUM(g.final_grade >= 75)            AS passed,
            SUM(g.final_grade < 75)             AS failed
         FROM   enrollments e
         LEFT JOIN grades g ON g.enrollment_id = e.id
         WHERE  e.subject_id  = :subject_id
         AND    e.semester_id = :semester_id"
    );
    $stmt->execute([
        ':subject_id'  => $sub['subject_id'],
        ':semester_id' => $sub['semester_id'],
    ]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $sub['total_enrolled'] = (int)   ($stats['total_enrolled'] ?? 0);
    $sub['total_graded']   = (int)   ($stats['total_graded']   ?? 0);
    $sub['avg_grade']      = round((float)($stats['avg_grade'] ?? 0), 2);
    $sub['passed']         = (int)   ($stats['passed']         ?? 0);
    $sub['failed']         = (int)   ($stats['failed']         ?? 0);
    $sub['grade_progress'] = $sub['total_enrolled'] > 0
        ? round(($sub['total_graded'] / $sub['total_enrolled']) * 100)
        : 0;
}
unset($sub);

$pageTitle = 'My Subjects';

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
        <div class="topbar-title">My Subjects</div>
        <div class="topbar-subtitle">
          Assigned subjects this semester
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
          <div class="breadcrumb-item active">My Subjects</div>
        </div>
        <h1>My Subjects</h1>
        <p>
          <?= count($mySubjects) ?> subject(s) assigned
        </p>
      </div>
      <div class="page-header-right">
        <form method="GET">
          <select name="semester_id"
                  class="form-control"
                  onchange="this.form.submit()">
            <option value="">All Semesters</option>
            <?php foreach ($semesters as $sem): ?>
              <option value="<?= $sem['id'] ?>"
                <?= $filterSemesterId == $sem['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($sem['name']) ?>
                (<?= htmlspecialchars($sem['school_year']) ?>)
                <?= $sem['is_active'] ? '✓' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <!-- Subjects Grid -->
    <?php if (empty($mySubjects)): ?>
      <div class="glass-card">
        <div class="glass-card-body">
          <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h3>No Subjects Assigned</h3>
            <p>
              You have no subjects for the selected semester.
              Contact the administrator.
            </p>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="grid-auto-2 stagger-children">
        <?php foreach ($mySubjects as $sub): ?>
          <div class="glass-card">
            <div class="glass-card-body">

              <!-- Subject Header -->
              <div class="d-flex align-center justify-between mb-4">
                <div class="d-flex align-center gap-3">
                  <div class="stat-icon primary"
                       style="width:44px;height:44px;font-size:1rem">
                    <i class="fas fa-book"></i>
                  </div>
                  <div>
                    <div class="font-bold">
                      <?= htmlspecialchars($sub['subject_name']) ?>
                    </div>
                    <div class="text-xs text-muted">
                      <?= htmlspecialchars($sub['subject_code']) ?>
                      &nbsp;·&nbsp;
                      <?= $sub['units'] ?> units
                    </div>
                  </div>
                </div>
                <span class="badge badge-info">
                  <?= htmlspecialchars($sub['semester_name']) ?>
                </span>
              </div>

              <!-- Stats Row -->
              <div class="d-flex gap-5 mb-4">
                <div>
                  <div class="text-xs text-muted">Enrolled</div>
                  <div class="font-bold text-xl">
                    <?= $sub['total_enrolled'] ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Graded</div>
                  <div class="font-bold text-xl text-success">
                    <?= $sub['total_graded'] ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Average</div>
                  <div class="font-bold text-xl <?=
                    $sub['avg_grade'] >= 90 ? 'text-success'
                    : ($sub['avg_grade'] >= 75 ? 'text-info'
                    : ($sub['avg_grade'] > 0 ? 'text-warning'
                    : 'text-muted')) ?>">
                    <?= $sub['avg_grade'] > 0
                        ? number_format($sub['avg_grade'], 2)
                        : '—' ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Passed</div>
                  <div class="font-bold text-xl text-success">
                    <?= $sub['passed'] ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Failed</div>
                  <div class="font-bold text-xl text-danger">
                    <?= $sub['failed'] ?>
                  </div>
                </div>
              </div>

              <!-- Grading Progress Bar -->
              <div class="mb-4">
                <div class="d-flex justify-between text-xs text-muted mb-1">
                  <span>Grading Progress</span>
                  <span><?= $sub['grade_progress'] ?>%</span>
                </div>
                <div class="progress">
                  <div class="progress-bar <?=
                    $sub['grade_progress'] >= 100 ? 'success'
                    : ($sub['grade_progress'] >= 50 ? 'primary'
                    : 'warning') ?>"
                    style="width:<?= $sub['grade_progress'] ?>%">
                  </div>
                </div>
              </div>

              <!-- School Year -->
              <div class="text-xs text-muted mb-4">
                <i class="fas fa-calendar-alt"></i>
                <?= htmlspecialchars($sub['school_year']) ?>
              </div>

              <!-- Actions -->
              <div class="d-flex gap-2">
                <a href="class_list.php?subject_id=<?= $sub['subject_id'] ?>&semester_id=<?= $sub['semester_id'] ?>"
                   class="btn btn-secondary btn-sm flex-1 text-center">
                  <i class="fas fa-list"></i> Class List
                </a>
                <a href="manage_grades.php?subject_id=<?= $sub['subject_id'] ?>&semester_id=<?= $sub['semester_id'] ?>"
                   class="btn btn-primary btn-sm flex-1 text-center">
                  <i class="fas fa-pen"></i> Enter Grades
                </a>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include '../shared/footer.php'; ?>