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
    header('Location: ../auth/login.php');
    exit();
}

$teacherId = $teacher['id'];

// ── Filters ───────────────────────────────────────────────────
$activeSemester   = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$semesters        = $db->query(
    "SELECT * FROM semesters ORDER BY school_year DESC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$filterSemesterId = (int) ($_GET['semester_id']
    ?? $activeSemester['id']
    ?? 0);

// ── My Subjects ───────────────────────────────────────────────
$mySubjects = $teacherModel->getSubjects($teacherId, $filterSemesterId ?: null);

// ── Overall Stats ─────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT
        COUNT(DISTINCT e.student_id)        AS total_students,
        COUNT(DISTINCT g.id)                AS total_graded,
        AVG(g.final_grade)                  AS overall_avg,
        SUM(g.final_grade >= 75)            AS total_passed,
        SUM(g.final_grade <  75)            AS total_failed,
        MAX(g.final_grade)                  AS highest,
        MIN(g.final_grade)                  AS lowest
     FROM   enrollments e
     JOIN   teacher_subjects ts ON ts.subject_id  = e.subject_id
                AND ts.semester_id = e.semester_id
     LEFT JOIN grades g ON g.enrollment_id = e.id
     WHERE  ts.teacher_id  = :teacher_id
     AND    e.semester_id  = :semester_id"
);
$stmt->execute([
    ':teacher_id'  => $teacherId,
    ':semester_id' => $filterSemesterId,
]);
$overallStats = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Per Subject Stats ─────────────────────────────────────────
$subjectStats = [];
foreach ($mySubjects as $sub) {
    $stmt = $db->prepare(
        "SELECT
            COUNT(e.id)              AS enrolled,
            COUNT(g.id)              AS graded,
            AVG(g.final_grade)       AS avg_grade,
            MAX(g.final_grade)       AS highest,
            MIN(g.final_grade)       AS lowest,
            SUM(g.final_grade >= 75) AS passed,
            SUM(g.final_grade <  75) AS failed
         FROM   enrollments e
         LEFT JOIN grades g ON g.enrollment_id = e.id
         WHERE  e.subject_id  = :subject_id
         AND    e.semester_id = :semester_id"
    );
    $stmt->execute([
        ':subject_id'  => $sub['subject_id'],
        ':semester_id' => $sub['semester_id'],
    ]);
    $stats                    = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['subject_name']    = $sub['subject_name'];
    $stats['subject_code']    = $sub['subject_code'];
    $stats['semester_name']   = $sub['semester_name'];
    $stats['school_year']     = $sub['school_year'];
    $stats['avg_grade']       = round((float) ($stats['avg_grade'] ?? 0), 2);
    $subjectStats[]           = $stats;
}

// ── Grade Distribution Chart ──────────────────────────────────
$stmt = $db->prepare(
    "SELECT
        SUM(CASE WHEN g.final_grade >= 90 THEN 1 ELSE 0 END) AS excellent,
        SUM(CASE WHEN g.final_grade BETWEEN 80 AND 89 THEN 1 ELSE 0 END) AS good,
        SUM(CASE WHEN g.final_grade BETWEEN 70 AND 79 THEN 1 ELSE 0 END) AS average,
        SUM(CASE WHEN g.final_grade BETWEEN 60 AND 69 THEN 1 ELSE 0 END) AS poor,
        SUM(CASE WHEN g.final_grade < 60 THEN 1 ELSE 0 END) AS failed
     FROM   grades g
     JOIN   enrollments e ON e.id = g.enrollment_id
     JOIN   teacher_subjects ts ON ts.subject_id  = e.subject_id
                AND ts.semester_id = e.semester_id
     WHERE  ts.teacher_id  = :teacher_id
     AND    e.semester_id  = :semester_id"
);
$stmt->execute([
    ':teacher_id'  => $teacherId,
    ':semester_id' => $filterSemesterId,
]);
$dist = $stmt->fetch(PDO::FETCH_ASSOC);

$distChartData = json_encode([
    'labels' => ['90-100', '80-89', '70-79', '60-69', 'Below 60'],
    'values' => [
        (int)($dist['excellent'] ?? 0),
        (int)($dist['good']      ?? 0),
        (int)($dist['average']   ?? 0),
        (int)($dist['poor']      ?? 0),
        (int)($dist['failed']    ?? 0),
    ],
]);

