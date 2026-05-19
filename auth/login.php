<?php
/* ============================================================
   GradeMS — Login Page
   File: auth/login.php
   ============================================================ */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../config/App.php';

Auth::startSession();

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    $dashboards = [
        'admin'   => '/admin/dashboard.php',
        'teacher' => '/teacher/dashboard.php',
        'student' => '/student/dashboard.php',
    ];
    App::redirect($dashboards[$_SESSION['role']] ?? '/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SECURITY: CSRF check
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } elseif (Auth::login($email, $password)) {
            $dashboards = [
                'admin'   => '/admin/dashboard.php',
                'teacher' => '/teacher/dashboard.php',
                'student' => '/student/dashboard.php',
            ];
            App::redirect($dashboards[$_SESSION['role']] ?? '/');
            exit;
        } else {
            // SECURITY: vague error — don't reveal whether email or password was wrong
            $error = 'Incorrect email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — GradeMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="<?= App::url('/assets/css/global.css') ?>">
  <link rel="stylesheet" href="<?= App::url('/assets/css/auth.css') ?>">
</head>
<body>

<div class="auth-page">
  <div class="auth-card">

    <!-- Header -->
    <div class="auth-header">
      <div class="auth-logo">🎓</div>
      <div class="auth-title">Welcome back</div>
      <div class="auth-subtitle">Sign in to access your dashboard</div>
    </div>

    <!-- Body -->
    <div class="auth-body">

      <!-- Error message -->
      <?php if ($error): ?>
        <div class="auth-alert auth-alert-error">
          <span class="auth-alert-icon"><i class="fas fa-exclamation-circle"></i></span>
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" action="<?= App::url('/auth/login.php') ?>" class="auth-form">
        <?= Auth::csrfField() ?>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-icon-wrap">
            <i class="fas fa-envelope input-icon"></i>
            <input
              type="email"
              id="email"
              name="email"
              class="form-control"
              placeholder="you@school.edu"
              value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              required
              autocomplete="email"
            >
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-icon-wrap">
            <i class="fas fa-lock input-icon"></i>
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="••••••••"
              required
              autocomplete="current-password"
            >
          </div>
        </div>

        <button type="submit" class="auth-submit-btn">
          <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
      </form>

      <!-- Demo credentials -->
      <div class="demo-credentials">
        <div class="demo-credentials-title">
          <i class="fas fa-info-circle"></i> Demo Accounts (password: <code>password</code>)
        </div>
        <div class="demo-item" onclick="fillLogin('admin@school.edu')">
          <div class="demo-item-role">🛡️ Admin</div>
          <div class="demo-item-creds">admin@school.edu</div>
          <div class="demo-item-use">Use →</div>
        </div>
        <div class="demo-item" onclick="fillLogin('ana.reyes@school.edu')">
          <div class="demo-item-role">👩‍🏫 Teacher</div>
          <div class="demo-item-creds">ana.reyes@school.edu</div>
          <div class="demo-item-use">Use →</div>
        </div>
        <div class="demo-item" onclick="fillLogin('maria.s@school.edu')">
          <div class="demo-item-role">🎓 Student</div>
          <div class="demo-item-creds">maria.s@school.edu</div>
          <div class="demo-item-use">Use →</div>
        </div>
      </div>

    </div><!-- /auth-body -->

    <!-- Footer -->
    <div class="auth-footer">
      <div class="auth-footer-text">
        GradeMS · School Grade Management System
      </div>
    </div>

  </div><!-- /auth-card -->
</div><!-- /auth-page -->

<script>
function fillLogin(email) {
  document.getElementById('email').value    = email;
  document.getElementById('password').value = 'password';
}
</script>

</body>
</html>
