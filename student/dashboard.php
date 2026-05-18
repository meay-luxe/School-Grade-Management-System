<?php
// ============================================================
// student/dashboard.php — Student Home + GPA Ring
// ============================================================

require_once '../middleware/StudentMiddleware.php';
require_once '../models/Student.php';
require_once '../models/Grade.php';
require_once '../helpers/Auth.php';
require_once '../helpers/GradeCalculator.php';

StudentMiddleware::handle();

$db           = DB::getInstance();
$studentModel = new Student();

// ── Get Student Profile ───────────────────────────────────────
$student = $studentModel->getByUserId(Auth::id());

if (!$student) {
    Auth::logout();
    header('Location: ../auth/login.php');
    exit();
}

$studentId = $student['id'];

// ── Active Semester ───────────────────────────────────────────
$activeSemester = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$semesterId = $activeSemester['id'] ?? 0;

// ── Current Enrollments ───────────────────────────────────────
$stmt = $db->prepare(
    "SELECT e.*,
            sub.name        AS subject_name,
            sub.code        AS subject_code,
            sub.units,
            sub.description AS subject_desc,
            g.prelim,
            g.midterm,
            g.prefinal,
            g.final_exam,
            g.final_grade,
            g.gpa,
            g.standing,
            g.is_locked,
            t.first_name    AS teacher_fname,
            t.last_name     AS teacher_lname
     FROM   enrollments e
     JOIN   subjects sub ON sub.id = e.subject_id
     LEFT JOIN grades g  ON g.enrollment_id = e.id
     LEFT JOIN teacher_subjects ts ON ts.subject_id  = sub.id
                  AND ts.semester_id = e.semester_id
     LEFT JOIN teachers t ON t.id = ts.teacher_id
     WHERE  e.student_id  = :student_id
     AND    e.semester_id = :semester_id
     ORDER  BY sub.name"
);
$stmt->execute([
    ':student_id'  => $studentId,
    ':semester_id' => $semesterId,
]);
$currentSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Current Semester GPA ──────────────────────────────────────
$gradedSubjects = array_filter(
    $currentSubjects,
    fn($s) => $s['final_grade'] !== null
);

$currentGPA = 0;
if (!empty($gradedSubjects)) {
    $totalPoints = 0;
    $totalUnits  = 0;
    foreach ($gradedSubjects as $sub) {
        $gpa         = GradeCalculator::getGPA((float) $sub['final_grade']);
        $totalPoints += $gpa * $sub['units'];
        $totalUnits  += $sub['units'];
    }
    $currentGPA = $totalUnits > 0
        ? round($totalPoints / $totalUnits, 2)
        : 0;
}

// ── Overall / Cumulative GPA ──────────────────────────────────
$stmt = $db->prepare(
    "SELECT g.final_grade, sub.units
     FROM   grades g
     JOIN   enrollments e ON e.id = g.enrollment_id
     JOIN   subjects sub  ON sub.id = e.subject_id
     WHERE  e.student_id = :student_id
     AND    g.final_grade IS NOT NULL"
);
$stmt->execute([':student_id' => $studentId]);
$allGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$overallGPA = 0;
if (!empty($allGrades)) {
    $totalPoints = 0;
    $totalUnits  = 0;
    foreach ($allGrades as $g) {
        $gpa         = GradeCalculator::getGPA((float) $g['final_grade']);
        $totalPoints += $gpa * $g['units'];
        $totalUnits  += $g['units'];
    }
    $overallGPA = $totalUnits > 0
        ? round($totalPoints / $totalUnits, 2)
        : 0;
}

// ── Stats ─────────────────────────────────────────────────────
$totalSubjects  = count($currentSubjects);
$totalGraded    = count($gradedSubjects);
$totalPassed    = count(array_filter(
    $gradedSubjects,
    fn($s) => (float) $s['final_grade'] >= 75
));
$totalFailed    = count(array_filter(
    $gradedSubjects,
    fn($s) => (float) $s['final_grade'] <  75
));
$totalUnitsEnrolled = array_sum(array_column($currentSubjects, 'units'));

