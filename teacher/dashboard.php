<?php
// ============================================================
// teacher/dashboard.php — Teacher Home
// ============================================================

require_once '../middleware/TeacherMiddleware.php';
require_once '../models/Teacher.php';
require_once '../models/Grade.php';
require_once '../helpers/Auth.php';

TeacherMiddleware::handle();

$db           = DB::getInstance();
$teacherModel = new Teacher();
$gradeModel   = new Grade();

// ── Get Teacher Profile ───────────────────────────────────────
$userId  = Auth::id();
$teacher = $teacherModel->getByUserId($userId);

if (!$teacher) {
    Auth::logout();
    header('Location: ../auth/login.php');
    exit();
}

$teacherId = $teacher['id'];

// ── Active Semester ───────────────────────────────────────────
$activeSemester = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$semesterId = $activeSemester['id'] ?? 0;

// ── My Subjects This Semester ─────────────────────────────────
$mySubjects = $teacherModel->getSubjects($teacherId, $semesterId);

// ── Total Students Across My Subjects ────────────────────────
$totalMyStudents = 0;
foreach ($mySubjects as $sub) {
    $totalMyStudents += (int) $sub['enrolled_count'];
}

// ── Grades Submitted ─────────────────────────────────────────
$gradedCount = (int) $db->prepare(
    "SELECT COUNT(DISTINCT g.id)
     FROM   grades g
     JOIN   enrollments e  ON e.id = g.enrollment_id
     JOIN   teacher_subjects ts ON ts.subject_id = e.subject_id
                AND ts.semester_id = e.semester_id
     WHERE  ts.teacher_id  = :teacher_id
     AND    e.semester_id  = :semester_id"
)->execute([
    ':teacher_id'  => $teacherId,
    ':semester_id' => $semesterId,
]) ? $db->query(
    "SELECT COUNT(DISTINCT g.id)
     FROM   grades g
     JOIN   enrollments e  ON e.id = g.enrollment_id
     JOIN   teacher_subjects ts ON ts.subject_id = e.subject_id
                AND ts.semester_id = e.semester_id
     WHERE  ts.teacher_id  = {$teacherId}
     AND    e.semester_id  = {$semesterId}"
)->fetchColumn() : 0;

// Cleaner approach
$stmt = $db->prepare(
    "SELECT COUNT(DISTINCT g.id) AS graded,
            COUNT(DISTINCT e.id) AS total
     FROM   enrollments e
     JOIN   teacher_subjects ts ON ts.subject_id  = e.subject_id
                AND ts.semester_id = e.semester_id
     LEFT JOIN grades g ON g.enrollment_id = e.id
     WHERE  ts.teacher_id  = :teacher_id
     AND    e.semester_id  = :semester_id"
);
$stmt->execute([
    ':teacher_id'  => $teacherId,
    ':semester_id' => $semesterId,
]);
$gradeStats = $stmt->fetch(PDO::FETCH_ASSOC);
$gradedCount  = (int) ($gradeStats['graded'] ?? 0);
$totalStudentsEnrolled = (int) ($gradeStats['total'] ?? 0);

// ── Average Grade Across My Subjects ─────────────────────────
$stmt = $db->prepare(
    "SELECT AVG(g.final_grade) AS avg_grade
     FROM   grades g
     JOIN   enrollments e ON e.id = g.enrollment_id
     JOIN   teacher_subjects ts ON ts.subject_id  = e.subject_id
                AND ts.semester_id = e.semester_id
     WHERE  ts.teacher_id  = :teacher_id
     AND    e.semester_id  = :semester_id"
);
$stmt->execute([
    ':teacher_id'  => $teacherId,
    ':semester_id' => $semesterId,
]);
$avgGrade = round((float) $stmt->fetchColumn(), 2);

