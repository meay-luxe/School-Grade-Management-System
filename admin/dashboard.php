<?php
/* ============================================================
   GradeMS — Admin Dashboard
   File: admin/dashboard.php
   ============================================================ */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../config/DB.php';
require_once __DIR__ . '/../config/App.php';

Auth::startSession();
Auth::requireRole('admin');

$pageTitle  = 'Dashboard';
$activePage = App::url('/admin/dashboard.php');

/* ── Stats ── */
$totalStudents = DB::fetchOne('SELECT COUNT(*) AS c FROM users WHERE role = "student" AND is_active = 1')['c'] ?? 0;
$totalTeachers = DB::fetchOne('SELECT COUNT(*) AS c FROM users WHERE role = "teacher" AND is_active = 1')['c'] ?? 0;
$totalSubjects = DB::fetchOne('SELECT COUNT(*) AS c FROM subjects WHERE is_active = 1')['c'] ?? 0;
$pendingGrades = DB::fetchOne('SELECT COUNT(*) AS c FROM grades WHERE final_grade IS NULL')['c'] ?? 0;

$passCount   = DB::fetchOne("SELECT COUNT(*) AS c FROM grades WHERE remarks = 'Passed'")['c'] ?? 0;
$totalGrades = DB::fetchOne('SELECT COUNT(*) AS c FROM grades WHERE final_grade IS NOT NULL')['c'] ?? 0;
$passRate    = $totalGrades > 0 ? round(($passCount / $totalGrades) * 100) : 0;
$atRiskCount = DB::fetchOne("SELECT COUNT(*) AS c FROM grades WHERE final_grade < 75 AND final_grade IS NOT NULL")['c'] ?? 0;