// ── All Semester History (for grades.php link) ────────────────
$semesterHistory = $db->prepare(
    "SELECT DISTINCT sem.id, sem.name, sem.school_year
     FROM   enrollments e
     JOIN   semesters sem ON sem.id = e.semester_id
     WHERE  e.student_id = :student_id
     ORDER  BY sem.school_year DESC, sem.id DESC"
);
$semesterHistory->execute([':student_id' => $studentId]);
$semesterHistory = $semesterHistory->fetchAll(PDO::FETCH_ASSOC);

// ── GPA Ring Chart Data ───────────────────────────────────────
$gpaMax        = 4.0;
$gpaPercent    = min(($currentGPA / $gpaMax) * 100, 100);
$radius        = 54;
$circumference = 2 * M_PI * $radius;
$dashOffset    = $circumference - ($gpaPercent / 100) * $circumference;

// GPA Ring color
$ringColor = $currentGPA >= 3.5 ? '#00d4aa'
           : ($currentGPA >= 2.5 ? '#54a0ff'
           : ($currentGPA >= 1.5 ? '#ff9f43'
           : '#ff6b6b'));

// ── Radar Chart Data (grades per subject) ─────────────────────
$radarLabels = array_map(
    fn($s) => $s['subject_code'],
    array_filter($currentSubjects, fn($s) => $s['final_grade'] !== null)
);
$radarScores = array_map(
    fn($s) => (float) $s['final_grade'],
    array_filter($currentSubjects, fn($s) => $s['final_grade'] !== null)
);

$radarData = json_encode([
    'labels' => array_values($radarLabels),
    'scores' => array_values($radarScores),
]);

// ── Progress Chart (GPA history across semesters) ────────────
$progressRaw = $db->prepare(
    "SELECT sem.name AS sem_name,
            sem.school_year,
            AVG(GradeCalculator_gpa(g.final_grade)) AS avg_gpa
     FROM   grades g
     JOIN   enrollments e  ON e.id = g.enrollment_id
     JOIN   semesters sem  ON sem.id = e.semester_id
     WHERE  e.student_id   = :student_id
     AND    g.final_grade  IS NOT NULL
     GROUP  BY sem.id
     ORDER  BY sem.school_year, sem.id"
);

// Note: GradeCalculator_gpa is not a MySQL function,
// so we compute it in PHP instead
$stmt = $db->prepare(
    "SELECT sem.id, sem.name AS sem_name,
            sem.school_year,
            g.final_grade,
            sub.units
     FROM   grades g
     JOIN   enrollments e ON e.id = g.enrollment_id
     JOIN   subjects sub  ON sub.id = e.subject_id
     JOIN   semesters sem ON sem.id = e.semester_id
     WHERE  e.student_id  = :student_id
     AND    g.final_grade IS NOT NULL
     ORDER  BY sem.school_year, sem.id"
);
$stmt->execute([':student_id' => $studentId]);
$allSemGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by semester and compute GPA
$semGPAMap = [];
foreach ($allSemGrades as $row) {
    $key = $row['sem_name'] . ' ' . $row['school_year'];
    if (!isset($semGPAMap[$key])) {
        $semGPAMap[$key] = ['points' => 0, 'units' => 0];
    }
    $gpa = GradeCalculator::getGPA((float) $row['final_grade']);
    $semGPAMap[$key]['points'] += $gpa * $row['units'];
    $semGPAMap[$key]['units']  += $row['units'];
}

$progressLabels = [];
$progressGPAs   = [];
foreach ($semGPAMap as $label => $data) {
    $progressLabels[] = $label;
    $progressGPAs[]   = $data['units'] > 0
        ? round($data['points'] / $data['units'], 2)
        : 0;
}

$progressData = json_encode([
    'labels' => $progressLabels,
    'gpas'   => $progressGPAs,
]);

