<?php
// ============================================================
// reports.php — Analytics & Charts (Admin)
// ============================================================

require_once '../middleware/AdminMiddleware.php';
require_once '../models/Grade.php';
require_once '../helpers/Auth.php';

AdminMiddleware::handle();

$db         = DB::getInstance();
$gradeModel = new Grade();

// ── Active Semester ───────────────────────────────────────────
$activeSemester   = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$filterSemesterId = (int) ($_GET['semester_id']
    ?? $activeSemester['id']
    ?? 0);

$semesters = $db->query(
    "SELECT * FROM semesters ORDER BY school_year DESC, id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// ── Summary Stats ─────────────────────────────────────────────
$totalStudents = (int) $db->query(
    "SELECT COUNT(*) FROM students s
     JOIN users u ON u.id = s.user_id WHERE u.is_active = 1"
)->fetchColumn();

$totalTeachers = (int) $db->query(
    "SELECT COUNT(*) FROM teachers t
     JOIN users u ON u.id = t.user_id WHERE u.is_active = 1"
)->fetchColumn();

$totalSubjects = (int) $db->query(
    "SELECT COUNT(*) FROM subjects WHERE is_active = 1"
)->fetchColumn();

// Grade summary
$summary     = $filterSemesterId
    ? $gradeModel->getSummary($filterSemesterId)
    : [];

$distribution = $filterSemesterId
    ? $gradeModel->getDistribution($filterSemesterId)
    : [];

// ── Grade Distribution Chart Data ─────────────────────────────
$distChartData = json_encode([
    'labels' => ['90-100', '80-89', '70-79', '60-69', 'Below 60'],
    'values' => [
        $distribution['excellent'] ?? 0,
        $distribution['good']      ?? 0,
        $distribution['average']   ?? 0,
        $distribution['poor']      ?? 0,
        $distribution['failed']    ?? 0,
    ],
]);

// ── Pass/Fail Chart Data ──────────────────────────────────────
$passFailData = json_encode([
    'passed' => $summary['passed_count'] ?? 0,
    'failed' => $summary['failed_count'] ?? 0,
]);

// ── Top Subjects by Average ───────────────────────────────────
$topSubjectsRaw = $db->prepare(
    "SELECT sub.name AS subject_name,
            sub.code,
            AVG(g.final_grade) AS avg_grade,
            COUNT(g.id)        AS grade_count
     FROM   grades g
     JOIN   enrollments e   ON e.id = g.enrollment_id
     JOIN   subjects sub    ON sub.id = e.subject_id
     WHERE  e.semester_id = :semester_id
     GROUP  BY sub.id
     ORDER  BY avg_grade DESC
     LIMIT  8"
);
$topSubjectsRaw->execute([':semester_id' => $filterSemesterId ?: 0]);
$topSubjects = $topSubjectsRaw->fetchAll(PDO::FETCH_ASSOC);

$topSubjectsData = json_encode([
    'labels'   => array_column($topSubjects, 'code'),
    'averages' => array_map(
        fn($r) => round((float) $r['avg_grade'], 2),
        $topSubjects
    ),
]);

// ── Enrollment Trend (by semester) ────────────────────────────
$trendRaw = $db->query(
    "SELECT sem.name AS sem_name,
            sem.school_year,
            COUNT(e.id) AS enrollments
     FROM   semesters sem
     LEFT JOIN enrollments e ON e.semester_id = sem.id
     GROUP  BY sem.id
     ORDER  BY sem.school_year, sem.id
     LIMIT  8"
)->fetchAll(PDO::FETCH_ASSOC);

$trendData = json_encode([
    'labels' => array_map(
        fn($r) => $r['sem_name'] . ' ' . $r['school_year'],
        $trendRaw
    ),
    'values' => array_column($trendRaw, 'enrollments'),
]);

// ── Subject Comparison ────────────────────────────────────────
$comparisonRaw = $db->prepare(
    "SELECT sub.code,
            AVG(g.final_grade) AS avg_grade,
            MAX(g.final_grade) AS max_grade,
            MIN(g.final_grade) AS min_grade
     FROM   grades g
     JOIN   enrollments e ON e.id = g.enrollment_id
     JOIN   subjects sub  ON sub.id = e.subject_id
     WHERE  e.semester_id = :semester_id
     GROUP  BY sub.id
     ORDER  BY sub.name
     LIMIT  8"
);
$comparisonRaw->execute([':semester_id' => $filterSemesterId ?: 0]);
$comparison = $comparisonRaw->fetchAll(PDO::FETCH_ASSOC);

$comparisonData = json_encode([
    'labels'   => array_column($comparison, 'code'),
    'averages' => array_map(fn($r) => round((float)$r['avg_grade'], 2), $comparison),
    'highest'  => array_map(fn($r) => round((float)$r['max_grade'], 2), $comparison),
    'lowest'   => array_map(fn($r) => round((float)$r['min_grade'], 2), $comparison),
]);

// ── Top Performing Students ───────────────────────────────────
$topStudents = $db->prepare(
    "SELECT s.first_name, s.last_name, s.student_id AS student_number,
            AVG(g.final_grade) AS avg_grade,
            COUNT(g.id)        AS subjects_graded
     FROM   grades g
     JOIN   enrollments e ON e.id = g.enrollment_id
     JOIN   students s    ON s.id = e.student_id
     WHERE  e.semester_id = :semester_id
     GROUP  BY s.id
     HAVING subjects_graded > 0
     ORDER  BY avg_grade DESC
     LIMIT  10"
);
$topStudents->execute([':semester_id' => $filterSemesterId ?: 0]);
$topStudents = $topStudents->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Reports & Analytics';

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
        <div class="topbar-title">Reports & Analytics</div>
        <div class="topbar-subtitle">
          Grade performance overview
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-secondary btn-sm"
              onclick="PrintHelper.print('reports-content')">
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

  <div class="page-content" id="reports-content">

    <div class="page-header">
      <div class="page-header-left">
        <div class="breadcrumb">
          <div class="breadcrumb-item">
            <a href="dashboard.php">Dashboard</a>
          </div>
          <span class="breadcrumb-separator">›</span>
          <div class="breadcrumb-item active">Reports</div>
        </div>
        <h1>Reports & Analytics</h1>
        <p>Performance overview for the selected semester</p>
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
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon primary">
          <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Total Students</div>
          <div class="stat-value"><?= $totalStudents ?></div>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon info">
          <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Total Teachers</div>
          <div class="stat-value"><?= $totalTeachers ?></div>
        </div>
      </div>
      <div class="stat-card success">
        <div class="stat-icon success">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Passed</div>
          <div class="stat-value">
            <?= $summary['passed_count'] ?? '—' ?>
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
            <?= $summary['failed_count'] ?? '—' ?>
          </div>
        </div>
      </div>
      <div class="stat-card warning">
        <div class="stat-icon warning">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Average Grade</div>
          <div class="stat-value">
            <?= isset($summary['average_grade'])
                ? number_format($summary['average_grade'], 2)
                : '—' ?>
          </div>
        </div>
      </div>
      <div class="stat-card teal">
        <div class="stat-icon teal">
          <i class="fas fa-book-open"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Total Subjects</div>
          <div class="stat-value"><?= $totalSubjects ?></div>
        </div>
      </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="grid-2 mb-6">
      <!-- Grade Distribution -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3><i class="fas fa-chart-bar"></i> Grade Distribution</h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:260px">
            <canvas id="grade-distribution-chart"
                    data-chart-data='<?= $distChartData ?>'>
            </canvas>
          </div>
        </div>
      </div>

      <!-- Pass/Fail -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3><i class="fas fa-chart-pie"></i> Pass / Fail Rate</h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:260px">
            <canvas id="pass-fail-chart"
                    data-chart-data='<?= $passFailData ?>'>
            </canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="grid-2 mb-6">
      <!-- Enrollment Trend -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-chart-line"></i> Enrollment Trend
          </h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:260px">
            <canvas id="enrollment-trend-chart"
                    data-chart-data='<?= $trendData ?>'>
            </canvas>
          </div>
        </div>
      </div>

      <!-- Top Subjects -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-trophy"></i> Subject Averages
          </h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:260px">
            <canvas id="top-subjects-chart"
                    data-chart-data='<?= $topSubjectsData ?>'>
            </canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Subject Comparison Full Width -->
    <div class="glass-card mb-6">
      <div class="glass-card-header">
        <h3>
          <i class="fas fa-balance-scale"></i>
          Subject Grade Comparison
        </h3>
      </div>
      <div class="glass-card-body">
        <div class="chart-container" style="height:300px">
          <canvas id="subject-comparison-chart"
                  data-chart-data='<?= $comparisonData ?>'>
          </canvas>
        </div>
      </div>
    </div>

    <!-- Top Performing Students -->
    <?php if (!empty($topStudents)): ?>
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-medal"></i>
            Top Performing Students
          </h3>
          <span class="badge badge-primary">
            Top <?= count($topStudents) ?>
          </span>
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
                    <?php if ($i === 0): ?>
                      <span style="font-size:1.2rem">🥇</span>
                    <?php elseif ($i === 1): ?>
                      <span style="font-size:1.2rem">🥈</span>
                    <?php elseif ($i === 2): ?>
                      <span style="font-size:1.2rem">🥉</span>
                    <?php else: ?>
                      <span class="text-muted"><?= $i + 1 ?></span>
                    <?php endif; ?>
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
                      <?= $stu['subjects_graded'] ?> subjects
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
                      $avg   = (float) $stu['avg_grade'];
                      $stand = $avg >= 90 ? 'Excellent'
                             : ($avg >= 80 ? 'Good'
                             : ($avg >= 75 ? 'Satisfactory'
                             : ($avg >= 70 ? 'Fair' : 'Needs Improvement')));
                      $badgeMap = [
                        'Excellent'    => 'badge-excellent',
                        'Good'         => 'badge-good',
                        'Satisfactory' => 'badge-good',
                        'Fair'         => 'badge-average',
                        'Needs Improvement' => 'badge-average',
                      ];
                    ?>
                    <span class="badge <?= $badgeMap[$stand] ?? 'badge-muted' ?>">
                      <?= $stand ?>
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