<?php
// ============================================================
// teacher/dashboard.php — Teacher Home
// ============================================================

require_once '../middleware/TeacherMiddleware.php';
require_once '../models/Teacher.php';
require_once '../helpers/Auth.php';

TeacherMiddleware::handle();

$db           = DB::getInstance();
$teacherModel = new Teacher();

$userId  = Auth::getUserId();
$teacher = $teacherModel->getByUserId($userId);

if (!$teacher) {
    Auth::logout();
    App::redirect('/auth/login.php');
}

$teacherId = $teacher['id'];

// ── Active Semester ───────────────────────────────────────────
$activeSemester = $db->query(
    "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$semesterId = $activeSemester['id'] ?? 0;

// ── My Subjects: check both teacher_subjects AND subjects.teacher_id ─
$mySubjects = $db->prepare(
    "SELECT DISTINCT
            sub.id          AS subject_id,
            sub.code        AS subject_code,
            sub.name        AS subject_name,
            sub.units,
            sem.id          AS semester_id,
            sem.name        AS semester_name,
            sem.school_year,
            COUNT(e.id)     AS enrolled_count
     FROM   subjects sub
     JOIN   semesters sem ON sem.id = :semester_id
     LEFT JOIN enrollments e ON e.subject_id = sub.id AND e.semester_id = sem.id
     WHERE  sub.teacher_id = :teacher_id
        OR  sub.id IN (
              SELECT subject_id FROM teacher_subjects
              WHERE teacher_id = :teacher_id2 AND semester_id = :semester_id2
            )
     GROUP  BY sub.id, sem.id
     ORDER  BY sub.name"
);
$mySubjects->execute([
    ':teacher_id'   => $teacherId,
    ':semester_id'  => $semesterId,
    ':teacher_id2'  => $teacherId,
    ':semester_id2' => $semesterId,
]);
$mySubjects = $mySubjects->fetchAll(PDO::FETCH_ASSOC);

$totalStudentsEnrolled = array_sum(array_column($mySubjects, 'enrolled_count'));

// ── Grades submitted vs total ─────────────────────────────────
$subjectIds = array_column($mySubjects, 'subject_id');
$gradedCount = 0;
if (!empty($subjectIds)) {
    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $params = array_merge($subjectIds, [$semesterId]);
    $row = $db->prepare(
        "SELECT COUNT(DISTINCT g.id) AS graded
         FROM   grades g
         JOIN   enrollments e ON e.id = g.enrollment_id
         WHERE  e.subject_id IN ({$placeholders})
         AND    e.semester_id = ?"
    );
    $row->execute($params);
    $gradedCount = (int) $row->fetchColumn();
}

// ── Class average ─────────────────────────────────────────────
$avgGrade = 0;
if (!empty($subjectIds)) {
    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $params = array_merge($subjectIds, [$semesterId]);
    $row = $db->prepare(
        "SELECT AVG(g.final_grade) AS avg_grade
         FROM   grades g
         JOIN   enrollments e ON e.id = g.enrollment_id
         WHERE  e.subject_id IN ({$placeholders})
         AND    e.semester_id = ?"
    );
    $row->execute($params);
    $avgGrade = round((float) $row->fetchColumn(), 2);
}

// ── Pass / Fail ───────────────────────────────────────────────
$passed = $failed = 0;
if (!empty($subjectIds)) {
    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $params = array_merge($subjectIds, [$semesterId]);
    $row = $db->prepare(
        "SELECT
            SUM(g.final_grade >= 75) AS passed,
            SUM(g.final_grade <  75) AS failed
         FROM   grades g
         JOIN   enrollments e ON e.id = g.enrollment_id
         WHERE  e.subject_id IN ({$placeholders})
         AND    e.semester_id = ?"
    );
    $row->execute($params);
    $pf = $row->fetch(PDO::FETCH_ASSOC);
    $passed = (int)($pf['passed'] ?? 0);
    $failed = (int)($pf['failed'] ?? 0);
}

// ── Recent grading activity ───────────────────────────────────
$recentActivity = [];
if (!empty($subjectIds)) {
    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $params = array_merge($subjectIds, [$semesterId]);
    $stmt = $db->prepare(
        "SELECT g.updated_at,
                s.first_name, s.last_name,
                sub.name AS subject_name,
                sub.code AS subject_code,
                g.final_grade,
                g.remarks
         FROM   grades g
         JOIN   enrollments e ON e.id = g.enrollment_id
         JOIN   students s    ON s.id = e.student_id
         JOIN   subjects sub  ON sub.id = e.subject_id
         WHERE  e.subject_id IN ({$placeholders})
         AND    e.semester_id = ?
         AND    g.final_grade IS NOT NULL
         ORDER  BY g.updated_at DESC
         LIMIT  8"
    );
    $stmt->execute($params);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle   = 'Teacher Dashboard';
$teacherName = trim($teacher['first_name'] . ' ' . $teacher['last_name']);
$greeting    = (int)date('H') < 12 ? 'Good Morning'
             : ((int)date('H') < 17 ? 'Good Afternoon' : 'Good Evening');

include '../shared/header.php';
?>

<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-mobile-menu"><i class="fas fa-bars"></i></button>
      <div>
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-subtitle">
          <?= htmlspecialchars($activeSemester['name'] ?? 'No active semester') ?>
          <?= $activeSemester ? ' (' . htmlspecialchars($activeSemester['school_year']) . ')' : '' ?>
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="dropdown">
        <button class="topbar-btn" id="user-menu-btn">
          <div class="avatar avatar-sm avatar-primary">
            <?= strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)) ?>
          </div>
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

    <!-- Welcome Banner -->
    <div class="glass-card mb-6" style="background:linear-gradient(135deg,rgba(108,99,255,0.15),rgba(0,212,170,0.08));border-color:rgba(108,99,255,0.25)">
      <div class="glass-card-body">
        <div class="d-flex align-center justify-between flex-wrap gap-4">
          <div>
            <div class="text-muted text-sm mb-1"><?= $greeting ?>, 👋</div>
            <h2 style="font-size:1.4rem;font-weight:800;letter-spacing:-0.03em;margin-bottom:4px">
              Prof. <?= htmlspecialchars($teacherName) ?>
            </h2>
            <p class="text-secondary text-sm">
              You have <strong style="color:var(--primary-light)"><?= count($mySubjects) ?> subject(s)</strong>
              this semester with <strong style="color:var(--accent-teal)"><?= $totalStudentsEnrolled ?> student(s)</strong> to grade.
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
    <div class="stats-grid mb-6">
      <div class="stat-card primary">
        <div class="stat-icon primary"><i class="fas fa-book"></i></div>
        <div class="stat-info">
          <div class="stat-label">My Subjects</div>
          <div class="stat-value"><?= count($mySubjects) ?></div>
          <div class="stat-change flat">This semester</div>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon info"><i class="fas fa-users"></i></div>
        <div class="stat-info">
          <div class="stat-label">Total Students</div>
          <div class="stat-value"><?= $totalStudentsEnrolled ?></div>
          <div class="stat-change flat">Enrolled</div>
        </div>
      </div>
      <div class="stat-card success">
        <div class="stat-icon success"><i class="fas fa-check-double"></i></div>
        <div class="stat-info">
          <div class="stat-label">Grades Submitted</div>
          <div class="stat-value"><?= $gradedCount ?></div>
          <div class="stat-change flat">of <?= $totalStudentsEnrolled ?></div>
        </div>
      </div>
      <div class="stat-card warning">
        <div class="stat-icon warning"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info">
          <div class="stat-label">Class Average</div>
          <div class="stat-value"><?= $avgGrade > 0 ? $avgGrade : '—' ?></div>
          <div class="stat-change flat">
            <?= $avgGrade >= 75 ? '✅ Passing' : ($avgGrade > 0 ? '⚠️ Below passing' : 'No grades yet') ?>
          </div>
        </div>
      </div>
      <div class="stat-card success">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
          <div class="stat-label">Passed</div>
          <div class="stat-value"><?= $passed ?></div>
          <div class="stat-change flat">students</div>
        </div>
      </div>
      <div class="stat-card danger">
        <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info">
          <div class="stat-label">Failed</div>
          <div class="stat-value"><?= $failed ?></div>
          <div class="stat-change flat">students</div>
        </div>
      </div>
    </div>

    <!-- My Subjects + Recent Activity -->
    <div class="grid-2">

      <!-- My Subjects -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3><i class="fas fa-book-open"></i> My Subjects</h3>
          <a href="my_subjects.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="glass-card-body" style="padding:0">
          <?php if (empty($mySubjects)): ?>
            <div class="empty-state" style="padding:var(--space-8)">
              <div class="empty-state-icon">📚</div>
              <h3>No Subjects Assigned</h3>
              <p>Contact admin to assign subjects.</p>
            </div>
          <?php else: ?>
            <?php foreach (array_slice($mySubjects, 0, 6) as $sub): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;
                          padding:var(--space-4) var(--space-5);
                          border-bottom:1px solid var(--glass-border)"
                   onmouseover="this.style.background='var(--glass-bg-hover)'"
                   onmouseout="this.style.background=''">
                <div class="d-flex align-center gap-3">
                  <div class="stat-icon primary" style="width:36px;height:36px;font-size:0.85rem">
                    <i class="fas fa-book"></i>
                  </div>
                  <div>
                    <div class="font-semibold text-sm"><?= htmlspecialchars($sub['subject_name']) ?></div>
                    <div class="text-xs text-muted"><?= htmlspecialchars($sub['subject_code']) ?> · <?= $sub['units'] ?> units</div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="badge badge-info mb-1"><?= $sub['enrolled_count'] ?> students</div>
                  <div>
                    <a href="manage_grades.php?subject_id=<?= $sub['subject_id'] ?>"
                       class="btn btn-primary btn-xs">Grade</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent Grading -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3><i class="fas fa-history"></i> Recent Grading</h3>
          <a href="reports.php" class="btn btn-secondary btn-sm">Full Report</a>
        </div>
        <div class="glass-card-body" style="padding:0">
          <?php if (empty($recentActivity)): ?>
            <div class="empty-state" style="padding:var(--space-8)">
              <div class="empty-state-icon">📝</div>
              <h3>No Grades Yet</h3>
              <p>Start entering grades for your students.</p>
            </div>
          <?php else: ?>
            <?php foreach ($recentActivity as $act):
              $score = (float)$act['final_grade'];
              $cls   = $score >= 90 ? 'success' : ($score >= 80 ? 'info' : ($score >= 75 ? 'warning' : 'danger'));
            ?>
              <div style="display:flex;align-items:center;justify-content:space-between;
                          padding:var(--space-3) var(--space-5);
                          border-bottom:1px solid var(--glass-border)">
                <div class="d-flex align-center gap-3">
                  <div class="avatar avatar-sm avatar-<?= $cls ?>">
                    <?= strtoupper(substr($act['first_name'], 0, 1) . substr($act['last_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div class="text-sm font-semibold">
                      <?= htmlspecialchars($act['last_name'] . ', ' . $act['first_name']) ?>
                    </div>
                    <div class="text-xs text-muted">
                      <?= htmlspecialchars($act['subject_code']) ?>
                      · <?= $act['updated_at'] ? date('M d, g:i A', strtotime($act['updated_at'])) : '—' ?>
                    </div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="font-bold" style="color:<?= $score >= 75 ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= number_format($score, 2) ?>
                  </div>
                  <span class="badge <?= $score >= 75 ? 'badge-passed' : 'badge-failed' ?>">
                    <?= $act['remarks'] ?? ($score >= 75 ? 'Passed' : 'Failed') ?>
                  </span>
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