// ── Subject Comparison Chart ──────────────────────────────────
$comparisonData = json_encode([
    'labels'   => array_column($subjectStats, 'subject_code'),
    'averages' => array_column($subjectStats, 'avg_grade'),
    'highest'  => array_map(fn($s) => round((float)($s['highest'] ?? 0), 2), $subjectStats),
    'lowest'   => array_map(fn($s) => round((float)($s['lowest']  ?? 0), 2), $subjectStats),
]);

// ── Pass Fail Chart ───────────────────────────────────────────
$passFailData = json_encode([
    'passed' => (int)($overallStats['total_passed'] ?? 0),
    'failed' => (int)($overallStats['total_failed'] ?? 0),
]);

// ── Top Students ──────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT s.first_name, s.last_name,
            s.student_number,
            AVG(g.final_grade)  AS avg_grade,
            COUNT(g.id)         AS subjects_count
     FROM   grades g
     JOIN   enrollments e ON e.id = g.enrollment_id
     JOIN   students s    ON s.id = e.student_id
     JOIN   teacher_subjects ts ON ts.subject_id  = e.subject_id
                AND ts.semester_id = e.semester_id
     WHERE  ts.teacher_id  = :teacher_id
     AND    e.semester_id  = :semester_id
     GROUP  BY s.id
     ORDER  BY avg_grade DESC
     LIMIT  5"
);
$stmt->execute([
    ':teacher_id'  => $teacherId,
    ':semester_id' => $filterSemesterId,
]);
$topStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Reports';

include '../shared/header.php';
?>

