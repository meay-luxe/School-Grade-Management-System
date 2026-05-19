<?php
/* ============================================================
   GradeMS — Teacher: Manage Grades
   File: teacher/manage_grades.php
   ============================================================ */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/GradeCalculator.php';
require_once __DIR__ . '/../config/DB.php';

Auth::startSession();
Auth::requireRole('teacher');

$pageTitle  = 'Manage Grades';
$activePage = '/teacher/manage_grades.php';

$success = $error = '';

/* ── Identify this teacher's DB record ── */
$teacher = DB::fetchOne(
    'SELECT t.id FROM teachers t WHERE t.user_id = ?',
    [$_SESSION['user_id']]
);
$teacherId = $teacher['id'] ?? null;

/* ── Subject filter (from query string or default to first subject) ── */
$subjectId = (int)($_GET['subject_id'] ?? 0);

/* ── Verify teacher owns this subject ── */
if ($subjectId && $teacherId) {
    $ownsSubject = DB::fetchOne(
        'SELECT id FROM subjects WHERE id = ? AND teacher_id = ?',
        [$subjectId, $teacherId]
    );
    if (!$ownsSubject) {
        $subjectId = 0;
    }
}

/* ── Get teacher's subjects for dropdown ── */
$mySubjects = $teacherId ? DB::fetchAll(
    'SELECT s.id, s.code, s.name FROM subjects s
     WHERE s.teacher_id = ? ORDER BY s.code',
    [$teacherId]
) : [];

if (!$subjectId && !empty($mySubjects)) {
    $subjectId = $mySubjects[0]['id'];
}

$currentSubject = null;
if ($subjectId) {
    $currentSubject = DB::fetchOne('SELECT * FROM subjects WHERE id = ?', [$subjectId]);
}

/* ── HANDLE POST: save grades ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $enrollmentIds = $_POST['enrollment_id'] ?? [];
        $midterms      = $_POST['midterm']       ?? [];
        $finals        = $_POST['finals']        ?? [];

        $saved = 0;
        foreach ($enrollmentIds as $i => $eid) {
            $eid = (int)$eid;

            // Verify this enrollment belongs to teacher's subject
            $enroll = DB::fetchOne(
                'SELECT e.id FROM enrollments e
                 JOIN subjects s ON e.subject_id = s.id
                 WHERE e.id = ? AND s.teacher_id = ?',
                [$eid, $teacherId]
            );
            if (!$enroll) continue;

            // Check grade is not locked
            $grade = DB::fetchOne('SELECT * FROM grades WHERE enrollment_id = ?', [$eid]);
            if ($grade && $grade['is_locked']) continue;

            $mid = $midterms[$i] !== '' ? (float)$midterms[$i] : null;
            $fin = $finals[$i]   !== '' ? (float)$finals[$i]   : null;

            // SECURITY: validate grade range
            if (!GradeCalculator::isValidGrade($mid) || !GradeCalculator::isValidGrade($fin)) {
                $error = 'Grades must be between 0 and 100.';
                continue;
            }

            $final   = GradeCalculator::computeFinal($mid, $fin);
            $remarks = GradeCalculator::assignRemarks($final);

            if ($grade) {
                $oldFinal = $grade['final_grade'];
                DB::execute(
                    'UPDATE grades SET midterm=?, finals=?, final_grade=?, remarks=? WHERE enrollment_id=?',
                    [$mid, $fin, $final, $remarks, $eid]
                );
                Auth::logAction('Grade Updated',
                    "Enrollment ID: {$eid}, Old: {$oldFinal}, New: {$final}, Remarks: {$remarks}"
                );
            } else {
                DB::execute(
                    'INSERT INTO grades (enrollment_id, midterm, finals, final_grade, remarks, is_locked)
                     VALUES (?, ?, ?, ?, ?, 0)',
                    [$eid, $mid, $fin, $final, $remarks]
                );
                Auth::logAction('Grade Entered',
                    "Enrollment ID: {$eid}, Final: {$final}, Remarks: {$remarks}"
                );
            }
            $saved++;
        }
        $success = "{$saved} grade(s) saved successfully.";
    }
}

/* ── Fetch class list with grades for selected subject ── */
$classData = [];
if ($subjectId && $teacherId) {
    $classData = DB::fetchAll(
        'SELECT e.id AS enrollment_id, u.name, s.student_number,
                g.midterm, g.finals, g.final_grade, g.remarks, g.is_locked
         FROM enrollments e
         JOIN users u    ON e.student_id  = u.id
         JOIN students s ON s.user_id     = u.id
         LEFT JOIN grades g ON g.enrollment_id = e.id
         WHERE e.subject_id = ?
         ORDER BY u.name ASC',
        [$subjectId]
    );
}

