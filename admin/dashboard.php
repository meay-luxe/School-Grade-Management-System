<?php
/* ============================================================
   GradeMS — Admin Dashboard
   File: admin/dashboard.php
   ============================================================ */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../config/DB.php';

Auth::startSession();
Auth::requireRole('admin');

$pageTitle  = 'Dashboard';
$activePage = '/admin/dashboard.php';

/* ── Fetch real stats from DB ── */
$totalStudents  = DB::fetchOne('SELECT COUNT(*) as c FROM users WHERE role = "student" AND is_active = 1')['c'] ?? 0;
$totalTeachers  = DB::fetchOne('SELECT COUNT(*) as c FROM users WHERE role = "teacher" AND is_active = 1')['c'] ?? 0;
$totalSubjects  = DB::fetchOne('SELECT COUNT(*) as c FROM subjects')['c'] ?? 0;
$pendingGrades  = DB::fetchOne('SELECT COUNT(*) as c FROM grades WHERE finals IS NULL')['c'] ?? 0;

$passCount  = DB::fetchOne("SELECT COUNT(*) as c FROM grades WHERE remarks = 'Passed'")['c']  ?? 0;
$totalGrades= DB::fetchOne('SELECT COUNT(*) as c FROM grades WHERE final_grade IS NOT NULL')['c'] ?? 0;
$passRate   = $totalGrades > 0 ? round(($passCount / $totalGrades) * 100) : 0;

$atRiskCount = DB::fetchOne("SELECT COUNT(*) as c FROM grades WHERE final_grade < 75 AND final_grade IS NOT NULL")['c'] ?? 0;

