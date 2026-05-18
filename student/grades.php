<?php
/* ============================================================
   GradeMS — Student: My Grades
   File: student/grades.php
   ============================================================ */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/GradeCalculator.php';
require_once __DIR__ . '/../config/DB.php';

Auth::startSession();
Auth::requireRole('student');

$pageTitle  = 'My Grades';
$activePage = '/student/grades.php';

/* ── Fetch student record ── */
$student = DB::fetchOne(
    'SELECT s.*, u.name, u.email
     FROM students s JOIN users u ON s.user_id = u.id
     WHERE s.user_id = ?',
    [$_SESSION['user_id']]
);

/* ── Fetch all grades grouped by semester ── */
$allGrades = DB::fetchAll(
    'SELECT g.*, sub.name AS subject_name, sub.code, sub.units,
            sem.school_year, sem.semester
     FROM grades g
     JOIN enrollments e  ON g.enrollment_id = e.id
     JOIN subjects sub   ON e.subject_id    = sub.id
     JOIN semesters sem  ON e.semester_id   = sem.id
     WHERE e.student_id = ?
     ORDER BY sem.school_year DESC, sem.semester ASC, sub.code ASC',
    [$student['id'] ?? 0]
);

/* ── Group by school_year + semester ── */
$grouped = [];
foreach ($allGrades as $g) {
    $key = $g['school_year'] . '||' . $g['semester'];
    $grouped[$key][] = $g;
}

/* ── Compute GPA for each semester ── */
$semGPAs = [];
foreach ($grouped as $key => $grades) {
    $semGPAs[$key] = GradeCalculator::computeGPA($grades);
}

/* ── Overall GPA (current semester only) ── */
$currentKey  = array_key_first($grouped);
$currentGPA  = $semGPAs[$currentKey] ?? null;
$standing    = GradeCalculator::getStanding($currentGPA);
$standingCls = GradeCalculator::getStandingClass($currentGPA);

include __DIR__ . '/../shared/header.php';
?>

<div class="topbar">
  <div>
    <div class="topbar-title">My Grade Records</div>
    <div class="topbar-subtitle text-secondary">Complete academic history by semester</div>
  </div>
  <div class="topbar-actions">
    <button class="btn btn-glass" onclick="window.print()">📥 Print Report</button>
  </div>
</div>