$pageTitle    = 'Student Dashboard';
$studentName  = $student['first_name'] . ' ' . $student['last_name'];
$greetingHour = (int) date('H');
$greeting     = $greetingHour < 12 ? 'Good Morning'
              : ($greetingHour < 17 ? 'Good Afternoon'
              : 'Good Evening');

$overallStanding = GradeCalculator::getStandingFromGPA($overallGPA);

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
        <div class="topbar-title">My Dashboard</div>
        <div class="topbar-subtitle">
          <?= htmlspecialchars(
            $activeSemester['name']        ?? 'No active semester'
          ) ?>
          <?= $activeSemester
              ? '(' . htmlspecialchars($activeSemester['school_year']) . ')'
              : '' ?>
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="dropdown">
        <button class="topbar-btn" id="user-menu-btn">
          <div class="avatar avatar-sm avatar-primary">
            <?= strtoupper(
              substr($student['first_name'], 0, 1)
              . substr($student['last_name'],  0, 1)
            ) ?>
          </div>
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

    <!-- Welcome Banner -->
    <div class="glass-card mb-6" style="
      background:linear-gradient(135deg,
        rgba(108,99,255,0.15) 0%,
        rgba(0,212,170,0.08) 100%);
      border-color:rgba(108,99,255,0.25)">
      <div class="glass-card-body">
        <div class="d-flex align-center justify-between flex-wrap gap-4">
          <div>
            <div class="text-muted text-sm mb-1">
              <?= $greeting ?>, 👋
            </div>
            <h2 style="font-size:1.4rem;font-weight:800;
                       letter-spacing:-0.03em;margin-bottom:4px">
              <?= htmlspecialchars($studentName) ?>
            </h2>
            <p class="text-secondary text-sm">
              <?= htmlspecialchars($student['course'] ?? 'Student') ?>
              · Year <?= $student['year_level'] ?>
              <?= $student['section']
                  ? '· Section ' . htmlspecialchars($student['section'])
                  : '' ?>
            </p>
            <div class="mt-2">
              <span class="badge badge-primary">
                <?= htmlspecialchars($student['student_id']) ?>
              </span>
            </div>
          </div>
          <div class="d-flex gap-3">
            <a href="grades.php" class="btn btn-primary">
              <i class="fas fa-graduation-cap"></i> View Grades
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats + GPA Ring Row -->
    <div class="grid-2 mb-6" style="
      grid-template-columns:1fr auto;
      gap:var(--space-5)">

      <!-- Stats Grid -->
      <div class="stats-grid" style="
        grid-template-columns:repeat(2,1fr);
        align-content:start">

        <div class="stat-card primary">
          <div class="stat-icon primary">
            <i class="fas fa-book-open"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Subjects</div>
            <div class="stat-value"><?= $totalSubjects ?></div>
            <div class="stat-change flat">
              <?= $totalUnitsEnrolled ?> units
            </div>
          </div>
        </div>

        <div class="stat-card success">
          <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Passed</div>
            <div class="stat-value"><?= $totalPassed ?></div>
            <div class="stat-change up">
              of <?= $totalGraded ?> graded
            </div>
          </div>
        </div>

        <div class="stat-card danger">
          <div class="stat-icon danger">
            <i class="fas fa-times-circle"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Failed</div>
            <div class="stat-value"><?= $totalFailed ?></div>
            <div class="stat-change <?= $totalFailed > 0 ? 'down' : 'flat' ?>">
              <?= $totalFailed > 0 ? 'Needs attention' : 'None' ?>
            </div>
          </div>
        </div>

        <div class="stat-card warning">
          <div class="stat-icon warning">
            <i class="fas fa-star"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Overall GPA</div>
            <div class="stat-value">
              <?= $overallGPA > 0 ? $overallGPA : '—' ?>
            </div>
            <div class="stat-change flat">
              <?= $overallStanding ?? 'No grades yet' ?>
            </div>
          </div>
        </div>

      </div>

      <!-- GPA Ring -->
      <div class="glass-card" style="min-width:220px">
        <div class="glass-card-body d-flex flex-direction-column
                    align-center justify-center"
             style="flex-direction:column;height:100%;
                    min-height:200px;gap:var(--space-4)">

          <div class="text-sm text-muted text-center font-semibold">
            Current Semester GPA
          </div>

          <!-- SVG Ring -->
          <div class="gpa-ring"
               data-gpa="<?= $currentGPA ?>"
               data-max-gpa="4.0">
            <svg viewBox="0 0 120 120">
              <circle class="gpa-ring-bg"
                      cx="60" cy="60" r="<?= $radius ?>"/>
              <circle class="gpa-ring-fill"
                      cx="60" cy="60" r="<?= $radius ?>"
                      stroke="<?= $ringColor ?>"
                      stroke-dasharray="<?= $circumference ?>"
                      stroke-dashoffset="<?= $circumference ?>"/>
            </svg>
            <div class="gpa-ring-center">
              <div class="gpa-value">0.00</div>
              <div class="gpa-label">GPA</div>
            </div>
          </div>

          <!-- Standing Badge -->
          <?php
            $standingMap = [
              'Summa Cum Laude'  => ['badge-excellent', '🏆'],
              'Magna Cum Laude'  => ['badge-excellent', '🥇'],
              'Cum Laude'        => ['badge-good',      '🎖️'],
              'Good Standing'    => ['badge-good',      '✅'],
              'Satisfactory'     => ['badge-average',   '📊'],
              'Needs Improvement'=> ['badge-average',   '⚠️'],
              'Academic Probation'=> ['badge-failed',   '❌'],
            ];
            $currentStanding = GradeCalculator::getStandingFromGPA($currentGPA);
            [$standBadge, $standIcon] = $standingMap[$currentStanding]
              ?? ['badge-muted', '📋'];
          ?>
          <div class="d-flex flex-direction-column align-center gap-2"
               style="flex-direction:column">
            <span class="badge <?= $standBadge ?>"
                  style="font-size:0.75rem;padding:6px 14px">
              <?= $standIcon ?> <?= $currentStanding ?>
            </span>
            <span class="text-xs text-muted">
              <?= $activeSemester
                  ? htmlspecialchars($activeSemester['name'])
                    . ' ' . htmlspecialchars($activeSemester['school_year'])
                  : 'Current Semester' ?>
            </span>
          </div>

        </div>
      </div>

    </div>

    <!-- Charts Row -->
    <?php if (!empty($radarLabels)): ?>
      <div class="grid-2 mb-6">

        <!-- Subject Radar -->
        <div class="glass-card">
          <div class="glass-card-header">
            <h3>
              <i class="fas fa-spider"></i>
              Subject Performance
            </h3>
          </div>
          <div class="glass-card-body">
            <div class="chart-container" style="height:260px">
              <canvas id="student-radar-chart"
                      data-chart-data='<?= $radarData ?>'>
              </canvas>
            </div>
          </div>
        </div>

        <!-- GPA Progress -->
        <div class="glass-card">
          <div class="glass-card-header">
            <h3>
              <i class="fas fa-chart-line"></i>
              GPA Progress
            </h3>
          </div>
          <div class="glass-card-body">
            <div class="chart-container" style="height:260px">
              <canvas id="student-progress-chart"
                      data-chart-data='<?= $progressData ?>'>
              </canvas>
            </div>
          </div>
        </div>

      </div>
    <?php endif; ?>

    <!-- Current Subjects Table -->
    <div class="glass-card">
      <div class="glass-card-header">
        <h3>
          <i class="fas fa-book"></i>
          Current Subjects
        </h3>
        <a href="grades.php" class="btn btn-secondary btn-sm">
          Full Grade History
        </a>
      </div>

      <?php if (empty($currentSubjects)): ?>
        <div class="glass-card-body">
          <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h3>No Subjects This Semester</h3>
            <p>You are not enrolled in any subjects yet.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Subject</th>
                <th>Teacher</th>
                <th class="sortable">Units</th>
                <th class="sortable">Prelim</th>
                <th class="sortable">Midterm</th>
                <th class="sortable">Pre-Final</th>
                <th class="sortable">Final Exam</th>
                <th class="sortable">Final Grade</th>
                <th>Standing</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($currentSubjects as $sub): ?>
                <?php
                  $hasGrade   = $sub['final_grade'] !== null;
                  $grade      = (float) ($sub['final_grade'] ?? 0);
                  $scoreClass = !$hasGrade ? ''
                    : ($grade >= 90 ? 'excellent'
                    : ($grade >= 80 ? 'good'
                    : ($grade >= 75 ? 'average'
                    : 'failed')));

                  $standBadgeMap = [
                    'Excellent'          => 'badge-excellent',
                    'Good'               => 'badge-good',
                    'Satisfactory'       => 'badge-good',
                    'Fair'               => 'badge-average',
                    'Needs Improvement'  => 'badge-average',
                    'Failed'             => 'badge-failed',
                  ];
                ?>
                <tr>
                  <td>
                    <div class="d-flex align-center gap-3">
                      <div class="stat-icon primary"
                           style="width:32px;height:32px;
                                  font-size:0.75rem;flex-shrink:0">
                        <i class="fas fa-book"></i>
                      </div>
                      <div>
                        <div class="font-semibold text-sm">
                          <?= htmlspecialchars($sub['subject_name']) ?>
                        </div>
                        <div class="text-xs text-muted">
                          <?= htmlspecialchars($sub['subject_code']) ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <?php if ($sub['teacher_fname']): ?>
                      <div class="text-sm">
                        <?= htmlspecialchars(
                          $sub['teacher_lname'] . ', '
                          . $sub['teacher_fname']
                        ) ?>
                      </div>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge badge-info">
                      <?= $sub['units'] ?>
                    </span>
                  </td>
                  <td><?= $sub['prelim']     ?? '—' ?></td>
                  <td><?= $sub['midterm']    ?? '—' ?></td>
                  <td><?= $sub['prefinal']   ?? '—' ?></td>
                  <td><?= $sub['final_exam'] ?? '—' ?></td>
                  <td>
                    <?php if ($hasGrade): ?>
                      <span class="grade-score <?= $scoreClass ?>"
                            data-score="<?= $grade ?>">
                        <?= number_format($grade, 2) ?>
                      </span>
                    <?php else: ?>
                      <span class="badge badge-muted">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($hasGrade && $sub['standing']): ?>
                      <span class="badge
                        <?= $standBadgeMap[$sub['standing']] ?? 'badge-muted' ?>">
                        <?= htmlspecialchars($sub['standing']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Semester GPA Summary Footer -->
        <?php if ($totalGraded > 0): ?>
          <div class="glass-card-footer d-flex
                      align-center justify-between flex-wrap gap-3">
            <div class="d-flex gap-5">
              <div>
                <span class="text-xs text-muted">Semester GPA</span>
                <div class="font-bold" style="color:<?= $ringColor ?>">
                  <?= $currentGPA ?>
                </div>
              </div>
              <div>
                <span class="text-xs text-muted">Graded</span>
                <div class="font-bold">
                  <?= $totalGraded ?> / <?= $totalSubjects ?>
                </div>
              </div>
              <div>
                <span class="text-xs text-muted">Status</span>
                <div>
                  <span class="badge <?= $standBadge ?>">
                    <?= $currentStanding ?>
                  </span>
                </div>
              </div>
            </div>
            <a href="grades.php" class="btn btn-primary btn-sm">
              <i class="fas fa-history"></i>
              Full Grade History
            </a>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>

  </div><!-- /page-content -->
</div><!-- /main-content -->

<?php include '../shared/footer.php'; ?>