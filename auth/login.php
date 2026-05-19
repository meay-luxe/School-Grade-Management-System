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
  <link rel="stylesheet" href="<?= App::url('/assets/css/global.css') ?>">
  <link rel="stylesheet" href="<?= App::url('/assets/css/auth.css') ?>">
</head>
<body>

<div class="auth-wrap">
  <div class="auth-card">

    <!-- Logo -->
    <div class="auth-logo">
      <div class="auth-logo-icon">🎓</div>
      <div class="auth-logo-text">Grade<span>MS</span></div>
    </div>

    <div class="auth-title">Welcome back</div>
    <div class="auth-subtitle">Sign in to access your dashboard</div>

    <!-- Error message -->
    <?php if ($error): ?>
      <div class="auth-error show"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="<?= App::url('/auth/login.php') ?>">
      <!-- SECURITY: CSRF token -->
      <?= Auth::csrfField() ?>

      <div class="auth-field">
        <label for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="you@school.edu"
          value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required
          autocomplete="email"
        >
      </div>

      <div class="auth-field">
        <label for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="••••••••"
          required
          autocomplete="current-password"
        >
      </div>

      <button type="submit" class="btn-auth">Sign In →</button>
    </form>

    <div class="auth-demo-note">
      Default admin: admin@school.edu / password
    </div>
  </div>
</div>

</body>
</html>