<!-- GPA Summary -->
<div class="glass-card" style="padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;gap:32px">
  <!-- GPA Ring -->
  <div style="position:relative;flex-shrink:0">
    <svg width="100" height="100" viewBox="0 0 100 100">
      <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="9"/>
      <?php if ($currentGPA !== null):
        // GPA 1.00 = full ring, 5.00 = empty. Map 1.00–5.00 to 264–0
        $pct = max(0, min(1, (5.00 - $currentGPA) / 4.00));
        $dash = round($pct * 264);
      ?>
      <circle cx="50" cy="50" r="42" fill="none" stroke="url(#gpaG)" stroke-width="9"
        stroke-dasharray="264" stroke-dashoffset="<?= 264 - $dash ?>"
        stroke-linecap="round" transform="rotate(-90 50 50)"/>
      <?php endif; ?>
      <defs>
        <linearGradient id="gpaG" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="#4f9eff"/>
          <stop offset="100%" stop-color="#a78bfa"/>
        </linearGradient>
      </defs>
    </svg>
    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column">
      <div style="font-size:20px;font-weight:700;color:var(--accent-blue);line-height:1">
        <?= $currentGPA !== null ? number_format($currentGPA, 2) : 'N/A' ?>
      </div>
      <div style="font-size:9px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px">GPA</div>
    </div>
  </div>

  <div>
    <div style="font-size:20px;font-weight:700;margin-bottom:4px">
      <?= htmlspecialchars($student['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div style="font-size:13px;color:var(--text-secondary);margin-bottom:10px">
      <?= htmlspecialchars($student['student_number'] ?? '', ENT_QUOTES, 'UTF-8') ?> ·
      <?= htmlspecialchars($student['course'] ?? '', ENT_QUOTES, 'UTF-8') ?> ·
      <?= htmlspecialchars($student['year_level'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <span class="standing-chip <?= $standingCls ?>">
      <?= htmlspecialchars($standing, ENT_QUOTES, 'UTF-8') ?>
    </span>
  </div>

  <!-- Per-semester GPA pills -->
  <div style="margin-left:auto;display:flex;gap:12px;flex-wrap:wrap">
    <?php foreach ($semGPAs as $key => $gpa):
      [$sy, $sem] = explode('||', $key);
    ?>
      <div style="text-align:center;padding:12px 16px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:12px">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px"><?= htmlspecialchars($sem, ENT_QUOTES, 'UTF-8') ?></div>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px"><?= htmlspecialchars($sy, ENT_QUOTES, 'UTF-8') ?></div>
        <div style="font-size:18px;font-weight:700;color:var(--accent-blue)">
          <?= $gpa !== null ? number_format($gpa, 2) : 'N/A' ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Grades by semester -->
<?php if (empty($grouped)): ?>
  <div class="table-card">
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <div class="empty-text">No grades available yet. Check back after your teachers submit grades.</div>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($grouped as $key => $grades):
    [$sy, $sem] = explode('||', $key);
    $semGpa = $semGPAs[$key];
  ?>
    <div class="table-card section-gap">
      <div class="table-header">
        <div>
          <div class="table-title"><?= htmlspecialchars($sem . ' · ' . $sy, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
          <?php if ($semGpa !== null): ?>
            <div style="font-size:12px;color:var(--text-muted)">Semester GPA:</div>
            <div style="font-size:16px;font-weight:700;color:var(--accent-blue)"><?= number_format($semGpa, 2) ?></div>
            <span class="standing-chip <?= GradeCalculator::getStandingClass($semGpa) ?>">
              <?= GradeCalculator::getStanding($semGpa) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
      <table>
        <thead>
          <tr>
            <th>Subject Code</th>
            <th>Subject Name</th>
            <th>Units</th>
            <th>Midterm</th>
            <th>Finals</th>
            <th>Final Grade</th>
            <th>GPA Equiv</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grades as $g):
            $final   = $g['final_grade'];
            $remarks = $g['remarks'] ?? 'Incomplete';
            $remCls  = $remarks === 'Passed' ? 'badge-passed' : ($remarks === 'Failed' ? 'badge-failed' : 'badge-incomplete');
            $gpaEq   = $final !== null ? GradeCalculator::gradeToGPA((float)$final) : null;
            $gColor  = $final !== null ? ($final >= 75 ? 'var(--accent-green)' : 'var(--accent-rose)') : 'var(--accent-amber)';
          ?>
            <tr>
              <td class="font-mono text-accent-teal fs-13">
                <?= htmlspecialchars($g['code'], ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td><?= htmlspecialchars($g['subject_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-muted"><?= $g['units'] ?></td>
              <td class="text-secondary"><?= $g['midterm'] ?? '—' ?></td>
              <td class="text-secondary"><?= $g['finals']  ?? '—' ?></td>
              <td class="fw-600" style="color:<?= $gColor ?>">
                <?= $final !== null ? number_format((float)$final, 1) : '—' ?>
                <?php if ($final !== null): ?>
                  <div class="grade-bar" style="width:70px;margin-top:4px">
                    <div class="grade-fill" style="width:<?= min($final, 100) ?>%;background:<?= $gColor ?>"></div>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-secondary">
                <?= $gpaEq !== null ? number_format($gpaEq, 2) : '—' ?>
              </td>
              <td><span class="badge <?= $remCls ?>"><?= $remarks ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="border-top:2px solid var(--glass-border)">
            <td colspan="2" style="padding:12px 24px;font-weight:600;color:var(--text-primary)">
              Total Units (Passed)
            </td>
            <td style="padding:12px 24px;font-weight:600;color:var(--accent-blue)">
              <?= array_sum(array_map(fn($g) => $g['remarks']==='Passed' ? $g['units'] : 0, $grades)) ?>
            </td>
            <td colspan="5" style="padding:12px 24px;text-align:right;font-size:12px;color:var(--text-muted)">
              Incomplete subjects are excluded from GPA calculation
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../shared/footer.php'; ?>
