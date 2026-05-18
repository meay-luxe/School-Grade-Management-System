<?php
/* ============================================================
   GradeMS — Shared Sidebar Partial
   File: shared/sidebar.php
   ============================================================ */

$role       = $_SESSION['role']  ?? '';
$userName   = $_SESSION['name']  ?? 'User';
$initials   = strtoupper(substr($userName, 0, 1) . (strpos($userName, ' ') !== false
    ? substr($userName, strrpos($userName, ' ') + 1, 1) : ''));

$avatarGradients = [
    'admin'   => 'linear-gradient(135deg,#a78bfa,#7c3aed)',
    'teacher' => 'linear-gradient(135deg,#4f9eff,#2563eb)',
    'student' => 'linear-gradient(135deg,#2dd4bf,#0f766e)',
];
$avatarBg = $avatarGradients[$role] ?? $avatarGradients['student'];

/* ── Nav definitions per role ── */
$nav = [
    'admin' => [
        ['label' => 'OVERVIEW', 'items' => [
            ['icon' => '🏠', 'text' => 'Dashboard',    'href' => '/admin/dashboard.php'],
        ]],
        ['label' => 'MANAGEMENT', 'items' => [
            ['icon' => '👥', 'text' => 'Users',       'href' => '/admin/user_management.php'],
            ['icon' => '🎓', 'text' => 'Students',    'href' => '/admin/manage_students.php'],
            ['icon' => '📚', 'text' => 'Subjects',    'href' => '/admin/manage_subjects.php'],
            ['icon' => '📅', 'text' => 'Semesters',   'href' => '/admin/manage_semesters.php'],
            ['icon' => '📝', 'text' => 'Enrollment',  'href' => '/admin/manage_enrollment.php'],
        ]],
        ['label' => 'GRADES', 'items' => [
            ['icon' => '📊', 'text' => 'Grade Records', 'href' => '/admin/view_grades.php'],
            ['icon' => '📈', 'text' => 'Reports',        'href' => '/admin/reports.php'],
        ]],
        ['label' => 'SYSTEM', 'items' => [
            ['icon' => '🔗', 'text' => 'JSON API',  'href' => '/api/grades.php'],
            ['icon' => '🕵️', 'text' => 'Audit Log', 'href' => '/admin/audit_log.php'],
            ['icon' => '👤', 'text' => 'Profile',   'href' => '/profile.php'],
        ]],
    ],
    'teacher' => [
        ['label' => 'TEACHING', 'items' => [
            ['icon' => '📚', 'text' => 'My Subjects',   'href' => '/teacher/my_subjects.php'],
            ['icon' => '📊', 'text' => 'Manage Grades', 'href' => '/teacher/manage_grades.php'],
            ['icon' => '📈', 'text' => 'My Reports',    'href' => '/teacher/reports.php'],
        ]],
        ['label' => 'ACCOUNT', 'items' => [
            ['icon' => '🔗', 'text' => 'JSON API', 'href' => '/api/grades.php'],
            ['icon' => '👤', 'text' => 'Profile',  'href' => '/profile.php'],
        ]],
    ],
    'student' => [
        ['label' => 'ACADEMIC', 'items' => [
            ['icon' => '🏠', 'text' => 'Dashboard', 'href' => '/student/dashboard.php'],
            ['icon' => '📋', 'text' => 'My Grades', 'href' => '/student/grades.php'],
        ]],
        ['label' => 'ACCOUNT', 'items' => [
            ['icon' => '👤', 'text' => 'Profile', 'href' => '/profile.php'],
        ]],
    ],
];

$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$sections    = $nav[$role] ?? [];
?>

<nav id="sidebar">
  <!-- Logo -->
  <div class="sidebar-logo">
    <div class="logo-icon">🎓</div>
    <div class="logo-text">Grade<span>MS</span></div>
  </div>

  <!-- Nav links -->
  <?php foreach ($sections as $section): ?>
    <div class="sidebar-section">
      <div class="sidebar-label"><?= htmlspecialchars($section['label']) ?></div>
      <?php foreach ($section['items'] as $item):
        $isActive = ($currentPath === $item['href']) ? 'active' : '';
      ?>
        <a class="nav-item <?= $isActive ?>" href="<?= htmlspecialchars($item['href']) ?>">
          <span class="nav-icon"><?= $item['icon'] ?></span>
          <?= htmlspecialchars($item['text']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <!-- User info + logout -->
  <a class="sidebar-user" href="/profile.php" style="text-decoration:none">
    <div class="user-avatar" style="background:<?= $avatarBg ?>">
      <?= htmlspecialchars($initials) ?>
    </div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($userName) ?></div>
      <div class="user-role"><?= htmlspecialchars(ucfirst($role)) ?></div>
    </div>
    <a class="logout-btn" href="/auth/logout.php" title="Logout"
       onclick="return confirm('Log out?')">⬡</a>
  </a>
</nav>