/* ── At-risk students ── */
$atRiskStudents = DB::fetchAll("
    SELECT u.name, sub.name AS subject_name, g.final_grade
    FROM   grades g
    JOIN   enrollments e ON g.enrollment_id = e.id
    JOIN   students s    ON e.student_id    = s.id
    JOIN   users u       ON s.user_id       = u.id
    JOIN   subjects sub  ON e.subject_id    = sub.id
    WHERE  g.final_grade < 75
    ORDER  BY g.final_grade ASC
    LIMIT  5
");

/* ── Recent audit ── */
$recentAudit = DB::fetchAll("
    SELECT al.action, al.details, al.created_at, u.name AS user_name
    FROM   audit_logs al
    JOIN   users u ON al.user_id = u.id
    ORDER  BY al.created_at DESC
    LIMIT  5
");

/* ── Grade distribution ── */
$gradeDistribution = DB::fetchAll("
    SELECT sub.code,
        SUM(CASE WHEN g.remarks = 'Passed'     THEN 1 ELSE 0 END) AS passed,
        SUM(CASE WHEN g.remarks = 'Failed'     THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN g.remarks = 'Incomplete' THEN 1 ELSE 0 END) AS incomplete
    FROM   grades g
    JOIN   enrollments e ON g.enrollment_id = e.id
    JOIN   subjects sub  ON e.subject_id    = sub.id
    GROUP  BY sub.id, sub.code
    LIMIT  6
");

include __DIR__ . '/../shared/header.php';
?>

<!-- ── Topbar ── -->
<div class="topbar">
  <div class="topbar-left">
    <div>
      <div class="topbar-title">
        <?php
          $h = (int) date('H');
          echo $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
          echo ', ' . htmlspecialchars(explode(' ', $_SESSION['name'])[0]) . ' 👋';
        ?>
      </div>
      <div class="topbar-subtitle">Admin Dashboard</div>
    </div>
  </div>
  <div class="topbar-right">
    <a href="<?= App::url('/admin/manage_semesters.php') ?>" class="btn btn-secondary btn-sm">
      <i class="fas fa-calendar-alt"></i> Manage Semester
    </a>
  </div>
</div>

<!-- ── Page Content ── -->
<div class="page-content">

  <!-- Stat Cards -->
  <div class="stats-grid">

    <div class="stat-card primary">
      <div class="stat-icon primary"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Students</div>
        <div class="stat-value"><?= $totalStudents ?></div>
        <div class="stat-change flat">Active enrolled</div>
      </div>
    </div>

    <div class="stat-card info">
      <div class="stat-icon info"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="stat-info">
        <div class="stat-label">Teachers</div>
        <div class="stat-value"><?= $totalTeachers ?></div>
        <div class="stat-change flat">Active faculty</div>
      </div>
    </div>

    <div class="stat-card success">
      <div class="stat-icon success"><i class="fas fa-book-open"></i></div>
      <div class="stat-info">
        <div class="stat-label">Subjects</div>
        <div class="stat-value"><?= $totalSubjects ?></div>
        <div class="stat-change flat">Active this semester</div>
      </div>
    </div>

    <div class="stat-card warning">
      <div class="stat-icon warning"><i class="fas fa-chart-line"></i></div>
      <div class="stat-info">
        <div class="stat-label">Pass Rate</div>
        <div class="stat-value"><?= $passRate ?>%</div>
        <div class="stat-change flat">Overall</div>
      </div>
    </div>

    <div class="stat-card danger">
      <div class="stat-icon danger"><i class="fas fa-hourglass-half"></i></div>
      <div class="stat-info">
        <div class="stat-label">Grades Pending</div>
        <div class="stat-value"><?= $pendingGrades ?></div>
        <div class="stat-change flat">Awaiting entry</div>
      </div>
    </div>

    <div class="stat-card warning">
      <div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
      <div class="stat-info">
        <div class="stat-label">At Risk</div>
        <div class="stat-value"><?= $atRiskCount ?></div>
        <div class="stat-change flat">Below passing</div>
      </div>
    </div>

  </div><!-- /stats-grid -->

  <!-- Charts + At-Risk Row -->
  <div class="grid-2 mb-6">

    <!-- Grade Distribution Chart -->
    <div class="glass-card">
      <div class="glass-card-header">
        <h3><i class="fas fa-chart-bar"></i> Grade Distribution</h3>
      </div>
      <div class="glass-card-body">
        <?php if (empty($gradeDistribution)): ?>
          <div class="empty-state">
            <div class="empty-state-icon">📊</div>
            <p>No grade data yet.</p>
          </div>
        <?php else: ?>
          <div id="grade-chart" style="display:flex;align-items:flex-end;gap:12px;height:140px;padding-bottom:8px"></div>
          <div style="display:flex;gap:12px;margin-top:12px;flex-wrap:wrap">
            <span class="badge badge-success">● Passed</span>
            <span class="badge badge-danger">● Failed</span>
            <span class="badge badge-muted">● Incomplete</span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- At-Risk Students -->
    <div class="glass-card">
      <div class="glass-card-header">
        <h3><i class="fas fa-exclamation-triangle"></i> At-Risk Students</h3>
        <span class="badge badge-danger"><?= $atRiskCount ?></span>
      </div>
      <?php if (empty($atRiskStudents)): ?>
        <div class="glass-card-body">
          <div class="empty-state">
            <div class="empty-state-icon">🎉</div>
            <p>No at-risk students!</p>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($atRiskStudents as $s): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;
                      padding:12px 24px;border-bottom:1px solid var(--glass-border)">
            <div>
              <div style="font-size:13.5px;font-weight:500;color:var(--text-primary)">
                <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="font-size:12px;color:var(--text-muted)">
                <?= htmlspecialchars($s['subject_name'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <span class="badge badge-danger">
              <?= number_format((float)$s['final_grade'], 1) ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div><!-- /grid-2 -->

  <!-- Quick Actions -->
  <div class="glass-card mb-6">
    <div class="glass-card-header">
      <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    </div>
    <div class="glass-card-body">
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="<?= App::url('/admin/manage_students.php') ?>" class="btn btn-primary">
          <i class="fas fa-user-plus"></i> Add Student
        </a>
        <a href="<?= App::url('/admin/manage_subjects.php') ?>" class="btn btn-secondary">
          <i class="fas fa-book"></i> Manage Subjects
        </a>
        <a href="<?= App::url('/admin/manage_enrollment.php') ?>" class="btn btn-secondary">
          <i class="fas fa-list-check"></i> Enrollment
        </a>
        <a href="<?= App::url('/admin/view_grades.php') ?>" class="btn btn-secondary">
          <i class="fas fa-graduation-cap"></i> View Grades
        </a>
        <a href="<?= App::url('/admin/reports.php') ?>" class="btn btn-secondary">
          <i class="fas fa-chart-pie"></i> Reports
        </a>
        <a href="<?= App::url('/admin/audit_log.php') ?>" class="btn btn-secondary">
          <i class="fas fa-history"></i> Audit Log
        </a>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="glass-card">
    <div class="glass-card-header">
      <h3><i class="fas fa-history"></i> Recent Activity</h3>
      <a href="<?= App::url('/admin/audit_log.php') ?>" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <?php if (empty($recentAudit)): ?>
      <div class="glass-card-body">
        <div class="empty-state">
          <div class="empty-state-icon">📋</div>
          <p>No audit entries yet.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Action</th>
              <th>Details</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentAudit as $log): ?>
              <tr>
                <td>
                  <div class="user-cell">
                    <div class="avatar avatar-sm avatar-primary">
                      <?= strtoupper(substr($log['user_name'], 0, 2)) ?>
                    </div>
                    <div class="user-cell-info">
                      <div class="name"><?= htmlspecialchars($log['user_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-muted text-sm"><?= htmlspecialchars($log['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-muted text-sm">
                  <?= date('M j, g:i a', strtotime($log['created_at'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div><!-- /page-content -->

<script>
const chartData = <?= json_encode($gradeDistribution) ?>;
document.addEventListener('DOMContentLoaded', () => {
  const el     = document.getElementById('grade-chart');
  const colors = ['#6c63ff','#4f9eff','#2dd4bf','#fbbf24','#4ade80','#f87171'];
  if (!el || !chartData.length) return;

  const maxTotal = Math.max(...chartData.map(b =>
    (+b.passed) + (+b.failed) + (+b.incomplete)
  ), 1);

  el.innerHTML = chartData.map((b, i) => {
    const total = (+b.passed) + (+b.failed) + (+b.incomplete);
    const h     = Math.round((total / maxTotal) * 120);
    return `
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex:1">
        <div style="font-size:11px;color:var(--text-muted)">${total}</div>
        <div style="width:100%;height:${h}px;background:${colors[i % colors.length]};
                    border-radius:6px 6px 0 0;opacity:0.85;min-height:4px"></div>
        <div style="font-size:11px;color:var(--text-secondary);font-weight:600">${b.code}</div>
      </div>`;
  }).join('');
});
</script>

<?php include __DIR__ . '/../shared/footer.php'; ?>