// ── Pass / Fail Count ─────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT
        SUM(g.final_grade >= 75) AS passed,
        SUM(g.final_grade <  75) AS failed
     FROM   grades g
     JOIN   enrollments e ON e.id = g.enrollment_id
     JOIN   teacher_subjects ts ON ts.subject_id  = e.subject_id
                AND ts.semester_id = e.semester_id
     WHERE  ts.teacher_id  = :teacher_id
     AND    e.semester_id  = :semester_id"
);
$stmt->execute([
    ':teacher_id'  => $teacherId,
    ':semester_id' => $semesterId,
]);
$passFailStats = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Recent Grading Activity ───────────────────────────────────
$stmt = $db->prepare(
    "SELECT g.updated_at,
            s.first_name, s.last_name,
            sub.name AS subject_name,
            sub.code AS subject_code,
            g.final_grade,
            g.standing
     FROM   grades g
     JOIN   enrollments e  ON e.id = g.enrollment_id
     JOIN   students s     ON s.id = e.student_id
     JOIN   subjects sub   ON sub.id = e.subject_id
     JOIN   teacher_subjects ts ON ts.subject_id  = e.subject_id
                AND ts.semester_id = e.semester_id
     WHERE  ts.teacher_id  = :teacher_id
     AND    e.semester_id  = :semester_id
     ORDER  BY g.updated_at DESC
     LIMIT  8"
);
$stmt->execute([
    ':teacher_id'  => $teacherId,
    ':semester_id' => $semesterId,
]);
$recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Grade Distribution for Chart ─────────────────────────────
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
    ':semester_id' => $semesterId,
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

$passFailChartData = json_encode([
    'passed' => (int)($passFailStats['passed'] ?? 0),
    'failed' => (int)($passFailStats['failed'] ?? 0),
]);

