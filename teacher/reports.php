<?php
// ============================================================
// teacher/reports.php — Teacher Subject Analytics
// ============================================================

require_once '../middleware/TeacherMiddleware.php';
require_once '../models/Teacher.php';
require_once '../helpers/Auth.php';

TeacherMiddleware::handle();

$db           = DB::getInstance();
$teacherModel = new Teacher();

$teacher = $teacherModel->getByUserId(Auth::getUserId());
if (!$teacher) {
    App::redirect('/auth/login.php');
}
$teacherId = $teacher['id'];

// ── Semesters & filter ────────────────────────────────────────
$activeSemester = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$semesters = $db->query(
    "SELECT * FROM semesters ORDER BY school_year DESC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$filterSemesterId = (int)($_GET['semester_id'] ?? $activeSemester['id'] ?? 0);

// ── My subjects (check both teacher_subjects and subjects.teacher_id) ─
$subjectsStmt = $db->prepare(
    "SELECT DISTINCT sub.id AS subject_id, sub.code AS subject_code,
            sub.name AS subject_name, sub.units,
            sem.id AS semester_id, sem.name AS semester_name, sem.school_year
     FROM   subjects sub
     JOIN   semesters sem ON sem.id = :semester_id
     WHERE  sub.teacher_id = :teacher_id
        OR  sub.id IN (
              SELECT subject_id FROM teacher_subjects
              WHERE teacher_id = :teacher_id2 AND semester_id = :semester_id2
            )
     ORDER  BY sub.name"
);
$subjectsStmt->execute([
    ':teacher_id'   => $teacherId,
    ':semester_id'  => $filterSemesterId,
    ':teacher_id2'  => $teacherId,
    ':semester_id2' => $filterSemesterId,
]);
$mySubjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
$subjectIds = array_column($mySubjects, 'subject_id');

// ── Overall stats ─────────────────────────────────────────────
$overallStats = [
    'total_students' => 0, 'total_graded' => 0,
    'overall_avg' => null, 'total_passed' => 0,
    'total_failed' => 0, 'highest' => null, 'lowest' => null,
];

if (!empty($subjectIds)) {
    $ph = implode(',', array_fill(0, count($subjectIds), '?'));
    $params = array_merge($subjectIds, [$filterSemesterId]);
    $stmt = $db->prepare(
        "SELECT COUNT(DISTINCT e.student_id) AS total_students,
                COUNT(DISTINCT g.id)         AS total_graded,
                AVG(g.final_grade)           AS overall_avg,
                SUM(g.final_grade >= 75)     AS total_passed,
                SUM(g.final_grade <  75)     AS total_failed,
                MAX(g.final_grade)           AS highest,
                MIN(g.final_grade)           AS lowest
         FROM   enrollments e
         LEFT JOIN grades g ON g.enrollment_id = e.id
         WHERE  e.subject_id IN ({$ph})
         AND    e.semester_id = ?"
    );
    $stmt->execute($params);
    $overallStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $overallStats;
}

// ── Per-subject breakdown ─────────────────────────────────────
$subjectStats = [];
foreach ($mySubjects as $sub) {
    $stmt = $db->prepare(
        "SELECT COUNT(e.id)              AS enrolled,
                COUNT(g.id)              AS graded,
                AVG(g.final_grade)       AS avg_grade,
                MAX(g.final_grade)       AS highest,
                MIN(g.final_grade)       AS lowest,
                SUM(g.final_grade >= 75) AS passed,
                SUM(g.final_grade <  75) AS failed
         FROM   enrollments e
         LEFT JOIN grades g ON g.enrollment_id = e.id
         WHERE  e.subject_id  = ?
         AND    e.semester_id = ?"
    );
    $stmt->execute([$sub['subject_id'], $filterSemesterId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['subject_name']  = $sub['subject_name'];
    $stats['subject_code']  = $sub['subject_code'];
    $stats['semester_name'] = $sub['semester_name'];
    $stats['school_year']   = $sub['school_year'];
    $stats['avg_grade']     = round((float)($stats['avg_grade'] ?? 0), 2);
    $subjectStats[] = $stats;
}

// ── Top students ──────────────────────────────────────────────
$topStudents = [];
if (!empty($subjectIds)) {
    $ph = implode(',', array_fill(0, count($subjectIds), '?'));
    $params = array_merge($subjectIds, [$filterSemesterId]);
    $stmt = $db->prepare(
        "SELECT s.first_name, s.last_name, s.student_number,
                AVG(g.final_grade) AS avg_grade,
                COUNT(g.id)        AS subjects_count
         FROM   grades g
         JOIN   enrollments e ON e.id = g.enrollment_id
         JOIN   students s    ON s.id = e.student_id
         WHERE  e.subject_id IN ({$ph})
         AND    e.semester_id = ?
         AND    g.final_grade IS NOT NULL
         GROUP  BY s.id
         ORDER  BY avg_grade DESC
         LIMIT  5"
    );
    $stmt->execute($params);
    $topStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'My Reports';
include '../shared/header.php';
?>

<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-mobile-menu"><i class="fas fa-bars"></i></button>
      <div>
        <div class="topbar-title">My Reports</div>
        <div class="topbar-subtitle">Subject performance analytics</div>
      </div>
    </div>
    <div class="topbar-right">
      <form method="GET" style="display:inline">
        <select name="semester_id" class="form-control" style="width:220px" onchange="this.form.submit()">
          <?php foreach ($semesters as $sem): ?>
            <option value="<?= $sem['id'] ?>" <?= $filterSemesterId == $sem['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($sem['name']) ?> (<?= htmlspecialchars($sem['school_year']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
      <button class="btn btn-secondary btn-sm" onclick="window.print()">
        <i class="fas fa-print"></i> Print
      </button>
      <div class="dropdown">
        <button class="topbar-btn" id="user-menu-btn">
          <i class="fas fa-user-circle"></i>
        </button>
        <div class="dropdown-menu" id="user-dropdown">
          <a href="<?= App::url('/profile.php') ?>" class="dropdown-item">
            <i class="fas fa-user"></i> Profile
          </a>
          <div class="dropdown-divider"></div>
          <a href="<?= App::url('/auth/logout.php') ?>" class="dropdown-item danger">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="page-content">

    <!-- Overall Stats -->
    <div class="stats-grid mb-6">
      <div class="stat-card primary">
        <div class="stat-icon primary"><i class="fas fa-book"></i></div>
        <div class="stat-info">
          <div class="stat-label">My Subjects</div>
          <div class="stat-value"><?= count($mySubjects) ?></div>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon info"><i class="fas fa-users"></i></div>
        <div class="stat-info">
          <div class="stat-label">Total Students</div>
          <div class="stat-value"><?= $overallStats['total_students'] ?? 0 ?></div>
        </div>
      </div>
      <div class="stat-card success">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
          <div class="stat-label">Passed</div>
          <div class="stat-value"><?= $overallStats['total_passed'] ?? 0 ?></div>
        </div>
      </div>
      <div class="stat-card danger">
        <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info">
          <div class="stat-label">Failed</div>
          <div class="stat-value"><?= $overallStats['total_failed'] ?? 0 ?></div>
        </div>
      </div>
      <div class="stat-card warning">
        <div class="stat-icon warning"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info">
          <div class="stat-label">Overall Average</div>
          <div class="stat-value">
            <?= isset($overallStats['overall_avg']) && $overallStats['overall_avg'] !== null
                ? number_format($overallStats['overall_avg'], 2) : '—' ?>
          </div>
        </div>
      </div>
      <div class="stat-card teal">
        <div class="stat-icon teal"><i class="fas fa-trophy"></i></div>
        <div class="stat-info">
          <div class="stat-label">Highest Grade</div>
          <div class="stat-value">
            <?= $overallStats['highest'] !== null
                ? number_format($overallStats['highest'], 2) : '—' ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Subject Breakdown -->
    <div class="glass-card mb-6">
      <div class="glass-card-header">
        <h3><i class="fas fa-table"></i> Subject Breakdown</h3>
      </div>
      <?php if (empty($subjectStats)): ?>
        <div class="glass-card-body">
          <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h3>No Subjects Found</h3>
            <p>No subjects assigned for the selected semester.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>Enrolled</th>
                <th>Graded</th>
                <th>Average</th>
                <th>Highest</th>
                <th>Lowest</th>
                <th>Passed</th>
                <th>Failed</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($subjectStats as $stat): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($stat['subject_code']) ?></strong>
                    <div class="text-xs text-muted"><?= htmlspecialchars($stat['subject_name']) ?></div>
                  </td>
                  <td><?= $stat['enrolled'] ?></td>
                  <td><?= $stat['graded'] ?></td>
                  <td>
                    <?php if ($stat['avg_grade'] > 0): ?>
                      <span style="font-weight:600;color:<?= $stat['avg_grade'] >= 75 ? 'var(--success)' : 'var(--danger)' ?>">
                        <?= $stat['avg_grade'] ?>
                      </span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?= $stat['highest'] ? number_format($stat['highest'], 2) : '—' ?></td>
                  <td><?= $stat['lowest']  ? number_format($stat['lowest'],  2) : '—' ?></td>
                  <td><span class="badge badge-passed"><?= $stat['passed'] ?></span></td>
                  <td><span class="badge badge-failed"><?= $stat['failed'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Top Students -->
    <?php if (!empty($topStudents)): ?>
      <div class="glass-card">
        <div class="glass-card-header">
          <h3><i class="fas fa-medal"></i> Top Performing Students</h3>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Student</th>
                <th>ID No.</th>
                <th>Subjects Graded</th>
                <th>Average Grade</th>
                <th>Standing</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topStudents as $i => $stu):
                $avg = (float)$stu['avg_grade'];
                $standing = $avg >= 90 ? 'Excellent'
                          : ($avg >= 80 ? 'Good'
                          : ($avg >= 75 ? 'Satisfactory' : 'Fair'));
                $badgeMap = [
                  'Excellent'    => 'badge-excellent',
                  'Good'         => 'badge-good',
                  'Satisfactory' => 'badge-good',
                  'Fair'         => 'badge-average',
                ];
              ?>
                <tr>
                  <td><?= ['🥇','🥈','🥉'][$i] ?? ($i + 1) ?></td>
                  <td>
                    <div class="user-cell">
                      <div class="avatar avatar-sm avatar-primary">
                        <?= strtoupper(substr($stu['first_name'], 0, 1) . substr($stu['last_name'], 0, 1)) ?>
                      </div>
                      <div class="user-cell-info">
                        <div class="name">
                          <?= htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name']) ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($stu['student_number']) ?></td>
                  <td><span class="badge badge-info"><?= $stu['subjects_count'] ?></span></td>
                  <td>
                    <span style="font-weight:700;color:<?= $avg >= 75 ? 'var(--success)' : 'var(--danger)' ?>">
                      <?= number_format($avg, 2) ?>
                    </span>
                  </td>
                  <td><span class="badge <?= $badgeMap[$standing] ?>"><?= $standing ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="glass-card">
        <div class="glass-card-body">
          <div class="empty-state">
            <div class="empty-state-icon">🎓</div>
            <h3>No Graded Students Yet</h3>
            <p>Enter grades to see student performance here.</p>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div><!-- /page-content -->
</div><!-- /main-content -->

<?php include '../shared/footer.php'; ?>