include __DIR__ . '/../shared/header.php';
?>

<div class="topbar">
  <div>
    <div class="topbar-title">
      <?= $currentSubject
        ? htmlspecialchars($currentSubject['code'] . ' — ' . $currentSubject['name'], ENT_QUOTES, 'UTF-8')
        : 'Manage Grades' ?>
    </div>
    <div class="topbar-subtitle">
      <span class="semester-active"><span class="dot-live"></span>1st Semester · AY 2024–2025</span>
    </div>
  </div>
  <div class="topbar-actions">
    <!-- Subject switcher -->
    <form method="GET" style="display:inline">
      <select class="search-input" name="subject_id" style="width:220px" onchange="this.form.submit()">
        <?php foreach ($mySubjects as $sub): ?>
          <option value="<?= $sub['id'] ?>" <?= $sub['id']==$subjectId?'selected':'' ?>>
            <?= htmlspecialchars($sub['code'].' — '.$sub['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="<?= App::url('/teacher/my_subjects.php') ?>" class="btn btn-glass">← My Subjects</a>
  </div>
</div>

<?php if ($success): ?>
  <div style="background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#4ade80;font-size:13.5px;">✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div style="background:rgba(251,113,133,0.1);border:1px solid rgba(251,113,133,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#fb7185;font-size:13.5px;">❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="POST">
  <?= Auth::csrfField() ?>

  <div class="table-card">
    <div class="table-header">
      <div class="table-title">
        Class List
        <span class="badge badge-active" style="margin-left:8px"><?= count($classData) ?> students</span>
      </div>
      <div class="table-actions">
        <input class="search-input" placeholder="🔍  Search student..."
          oninput="filterTable(this,'grade-table')">
        <button type="submit" class="btn btn-teal">💾 Save All Grades</button>
      </div>
    </div>

    <table id="grade-table">
      <thead>
        <tr>
          <th>Student No.</th>
          <th>Name</th>
          <th>Midterm (0–100)</th>
          <th>Finals (0–100)</th>
          <th>Final Grade</th>
          <th>Remarks</th>
          <th>Progress</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($classData as $i => $row):
          $final   = $row['final_grade'];
          $remarks = $row['remarks'] ?? 'Incomplete';
          $locked  = (bool)$row['is_locked'];
          $remCls  = $remarks === 'Passed' ? 'badge-passed' : ($remarks === 'Failed' ? 'badge-failed' : 'badge-incomplete');
          $gColor  = $final !== null ? ($final >= 75 ? 'var(--accent-green)' : 'var(--accent-rose)') : 'var(--accent-amber)';
        ?>
          <tr>
            <td class="font-mono fs-13">
              <?= htmlspecialchars($row['student_number'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <input type="hidden" name="enrollment_id[]" value="<?= $row['enrollment_id'] ?>">
              <?php if ($locked): ?>
                <span style="color:var(--text-muted)"><?= $row['midterm'] ?? '—' ?></span>
              <?php else: ?>
                <input class="form-input" style="width:80px;padding:6px 10px;font-size:13px"
                  type="number" name="midterm[]" min="0" max="100" step="0.01"
                  value="<?= $row['midterm'] ?? '' ?>" placeholder="—"
                  oninput="recalcRow(this,<?= $i ?>)">
              <?php endif; ?>
            </td>
            <td>
              <?php if ($locked): ?>
                <span style="color:var(--text-muted)"><?= $row['finals'] ?? '—' ?></span>
              <?php else: ?>
                <input class="form-input" style="width:80px;padding:6px 10px;font-size:13px"
                  type="number" name="finals[]" min="0" max="100" step="0.01"
                  value="<?= $row['finals'] ?? '' ?>" placeholder="—"
                  oninput="recalcRow(this,<?= $i ?>)">
              <?php endif; ?>
            </td>
            <td id="fg-<?= $i ?>" class="fw-600" style="color:<?= $gColor ?>">
              <?= $final !== null ? number_format($final, 1) : '—' ?>
            </td>
            <td id="rem-<?= $i ?>">
              <?php if ($locked): ?>
                <span class="badge <?= $remCls ?>"><?= $remarks ?></span>
                <span class="badge badge-locked" style="margin-left:4px">🔒</span>
              <?php else: ?>
                <span class="badge <?= $remCls ?>"><?= $remarks ?></span>
              <?php endif; ?>
            </td>
            <td id="bar-<?= $i ?>" style="min-width:90px">
              <?php if ($final !== null): ?>
                <div class="grade-bar" style="width:80px">
                  <div class="grade-fill" style="width:<?= min($final,100) ?>%;background:<?= $gColor ?>"></div>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($classData)): ?>
          <tr>
            <td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">
              No students enrolled in this subject yet.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</form>

<!-- Grade formula reminder -->
<div style="background:rgba(45,212,191,0.06);border:1px solid rgba(45,212,191,0.18);border-radius:12px;padding:16px 20px;margin-top:16px;font-size:13px;color:var(--text-secondary)">
  📐 <strong style="color:var(--accent-teal)">Grade Formula:</strong>
  Final Grade = (Midterm × 0.50) + (Finals × 0.50) ·
  <span style="color:#4ade80">≥ 75 → Passed</span> ·
  <span style="color:#fb7185">&lt; 75 → Failed</span> ·
  <span style="color:#fbbf24">Finals missing → Incomplete</span>
</div>

<script>
/* Live grade recalculation — mirrors GradeCalculator::computeFinal() */
function recalcRow(input, idx) {
  const row    = input.closest('tr');
  const inputs = row.querySelectorAll('input[type=number]');
  const mid    = parseFloat(inputs[0].value);
  const fin    = parseFloat(inputs[1].value);
  const fgCell  = document.getElementById('fg-'  + idx);
  const remCell = document.getElementById('rem-' + idx);
  const barCell = document.getElementById('bar-' + idx);

  if (!isNaN(mid) && !isNaN(fin)) {
    const fg     = ((mid * 0.5) + (fin * 0.5)).toFixed(1);
    const passed = parseFloat(fg) >= 75;
    const col    = passed ? 'var(--accent-green)' : 'var(--accent-rose)';
    fgCell.textContent  = fg;
    fgCell.style.color  = col;
    remCell.innerHTML   = `<span class="badge ${passed ? 'badge-passed' : 'badge-failed'}">${passed ? 'Passed' : 'Failed'}</span>`;
    barCell.innerHTML   = `<div class="grade-bar" style="width:80px">
      <div class="grade-fill" style="width:${Math.min(parseFloat(fg), 100)}%;background:${col}"></div>
    </div>`;
  } else {
    fgCell.textContent  = '—';
    fgCell.style.color  = 'var(--text-muted)';
    remCell.innerHTML   = '<span class="badge badge-incomplete">Incomplete</span>';
    barCell.innerHTML   = '';
  }
}
</script>

<?php include __DIR__ . '/../shared/footer.php'; ?>