$pageTitle    = 'Teacher Dashboard';
$teacherName  = $teacher['first_name'] . ' ' . $teacher['last_name'];
$greetingHour = (int) date('H');
$greeting     = $greetingHour < 12 ? 'Good Morning'
              : ($greetingHour < 17 ? 'Good Afternoon'
              : 'Good Evening');

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
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-subtitle">
          <?= htmlspecialchars($activeSemester['name'] ?? 'No active semester') ?>
          <?= $activeSemester ? '(' . htmlspecialchars($activeSemester['school_year']) . ')' : '' ?>
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <button class="topbar-btn" id="notif-btn" data-tooltip="Notifications">
        <i class="fas fa-bell"></i>
      </button>
      <div class="dropdown">
        <button class="topbar-btn" id="user-menu-btn">
          <div class="avatar avatar-sm avatar-primary">
            <?= strtoupper(substr($teacher['first_name'], 0, 1)
              . substr($teacher['last_name'],  0, 1)) ?>
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
      background: linear-gradient(135deg,
        rgba(108,99,255,0.15) 0%,
        rgba(0,212,170,0.08) 100%);
      border-color: rgba(108,99,255,0.25);">
      <div class="glass-card-body">
        <div class="d-flex align-center justify-between flex-wrap gap-4">
          <div>
            <div class="text-muted text-sm mb-1">
              <?= $greeting ?>, 👋
            </div>
            <h2 style="font-size:1.4rem;font-weight:800;
                       letter-spacing:-0.03em;margin-bottom:4px">
              <?= htmlspecialchars('Prof. ' . $teacherName) ?>
            </h2>
            <p class="text-secondary text-sm">
              You have
              <strong style="color:var(--primary-light)">
                <?= count($mySubjects) ?> subject(s)
              </strong>
              this semester with
              <strong style="color:var(--accent-teal)">
                <?= $totalStudentsEnrolled ?> student(s)
              </strong>
              to grade.
            </p>
          </div>
          <div class="d-flex gap-3">
            <a href="my_subjects.php" class="btn btn-primary">
              <i class="fas fa-book-open"></i> My Subjects
            </a>
            <a href="manage_grades.php" class="btn btn-secondary">
              <i class="fas fa-pen"></i> Enter Grades
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card primary">
        <div class="stat-icon primary">
          <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">My Subjects</div>
          <div class="stat-value"><?= count($mySubjects) ?></div>
          <div class="stat-change flat">
            This semester
          </div>
        </div>
      </div>

      <div class="stat-card info">
        <div class="stat-icon info">
          <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Total Students</div>
          <div class="stat-value"><?= $totalStudentsEnrolled ?></div>
          <div class="stat-change flat">Enrolled</div>
        </div>
      </div>

      <div class="stat-card success">
        <div class="stat-icon success">
          <i class="fas fa-check-double"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Grades Submitted</div>
          <div class="stat-value"><?= $gradedCount ?></div>
          <div class="stat-change flat">
            of <?= $totalStudentsEnrolled ?>
          </div>
        </div>
      </div>

      <div class="stat-card warning">
        <div class="stat-icon warning">
          <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Class Average</div>
          <div class="stat-value">
            <?= $avgGrade > 0 ? $avgGrade : '—' ?>
          </div>
          <div class="stat-change flat">
            <?= $avgGrade >= 75 ? '✅ Passing' : ($avgGrade > 0 ? '⚠️ Below passing' : 'No grades yet') ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts + Subjects Row -->
    <div class="grid-2 mb-6">
      <!-- Grade Distribution -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-chart-bar"></i>
            Grade Distribution
          </h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:240px">
            <canvas id="grade-distribution-chart"
                    data-chart-data='<?= $distChartData ?>'>
            </canvas>
          </div>
        </div>
      </div>

      <!-- Pass / Fail -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-chart-pie"></i>
            Pass / Fail Rate
          </h3>
        </div>
        <div class="glass-card-body">
          <div class="chart-container" style="height:240px">
            <canvas id="pass-fail-chart"
                    data-chart-data='<?= $passFailChartData ?>'>
            </canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- My Subjects + Recent Activity Row -->
    <div class="grid-2">

      <!-- My Subjects Quick List -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3><i class="fas fa-book-open"></i> My Subjects</h3>
          <a href="my_subjects.php" class="btn btn-secondary btn-sm">
            View All
          </a>
        </div>
        <div class="glass-card-body" style="padding:0">
          <?php if (empty($mySubjects)): ?>
            <div class="empty-state" style="padding:var(--space-8)">
              <div class="empty-state-icon">📚</div>
              <h3>No Subjects Assigned</h3>
              <p>Contact admin to assign subjects.</p>
            </div>
          <?php else: ?>
            <?php foreach (array_slice($mySubjects, 0, 5) as $sub): ?>
              <div style="
                display:flex;
                align-items:center;
                justify-content:space-between;
                padding:var(--space-4) var(--space-5);
                border-bottom:1px solid var(--glass-border);
                transition:var(--transition-fast);"
                onmouseover="this.style.background='var(--glass-bg-hover)'"
                onmouseout="this.style.background=''">
                <div class="d-flex align-center gap-3">
                  <div class="stat-icon primary"
                       style="width:36px;height:36px;font-size:0.85rem">
                    <i class="fas fa-book"></i>
                  </div>
                  <div>
                    <div class="font-semibold text-sm">
                      <?= htmlspecialchars($sub['subject_name']) ?>
                    </div>
                    <div class="text-xs text-muted">
                      <?= htmlspecialchars($sub['subject_code']) ?>
                      · <?= $sub['units'] ?> units
                    </div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="badge badge-info">
                    <?= $sub['enrolled_count'] ?> students
                  </div>
                  <div class="mt-1">
                    <a href="manage_grades.php?subject_id=<?= $sub['subject_id'] ?>&semester_id=<?= $sub['semester_id'] ?>"
                       class="btn btn-primary btn-xs">
                      Grade
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-history"></i> Recent Grading
          </h3>
        </div>
        <div class="glass-card-body" style="padding:0">
          <?php if (empty($recentActivity)): ?>
            <div class="empty-state" style="padding:var(--space-8)">
              <div class="empty-state-icon">📝</div>
              <h3>No Grades Yet</h3>
              <p>Start entering grades for your students.</p>
            </div>
          <?php else: ?>
            <?php foreach ($recentActivity as $activity): ?>
              <?php
                $score = (float) $activity['final_grade'];
                $scoreClass = $score >= 90 ? 'success'
                            : ($score >= 80 ? 'info'
                            : ($score >= 75 ? 'warning'
                            : 'danger'));
              ?>
              <div style="
                display:flex;
                align-items:center;
                justify-content:space-between;
                padding:var(--space-3) var(--space-5);
                border-bottom:1px solid var(--glass-border)">
                <div class="d-flex align-center gap-3">
                  <div class="avatar avatar-sm avatar-<?= $scoreClass ?>">
                    <?= strtoupper(
                      substr($activity['first_name'], 0, 1)
                      . substr($activity['last_name'],  0, 1)
                    ) ?>
                  </div>
                  <div>
                    <div class="text-sm font-semibold">
                      <?= htmlspecialchars(
                        $activity['last_name'] . ', '
                        . $activity['first_name']
                      ) ?>
                    </div>
                    <div class="text-xs text-muted">
                      <?= htmlspecialchars($activity['subject_code']) ?>
                      · <?= date('M d, g:i A',
                          strtotime($activity['updated_at'])) ?>
                    </div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="grade-score <?= $scoreClass == 'success' ? 'excellent' : $scoreClass ?>">
                    <?= number_format($score, 2) ?>
                  </div>
                  <div class="text-xs text-muted">
                    <?= htmlspecialchars($activity['standing']) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /grid-2 -->

  </div><!-- /page-content -->
</div><!-- /main-content -->

<?php include '../shared/footer.php'; ?>