<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-mobile-menu">
        <i class="fas fa-bars"></i>
      </button>
      <div>
        <div class="topbar-title">My Reports</div>
        <div class="topbar-subtitle">Subject performance analytics</div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-secondary btn-sm"
              onclick="PrintHelper.print('report-body')">
        <i class="fas fa-print"></i> Print
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

  <div class="page-content" id="report-body">

    <div class="page-header">
      <div class="page-header-left">
        <div class="breadcrumb">
          <div class="breadcrumb-item">
            <a href="dashboard.php">Dashboard</a>
          </div>
          <span class="breadcrumb-separator">›</span>
          <div class="breadcrumb-item active">Reports</div>
        </div>
        <h1>My Analytics</h1>
        <p>Performance overview across your subjects</p>
      </div>
      <div class="page-header-right">
        <form method="GET">
          <select name="semester_id"
                  class="form-control"
                  onchange="this.form.submit()">
            <?php foreach ($semesters as $sem): ?>
              <option value="<?= $sem['id'] ?>"
                <?= $filterSemesterId == $sem['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($sem['name']) ?>
                (<?= htmlspecialchars($sem['school_year']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <!-- Overall Stats -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon primary">
          <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">My Subjects</div>
          <div class="stat-value"><?= count($mySubjects) ?></div>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon info">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Total Students</div>
          <div class="stat-value">
            <?= $overallStats['total_students'] ?? 0 ?>
          </div>
        </div>
      </div>
      <div class="stat-card success">
        <div class="stat-icon success">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Passed</div>
          <div class="stat-value">
            <?= $overallStats['total_passed'] ?? 0 ?>
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
            <?= $overallStats['total_failed'] ?? 0 ?>
          </div>
        </div>
      </div>
      <div class="stat-card warning">
        <div class="stat-icon warning">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Overall Average</div>
          <div class="stat-value">
            <?= isset($overallStats['overall_avg'])
                ? number_format($overallStats['overall_avg'], 2)
                : '—' ?>
          </div>
        </div>
      </div>
      <div class="stat-card teal">
        <div class="stat-icon teal">
          <i class="fas fa-trophy"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Highest Grade</div>
          <div class="stat-value">
            <?= isset($overallStats['highest'])
                ? number_format($overallStats['highest'], 2)
                : '—' ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts -->
    <div class="grid-2 mb-6">
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-chart-bar"></i> Grade Distribution
          </h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:250px">
            <canvas id="grade-distribution-chart"
                    data-chart-data='<?= $distChartData ?>'>
            </canvas>
          </div>
        </div>
      </div>

      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-chart-pie"></i> Pass / Fail Rate
          </h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:250px">
            <canvas id="pass-fail-chart"
                    data-chart-data='<?= $passFailData ?>'>
            </canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Subject Comparison -->
    <?php if (count($subjectStats) > 1): ?>
      <div class="glass-card mb-6">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-balance-scale"></i>
            Subject Grade Comparison
          </h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:280px">
            <canvas id="subject-comparison-chart"
                    data-chart-data='<?= $comparisonData ?>'>
            </canvas>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Per Subject Breakdown -->
    <div class="glass-card mb-6">
      <div class="glass-card-header">
        <h3>
          <i class="fas fa-table"></i> Subject Breakdown
        </h3>
      </div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Semester</th>
              <th class="sortable">Enrolled</th>
              <th class="sortable">Graded</th>
              <th class="sortable">Average</th>
              <th class="sortable">Highest</th>
              <th class="sortable">Lowest</th>
              <th class="sortable">Passed</th>
              <th class="sortable">Failed</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subjectStats as $stat): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($stat['subject_code']) ?></strong>
                  <div class="text-xs text-muted">
                    <?= htmlspecialchars($stat['subject_name']) ?>
                  </div>
                </td>
                <td>
                  <?= htmlspecialchars($stat['semester_name']) ?>
                  <div class="text-xs text-muted">
                    <?= htmlspecialchars($stat['school_year']) ?>
                  </div>
                </td>
                <td><?= $stat['enrolled'] ?></td>
                <td><?= $stat['graded'] ?></td>
                <td>
                  <span class="grade-score"
                        data-score="<?= $stat['avg_grade'] ?>">
                    <?= $stat['avg_grade'] > 0
                        ? $stat['avg_grade']
                        : '—' ?>
                  </span>
                </td>
                <td><?= $stat['highest'] ? number_format($stat['highest'], 2) : '—' ?></td>
                <td><?= $stat['lowest']  ? number_format($stat['lowest'],  2) : '—' ?></td>
                <td>
                  <span class="badge badge-success">
                    <?= $stat['passed'] ?>
                  </span>
                </td>
                <td>
                  <span class="badge badge-danger">
                    <?= $stat['failed'] ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top Students -->
    <?php if (!empty($topStudents)): ?>
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-medal"></i>
            Top Performing Students
          </h3>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Student</th>
                <th>ID No.</th>
                <th>Subjects</th>
                <th>Average Grade</th>
                <th>Standing</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topStudents as $i => $stu): ?>
                <tr>
                  <td>
                    <?= ['🥇','🥈','🥉'][$i] ?? ($i + 1) ?>
                  </td>
                  <td>
                    <div class="user-cell">
                      <div class="avatar avatar-sm avatar-primary">
                        <?= strtoupper(
                          substr($stu['first_name'], 0, 1)
                          . substr($stu['last_name'],  0, 1)
                        ) ?>
                      </div>
                      <div class="user-cell-info">
                        <div class="name">
                          <?= htmlspecialchars(
                            $stu['last_name'] . ', ' . $stu['first_name']
                          ) ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($stu['student_number']) ?></td>
                  <td>
                    <span class="badge badge-info">
                      <?= $stu['subjects_count'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="grade-score"
                          data-score="<?= $stu['avg_grade'] ?>">
                      <?= number_format($stu['avg_grade'], 2) ?>
                    </span>
                  </td>
                  <td>
                    <?php
                      $avg = (float) $stu['avg_grade'];
                      $standing = $avg >= 90 ? 'Excellent'
                                : ($avg >= 80 ? 'Good'
                                : ($avg >= 75 ? 'Satisfactory'
                                : 'Fair'));
                      $badgeMap = [
                        'Excellent'    => 'badge-excellent',
                        'Good'         => 'badge-good',
                        'Satisfactory' => 'badge-good',
                        'Fair'         => 'badge-average',
                      ];
                    ?>
                    <span class="badge <?= $badgeMap[$standing] ?>">
                      <?= $standing ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include '../shared/footer.php'; ?>