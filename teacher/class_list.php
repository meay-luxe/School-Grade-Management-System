<?php
// ============================================================
// teacher/class_list.php — Students in Teacher's Subject
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

$teacherId  = $teacher['id'];
$subjectId  = (int) ($_GET['subject_id']  ?? 0);
$semesterId = (int) ($_GET['semester_id'] ?? 0);

// ── Validate this subject belongs to teacher ──────────────────
if ($subjectId && $semesterId) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM teacher_subjects
         WHERE teacher_id  = :teacher_id
         AND   subject_id  = :subject_id
         AND   semester_id = :semester_id"
    );
    $stmt->execute([
        ':teacher_id'  => $teacherId,
        ':subject_id'  => $subjectId,
        ':semester_id' => $semesterId,
    ]);
    if ((int) $stmt->fetchColumn() === 0) {
        header('Location: my_subjects.php');
        exit();
    }
}

// ── My Subjects Dropdown ──────────────────────────────────────
$mySubjects = $teacherModel->getSubjects($teacherId);

// ── Subject Info ──────────────────────────────────────────────
$subjectInfo = null;
if ($subjectId) {
    $stmt = $db->prepare(
        "SELECT sub.*, sem.name AS semester_name, sem.school_year
         FROM   subjects sub
         JOIN   semesters sem ON sem.id = :semester_id
         WHERE  sub.id = :subject_id"
    );
    $stmt->execute([
        ':subject_id'  => $subjectId,
        ':semester_id' => $semesterId,
    ]);
    $subjectInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Class List ────────────────────────────────────────────────
$classList = [];
if ($subjectId && $semesterId) {
    $stmt = $db->prepare(
        "SELECT s.id AS student_id,
                s.first_name,
                s.last_name,
                s.student_number,
                s.year_level,
                s.section,
                s.course,
                s.contact_no,
                u.email,
                e.id   AS enrollment_id,
                e.status,
                g.final_grade,
                g.gpa,
                g.standing,
                g.is_locked,
                g.prelim,
                g.midterm,
                g.prefinal,
                g.final_exam
         FROM   enrollments e
         JOIN   students s ON s.id = e.student_id
         JOIN   users    u ON u.id = s.user_id
         LEFT JOIN grades g ON g.enrollment_id = e.id
         WHERE  e.subject_id  = :subject_id
         AND    e.semester_id = :semester_id
         ORDER  BY s.last_name, s.first_name"
    );
    $stmt->execute([
        ':subject_id'  => $subjectId,
        ':semester_id' => $semesterId,
    ]);
    $classList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Class Stats ───────────────────────────────────────────────
$totalEnrolled = count($classList);
$totalGraded   = count(array_filter($classList, fn($s) => $s['final_grade'] !== null));
$totalPassed   = count(array_filter($classList, fn($s) => ($s['final_grade'] ?? 0) >= 75));
$totalFailed   = count(array_filter($classList, fn($s) => $s['final_grade'] !== null && $s['final_grade'] < 75));
$classAvg      = $totalGraded > 0
    ? round(
        array_sum(array_column(
            array_filter($classList, fn($s) => $s['final_grade'] !== null),
            'final_grade'
        )) / $totalGraded,
        2
      )
    : 0;

$pageTitle = 'Class List';

include '../shared/header.php';
?>

<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-mobile-menu">
        <i class="fas fa-bars"></i>
      </button>
      <div>
        <div class="topbar-title">Class List</div>
        <div class="topbar-subtitle">
          <?= $subjectInfo
              ? htmlspecialchars($subjectInfo['name'])
              : 'Select a subject' ?>
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <?php if (!empty($classList)): ?>
        <button class="btn btn-secondary btn-sm"
                onclick="CSVExport.fromTable('class-list-table',
                  '<?= htmlspecialchars($subjectInfo['code'] ?? 'class') ?>_list.csv')">
          <i class="fas fa-download"></i> Export
        </button>
        <button class="btn btn-secondary btn-sm"
                onclick="PrintHelper.print('class-list-table')">
          <i class="fas fa-print"></i> Print
        </button>
      <?php endif; ?>
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
          <div class="breadcrumb-item">
            <a href="my_subjects.php">My Subjects</a>
          </div>
          <span class="breadcrumb-separator">›</span>
          <div class="breadcrumb-item active">Class List</div>
        </div>
        <h1>Class List</h1>
        <?php if ($subjectInfo): ?>
          <p>
            <?= htmlspecialchars($subjectInfo['code']) ?>
            — <?= htmlspecialchars($subjectInfo['name']) ?>
            · <?= htmlspecialchars($subjectInfo['semester_name']) ?>
            (<?= htmlspecialchars($subjectInfo['school_year']) ?>)
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Subject Selector -->
    <div class="glass-card mb-5">
      <div class="glass-card-body">
        <form method="GET" class="d-flex gap-4 align-center flex-wrap">
          <div class="form-group" style="flex:1;min-width:220px;margin-bottom:0">
            <label class="form-label">Select Subject</label>
            <select name="subject_id"
                    class="form-control"
                    onchange="
                      const sel = this.options[this.selectedIndex];
                      document.getElementById('sem-hidden').value
                        = sel.dataset.semId || '';
                      this.form.submit();">
              <option value="">— Choose Subject —</option>
              <?php foreach ($mySubjects as $sub): ?>
                <option value="<?= $sub['subject_id'] ?>"
                        data-sem-id="<?= $sub['semester_id'] ?>"
                  <?= ($subjectId == $sub['subject_id']
                       && $semesterId == $sub['semester_id'])
                      ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sub['subject_code']) ?>
                  — <?= htmlspecialchars($sub['subject_name']) ?>
                  (<?= htmlspecialchars($sub['semester_name']) ?>
                   <?= htmlspecialchars($sub['school_year']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <input type="hidden" name="semester_id"
                 id="sem-hidden" value="<?= $semesterId ?>">
        </form>
      </div>
    </div>

    <?php if (!empty($classList)): ?>
      <!-- Class Stats -->
      <div class="stats-grid mb-6">
        <div class="stat-card primary">
          <div class="stat-icon primary">
            <i class="fas fa-users"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Enrolled</div>
            <div class="stat-value"><?= $totalEnrolled ?></div>
          </div>
        </div>
        <div class="stat-card success">
          <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Passed</div>
            <div class="stat-value"><?= $totalPassed ?></div>
          </div>
        </div>
        <div class="stat-card danger">
          <div class="stat-icon danger">
            <i class="fas fa-times-circle"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Failed</div>
            <div class="stat-value"><?= $totalFailed ?></div>
          </div>
        </div>
        <div class="stat-card warning">
          <div class="stat-icon warning">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Class Average</div>
            <div class="stat-value">
              <?= $classAvg > 0 ? $classAvg : '—' ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Class List Table -->
      <div class="glass-card">
        <div class="glass-card-header">
          <h3>
            <i class="fas fa-list"></i>
            Enrolled Students
          </h3>
          <div class="search-input-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text"
                   class="form-control"
                   placeholder="Search student..."
                   data-table-search="class-list-table">
          </div>
        </div>

        <div class="table-wrapper">
          <table class="data-table" id="class-list-table">
            <thead>
              <tr>
                <th>#</th>
                <th class="sortable">Student</th>
                <th class="sortable">ID No.</th>
                <th>Year</th>
                <th>Section</th>
                <th class="sortable">Final Grade</th>
                <th>Standing</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($classList as $i => $student): ?>
                <?php
                  $hasGrade = $student['final_grade'] !== null;
                  $grade    = (float) ($student['final_grade'] ?? 0);
                  $scoreClass = !$hasGrade ? ''
                      : ($grade >= 90 ? 'excellent'
                      : ($grade >= 80 ? 'good'
                      : ($grade >= 75 ? 'average'
                      : 'failed')));

                  $standBadge = [
                    'Excellent'          => 'badge-excellent',
                    'Good'               => 'badge-good',
                    'Satisfactory'       => 'badge-good',
                    'Fair'               => 'badge-average',
                    'Needs Improvement'  => 'badge-average',
                    'Failed'             => 'badge-failed',
                  ];
                ?>
                <tr>
                  <td class="text-muted"><?= $i + 1 ?></td>
                  <td>
                    <div class="user-cell">
                      <div class="avatar avatar-sm avatar-primary">
                        <?= strtoupper(
                          substr($student['first_name'], 0, 1)
                          . substr($student['last_name'],  0, 1)
                        ) ?>
                      </div>
                      <div class="user-cell-info">
                        <div class="name">
                          <?= htmlspecialchars(
                            $student['last_name'] . ', '
                            . $student['first_name']
                          ) ?>
                        </div>
                        <div class="sub">
                          <?= htmlspecialchars($student['email']) ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($student['student_number']) ?></td>
                  <td>Year <?= $student['year_level'] ?></td>
                  <td>
                    <?= htmlspecialchars($student['section'] ?? '—') ?>
                  </td>
                  <td>
                    <?php if ($hasGrade): ?>
                      <span class="grade-score <?= $scoreClass ?>">
                        <?= number_format($grade, 2) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-muted">Not graded</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($hasGrade && $student['standing']): ?>
                      <span class="badge <?=
                        $standBadge[$student['standing']] ?? 'badge-muted'
                      ?>">
                        <?= htmlspecialchars($student['standing']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($student['is_locked']): ?>
                      <span class="badge badge-warning">
                        <i class="fas fa-lock"></i> Locked
                      </span>
                    <?php elseif ($hasGrade): ?>
                      <span class="badge badge-success">Graded</span>
                    <?php else: ?>
                      <span class="badge badge-muted">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="manage_grades.php?subject_id=<?= $subjectId ?>&semester_id=<?= $semesterId ?>#student-<?= $student['enrollment_id'] ?>"
                       class="btn btn-primary btn-xs"
                       <?= $student['is_locked'] ? 'disabled' : '' ?>>
                      <i class="fas fa-pen"></i>
                      <?= $student['is_locked'] ? 'Locked' : 'Grade' ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php elseif ($subjectId): ?>
      <div class="glass-card">
        <div class="glass-card-body">
          <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <h3>No Students Enrolled</h3>
            <p>
              No students are enrolled in this subject yet.
              Contact admin to enroll students.
            </p>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="glass-card">
        <div class="glass-card-body">
          <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h3>Select a Subject</h3>
            <p>
              Choose a subject from the dropdown above
              to view the class list.
            </p>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php include '../shared/footer.php'; ?>