$atRiskStudents = DB::fetchAll("
    SELECT u.name, s.name AS subject_name, g.final_grade
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.id
    JOIN users u       ON e.student_id    = u.id
    JOIN subjects s    ON e.subject_id    = s.id
    WHERE g.final_grade < 75
    ORDER BY g.final_grade ASC
    LIMIT 5
");

$recentAudit = DB::fetchAll("
    SELECT al.action, al.details, al.created_at, u.name AS user_name
    FROM audit_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 5
");

$gradeDistribution = DB::fetchAll("
    SELECT s.code,
        SUM(CASE WHEN g.remarks = 'Passed'     THEN 1 ELSE 0 END) AS passed,
        SUM(CASE WHEN g.remarks = 'Failed'     THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN g.remarks = 'Incomplete' THEN 1 ELSE 0 END) AS incomplete
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.id
    JOIN subjects s    ON e.subject_id    = s.id
    GROUP BY s.id, s.code
    LIMIT 5
");

include __DIR__ . '/../shared/header.php';
?>

<!-- Top Bar -->
<div class="topbar">
  <div>
    <div class="topbar-title">
      <?php
        $h = (int) date('H');
        echo $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
        echo ', ' . htmlspecialchars(explode(' ', $_SESSION['name'])[0]) . ' 👋';
      ?>
    </div>
    <div class="topbar-subtitle">
      <div class="semester-active">
        <span class="dot-live"></span>
        1st Semester · AY 2024–2025
      </div>
    </div>
  </div>
  <div class="topbar-actions">
    <a href="<?= App::url('/admin/manage_semesters.php') ?>" class="btn btn-glass">📅 Manage Semester</a>
  </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-label">Total Students</div>
    <div class="stat-value"><?= $totalStudents ?></div>
    <div class="stat-sub">Active enrolled students</div>
    <div class="stat-icon">🎓</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">Teachers</div>
    <div class="stat-value"><?= $totalTeachers ?></div>
    <div class="stat-sub">Active faculty</div>
    <div class="stat-icon">👩‍🏫</div>
  </div>
  <div class="stat-card teal">
    <div class="stat-label">Subjects</div>
    <div class="stat-value"><?= $totalSubjects ?></div>
    <div class="stat-sub">Active this semester</div>
    <div class="stat-icon">📚</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Pass Rate</div>
    <div class="stat-value"><?= $passRate ?>%</div>
    <div class="stat-sub">Overall this semester</div>
    <div class="stat-icon">✅</div>
  </div>
  <div class="stat-card amber">
    <div class="stat-label">Grades Pending</div>
    <div class="stat-value"><?= $pendingGrades ?></div>
    <div class="stat-sub">Awaiting finals entry</div>
    <div class="stat-icon">⏳</div>
  </div>
  <div class="stat-card rose">
    <div class="stat-label">At Risk</div>
    <div class="stat-value"><?= $atRiskCount ?></div>
    <div class="stat-sub">Below passing grade</div>
    <div class="stat-icon">⚠️</div>
  </div>
</div>

<!-- Charts + At-Risk -->
<div class="two-col section-gap">

  <!-- Grade distribution chart (rendered via JS with PHP data) -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">📊 Grade Distribution</div>
    </div>
    <div style="padding:20px 24px">
      <div class="chart-wrap" id="grade-chart"></div>
      <div style="display:flex;gap:12px;margin-top:14px">
        <span class="badge badge-passed">● Passed</span>
        <span class="badge badge-failed">● Failed</span>
        <span class="badge badge-incomplete">● Incomplete</span>
      </div>
    </div>
  </div>

  <!-- At-risk students -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">⚠️ At-Risk Students</div>
      <span class="badge badge-failed"><?= $atRiskCount ?> students</span>
    </div>
    <?php if (empty($atRiskStudents)): ?>
      <div class="empty-state"><div class="empty-text">No at-risk students 🎉</div></div>
    <?php else: ?>
      <?php foreach ($atRiskStudents as $s): ?>
        <div style="padding:12px 24px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,0.04)">
          <div>
            <div style="font-size:13.5px;font-weight:500;color:var(--text-primary)">
              <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="font-size:12px;color:var(--text-muted)">
              <?= htmlspecialchars($s['subject_name'], ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
          <div style="text-align:right">
            <div style="font-size:16px;font-weight:700;color:var(--accent-rose)">
              <?= htmlspecialchars($s['final_grade'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="font-size:10px;color:var(--text-muted)">Final Grade</div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Audit Log -->
<div class="table-card">
  <div class="table-header">
    <div class="table-title">🕵️ Recent Activity</div>
    <a href="<?= App::url('/admin/audit_log.php') ?>" class="btn btn-glass btn-sm">View All</a>
  </div>
  <div style="padding:8px 24px 16px">
    <?php if (empty($recentAudit)): ?>
      <div class="empty-state"><div class="empty-text">No audit entries yet</div></div>
    <?php else: ?>
      <?php foreach ($recentAudit as $log): ?>
        <div class="log-item">
          <div class="log-dot" style="background:var(--accent-blue)"></div>
          <div class="log-content">
            <div class="log-action">
              <?= htmlspecialchars($log['action'] . ' — ' . $log['details'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="log-meta">
              <?= htmlspecialchars($log['user_name'], ENT_QUOTES, 'UTF-8') ?> ·
              <?= htmlspecialchars(date('M j, g:i a', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Pass chart data to JS -->
<script>
const chartData = <?= json_encode($gradeDistribution) ?>;
document.addEventListener('DOMContentLoaded', () => {
  const el  = document.getElementById('grade-chart');
  const max = 50;
  const colors = ['#4f9eff','#a78bfa','#2dd4bf','#fbbf24','#4ade80'];
  if (el && chartData.length) {
    el.innerHTML = chartData.map((b, i) => {
      const total = (+b.passed) + (+b.failed) + (+b.incomplete);
      const h = Math.round((total / max) * 120);
      return `<div class="chart-bar-col">
        <div class="chart-bar" style="background:${colors[i%colors.length]};height:${h}px;opacity:0.85">
          <span>${total}</span>
        </div>
        <div class="chart-lbl">${b.code}</div>
      </div>`;
    }).join('');
  }
});
</script>

<?php include __DIR__ . '/../shared/footer.php'; ?>
