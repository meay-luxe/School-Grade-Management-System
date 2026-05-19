<?php
// ============================================================
// profile.php — Shared Profile Page (All Roles)
// ============================================================

require_once 'helpers/Auth.php';
require_once 'config/DB.php';

if (!Auth::isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$db     = DB::getInstance();
$userId = Auth::getUserId();
$role   = Auth::getRole();

$success = '';
$error   = '';

// ── Fetch base user ───────────────────────────────────────────
$user = $db->prepare(
    "SELECT * FROM users WHERE id = :id LIMIT 1"
);
$user->execute([':id' => $userId]);
$user = $user->fetch(PDO::FETCH_ASSOC);

// ── Fetch role profile ────────────────────────────────────────
$profile = [];
if ($role === 'admin') {
    $stmt = $db->prepare(
        "SELECT * FROM admins WHERE user_id = :uid LIMIT 1"
    );
    $stmt->execute([':uid' => $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

} elseif ($role === 'teacher') {
    $stmt = $db->prepare(
        "SELECT * FROM teachers WHERE user_id = :uid LIMIT 1"
    );
    $stmt->execute([':uid' => $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

} elseif ($role === 'student') {
    $stmt = $db->prepare(
        "SELECT * FROM students WHERE user_id = :uid LIMIT 1"
    );
    $stmt->execute([':uid' => $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$fullName = trim(
    ($profile['first_name'] ?? '')
    . ' '
    . ($profile['last_name'] ?? '')
) ?: $user['username'];

$initials = strtoupper(
    substr($profile['first_name'] ?? $user['username'], 0, 1)
    . substr($profile['last_name'] ?? '',               0, 1)
);

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';

    } else {

        // ── Update Profile Info ───────────────────────────────
        if ($action === 'update_profile') {
            $email = trim($_POST['email'] ?? '');

            if (empty($email)) {
                $error = 'Email is required.';
            } else {
                // Check email uniqueness
                $check = $db->prepare(
                    "SELECT COUNT(*) FROM users
                     WHERE email = :email AND id != :id"
                );
                $check->execute([':email' => $email, ':id' => $userId]);

                if ((int) $check->fetchColumn() > 0) {
                    $error = 'Email is already in use.';
                } else {
                    // Update user email
                    $db->prepare(
                        "UPDATE users SET email = :email WHERE id = :id"
                    )->execute([':email' => $email, ':id' => $userId]);

                    // Update role-specific fields
                    $contactNo = trim($_POST['contact_no'] ?? '');

                    if ($role === 'teacher' && !empty($profile)) {
                        $db->prepare(
                            "UPDATE teachers
                             SET contact_no     = :contact,
                                 department     = :dept,
                                 specialization = :spec
                             WHERE user_id = :uid"
                        )->execute([
                            ':contact' => $contactNo,
                            ':dept'    => trim($_POST['department']     ?? ''),
                            ':spec'    => trim($_POST['specialization'] ?? ''),
                            ':uid'     => $userId,
                        ]);

                    } elseif ($role === 'student' && !empty($profile)) {
                        $db->prepare(
                            "UPDATE students
                             SET contact_no = :contact,
                                 section    = :section,
                                 course     = :course
                             WHERE user_id  = :uid"
                        )->execute([
                            ':contact' => $contactNo,
                            ':section' => trim($_POST['section'] ?? ''),
                            ':course'  => trim($_POST['course']  ?? ''),
                            ':uid'     => $userId,
                        ]);
                    }

                    Auth::logAction('Updated profile');
                    $success = 'Profile updated successfully.';

                    // Refresh user data
                    $user['email'] = $email;
                }
            }
        }

        // ── Change Password ───────────────────────────────────
        if ($action === 'change_password') {
            $currentPw  = $_POST['current_password']  ?? '';
            $newPw      = $_POST['new_password']      ?? '';
            $confirmPw  = $_POST['confirm_password']  ?? '';

            if (empty($currentPw) || empty($newPw) || empty($confirmPw)) {
                $error = 'All password fields are required.';

            } elseif (!password_verify($currentPw, $user['password'])) {
                $error = 'Current password is incorrect.';

            } elseif (strlen($newPw) < 8) {
                $error = 'New password must be at least 8 characters.';

            } elseif ($newPw !== $confirmPw) {
                $error = 'New passwords do not match.';

            } else {
                $db->prepare(
                    "UPDATE users SET password = :pw WHERE id = :id"
                )->execute([
                    ':pw' => password_hash($newPw, PASSWORD_BCRYPT),
                    ':id' => $userId,
                ]);

                Auth::logAction('Changed password');
                $success = 'Password changed successfully.';
            }
        }

        // ── Upload Avatar ─────────────────────────────────────
        if ($action === 'upload_avatar') {
            $file = $_FILES['avatar'] ?? null;

            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $error = 'No file uploaded or upload error.';

            } else {
                $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize   = 2 * 1024 * 1024; // 2MB
                $mimeType  = mime_content_type($file['tmp_name']);

                if (!in_array($mimeType, $allowed)) {
                    $error = 'Only JPG, PNG, GIF, WEBP allowed.';

                } elseif ($file['size'] > $maxSize) {
                    $error = 'File too large. Max 2MB.';

                } else {
                    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                    $dest     = __DIR__ . '/uploads/avatars/' . $filename;

                    if (!is_dir(dirname($dest))) {
                        mkdir(dirname($dest), 0755, true);
                    }

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        // Delete old avatar
                        if (!empty($user['avatar'])) {
                            $old = __DIR__ . '/uploads/avatars/' . $user['avatar'];
                            if (file_exists($old)) unlink($old);
                        }

                        $db->prepare(
                            "UPDATE users SET avatar = :avatar WHERE id = :id"
                        )->execute([':avatar' => $filename, ':id' => $userId]);

                        Auth::logAction('Updated avatar');
                        $success = 'Avatar updated successfully.';
                        $user['avatar'] = $filename;

                    } else {
                        $error = 'Failed to save avatar.';
                    }
                }
            }
        }
    }
}

// ── Determine back link based on role ─────────────────────────
$dashboardLink = match($role) {
    'admin'   => 'admin/dashboard.php',
    'teacher' => 'teacher/dashboard.php',
    'student' => 'student/dashboard.php',
    default   => 'index.php',
};

$csrfToken = Auth::generateCsrf();
$pageTitle = 'My Profile';

include 'shared/header.php';
include 'shared/sidebar.php';
?>

<div id="flash-messages"
     data-success="<?= htmlspecialchars($success) ?>"
     data-error="<?= htmlspecialchars($error) ?>">
</div>

<div class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <button class="topbar-mobile-menu">
        <i class="fas fa-bars"></i>
      </button>
      <div>
        <div class="topbar-title">My Profile</div>
        <div class="topbar-subtitle">
          Manage your account settings
        </div>
      </div>
    </div>
    <div class="topbar-right">
      <a href="<?= $dashboardLink ?>" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
      </a>
    </div>
  </div>

  <div class="page-content">

    <div class="page-header">
      <div class="page-header-left">
        <div class="breadcrumb">
          <div class="breadcrumb-item">
            <a href="<?= $dashboardLink ?>">Dashboard</a>
          </div>
          <span class="breadcrumb-separator">›</span>
          <div class="breadcrumb-item active">Profile</div>
        </div>
        <h1>My Profile</h1>
        <p>Manage your personal information and settings</p>
      </div>
    </div>

    <div class="grid-2" style="
      grid-template-columns:280px 1fr;
      align-items:start;
      gap:var(--space-6)">

      <!-- Left: Avatar Card -->
      <div class="d-flex flex-direction-column gap-5"
           style="flex-direction:column">

        <!-- Avatar -->
        <div class="glass-card">
          <div class="glass-card-body text-center">

            <!-- Avatar Display -->
            <div style="position:relative;display:inline-block;margin-bottom:var(--space-4)">
              <?php if (!empty($user['avatar'])): ?>
                <img src="uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>"
                     alt="Avatar"
                     class="avatar avatar-xl"
                     style="margin:0 auto;
                            border:3px solid var(--glass-border)">
              <?php else: ?>
                <div class="avatar avatar-xl avatar-primary"
                     style="margin:0 auto;
                            font-size:1.8rem;
                            border:3px solid var(--glass-border)">
                  <?= $initials ?>
                </div>
              <?php endif; ?>

              <!-- Upload trigger -->
              <label for="avatar-upload"
                     style="position:absolute;bottom:0;right:0;
                            width:28px;height:28px;
                            background:var(--primary);
                            border-radius:50%;
                            display:flex;align-items:center;
                            justify-content:center;
                            cursor:pointer;
                            border:2px solid var(--bg-base)">
                <i class="fas fa-camera"
                   style="font-size:0.65rem;color:#fff"></i>
              </label>
            </div>

            <h3 class="font-bold mb-1">
              <?= htmlspecialchars($fullName) ?>
            </h3>
            <div class="mb-3">
              <span class="badge badge-primary">
                <?= ucfirst($role) ?>
              </span>
            </div>
            <div class="text-sm text-muted mb-1">
              <i class="fas fa-envelope"></i>
              <?= htmlspecialchars($user['email']) ?>
            </div>
            <div class="text-sm text-muted">
              <i class="fas fa-clock"></i>
              Joined <?= date('M Y', strtotime($user['created_at'])) ?>
            </div>

            <!-- Hidden avatar upload form -->
            <form method="POST"
                  enctype="multipart/form-data"
                  id="avatar-form"
                  class="mt-4">
              <input type="hidden" name="action"
                     value="upload_avatar">
              <input type="hidden" name="csrf_token"
                     value="<?= $csrfToken ?>">
              <input type="file"
                     id="avatar-upload"
                     name="avatar"
                     accept="image/*"
                     style="display:none"
                     onchange="document.getElementById('avatar-form').submit()">
              <p class="text-xs text-muted mt-2">
                Click the camera icon to update photo.<br>
                Max 2MB · JPG, PNG, GIF, WEBP
              </p>
            </form>

          </div>
        </div>

        <!-- Role Info Card -->
        <div class="glass-card">
          <div class="glass-card-header">
            <h3>
              <i class="fas fa-id-card"></i> Info
            </h3>
          </div>
          <div class="glass-card-body">
            <?php if ($role === 'student' && !empty($profile)): ?>
              <div class="d-flex flex-direction-column gap-3"
                   style="flex-direction:column">
                <div>
                  <div class="text-xs text-muted">Student ID</div>
                  <div class="font-semibold">
                    <?= htmlspecialchars($profile['student_id'] ?? '—') ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Year Level</div>
                  <div class="font-semibold">
                    Year <?= $profile['year_level'] ?? '—' ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Course</div>
                  <div class="font-semibold">
                    <?= htmlspecialchars($profile['course'] ?? '—') ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Section</div>
                  <div class="font-semibold">
                    <?= htmlspecialchars($profile['section'] ?? '—') ?>
                  </div>
                </div>
              </div>

            <?php elseif ($role === 'teacher' && !empty($profile)): ?>
              <div class="d-flex flex-direction-column gap-3"
                   style="flex-direction:column">
                <div>
                  <div class="text-xs text-muted">Employee ID</div>
                  <div class="font-semibold">
                    <?= htmlspecialchars($profile['employee_id'] ?? '—') ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Department</div>
                  <div class="font-semibold">
                    <?= htmlspecialchars($profile['department'] ?? '—') ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs text-muted">Specialization</div>
                  <div class="font-semibold">
                    <?= htmlspecialchars($profile['specialization'] ?? '—') ?>
                  </div>
                </div>
              </div>

            <?php else: ?>
              <div class="text-muted text-sm">
                Administrator account
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /left column -->

      <!-- Right: Settings Tabs -->
      <div class="d-flex flex-direction-column gap-5"
           style="flex-direction:column">

        <!-- Tabs -->
        <div class="tabs" data-persist="profile">
          <button class="tab-btn active" data-tab="tab-info">
            <i class="fas fa-user"></i> Personal Info
          </button>
          <button class="tab-btn" data-tab="tab-security">
            <i class="fas fa-lock"></i> Security
          </button>
          <?php if ($role === 'student'): ?>
            <button class="tab-btn" data-tab="tab-academic">
              <i class="fas fa-graduation-cap"></i> Academic
            </button>
          <?php endif; ?>
        </div>

        <!-- Tab: Personal Info -->
        <div class="tab-content active" id="tab-info">
          <div class="glass-card">
            <div class="glass-card-header">
              <h3>
                <i class="fas fa-user-edit"></i>
                Personal Information
              </h3>
            </div>
            <form method="POST">
              <div class="modal-body" style="padding:var(--space-6)">
                <input type="hidden" name="action"
                       value="update_profile">
                <input type="hidden" name="csrf_token"
                       value="<?= $csrfToken ?>">

                <!-- Read-only fields -->
                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text"
                           class="form-control"
                           value="<?= htmlspecialchars(
                             $profile['first_name'] ?? ''
                           ) ?>"
                           disabled>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text"
                           class="form-control"
                           value="<?= htmlspecialchars(
                             $profile['last_name'] ?? ''
                           ) ?>"
                           disabled>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Username</label>
                  <input type="text"
                         class="form-control"
                         value="<?= htmlspecialchars($user['username']) ?>"
                         disabled>
                  <span class="form-hint">
                    Username cannot be changed.
                  </span>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    Email <span class="required">*</span>
                  </label>
                  <input type="email"
                         name="email"
                         class="form-control"
                         value="<?= htmlspecialchars($user['email']) ?>"
                         required>
                </div>

                <div class="form-group">
                  <label class="form-label">Contact Number</label>
                  <input type="text"
                         name="contact_no"
                         class="form-control"
                         placeholder="e.g. 09XX-XXX-XXXX"
                         value="<?= htmlspecialchars(
                           $profile['contact_no'] ?? ''
                         ) ?>">
                </div>

                <?php if ($role === 'teacher'): ?>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Department</label>
                      <input type="text"
                             name="department"
                             class="form-control"
                             value="<?= htmlspecialchars(
                               $profile['department'] ?? ''
                             ) ?>">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Specialization</label>
                      <input type="text"
                             name="specialization"
                             class="form-control"
                             value="<?= htmlspecialchars(
                               $profile['specialization'] ?? ''
                             ) ?>">
                    </div>
                  </div>
                <?php endif; ?>

                <?php if ($role === 'student'): ?>
                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Course</label>
                      <input type="text"
                             name="course"
                             class="form-control"
                             value="<?= htmlspecialchars(
                               $profile['course'] ?? ''
                             ) ?>">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Section</label>
                      <input type="text"
                             name="section"
                             class="form-control"
                             value="<?= htmlspecialchars(
                               $profile['section'] ?? ''
                             ) ?>">
                    </div>
                  </div>
                <?php endif; ?>

              </div>
              <div class="glass-card-footer d-flex justify-end">
                <button type="submit" class="btn btn-primary">
                  <i class="fas fa-save"></i> Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Tab: Security -->
        <div class="tab-content" id="tab-security">
          <div class="glass-card">
            <div class="glass-card-header">
              <h3>
                <i class="fas fa-shield-alt"></i>
                Change Password
              </h3>
            </div>
            <form method="POST">
              <div class="modal-body" style="padding:var(--space-6)">
                <input type="hidden" name="action"
                       value="change_password">
                <input type="hidden" name="csrf_token"
                       value="<?= $csrfToken ?>">

                <div class="form-group">
                  <label class="form-label">
                    Current Password <span class="required">*</span>
                  </label>
                  <div class="input-icon-wrap">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password"
                           name="current_password"
                           class="form-control"
                           placeholder="Enter current password"
                           required>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    New Password <span class="required">*</span>
                  </label>
                  <div class="input-icon-wrap">
                    <i class="fas fa-key input-icon"></i>
                    <input type="password"
                           name="new_password"
                           id="new-password"
                           class="form-control"
                           placeholder="Min 8 characters"
                           minlength="8"
                           required>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    Confirm Password <span class="required">*</span>
                  </label>
                  <div class="input-icon-wrap">
                    <i class="fas fa-key input-icon"></i>
                    <input type="password"
                           name="confirm_password"
                           id="confirm-password"
                           class="form-control"
                           placeholder="Repeat new password"
                           required>
                  </div>
                </div>

                <!-- Password Strength -->
                <div id="pw-strength-wrap" style="display:none">
                  <div class="d-flex justify-between text-xs mb-1">
                    <span class="text-muted">Password Strength</span>
                    <span id="pw-strength-label" class="text-muted"></span>
                  </div>
                  <div class="progress">
                    <div id="pw-strength-bar"
                         class="progress-bar primary"
                         style="width:0%;transition:width 0.3s ease">
                    </div>
                  </div>
                </div>

              </div>
              <div class="glass-card-footer d-flex justify-end">
                <button type="submit" class="btn btn-warning">
                  <i class="fas fa-lock"></i> Change Password
                </button>
              </div>
            </form>
          </div>

          <!-- Session Info -->
          <div class="glass-card mt-5">
            <div class="glass-card-header">
              <h3>
                <i class="fas fa-info-circle"></i>
                Session Info
              </h3>
            </div>
            <div class="glass-card-body">
              <div class="d-flex flex-direction-column gap-3"
                   style="flex-direction:column">
                <div class="d-flex justify-between">
                  <span class="text-muted text-sm">Role</span>
                  <span class="badge badge-primary">
                    <?= ucfirst($role) ?>
                  </span>
                </div>
                <div class="d-flex justify-between">
                  <span class="text-muted text-sm">
                    Account Status
                  </span>
                  <span class="badge badge-success">Active</span>
                </div>
                <div class="d-flex justify-between">
                  <span class="text-muted text-sm">Last Login</span>
                  <span class="text-sm">
                    <?= date('M d, Y g:i A') ?>
                  </span>
                </div>
              </div>
            </div>
            <div class="glass-card-footer">
              <a href="auth/logout.php"
                 class="btn btn-ghost-danger btn-sm w-full text-center">
                <i class="fas fa-sign-out-alt"></i>
                Sign Out of All Sessions
              </a>
            </div>
          </div>
        </div>

        <!-- Tab: Academic (Students Only) -->
        <?php if ($role === 'student'): ?>
          <div class="tab-content" id="tab-academic">
            <div class="glass-card">
              <div class="glass-card-header">
                <h3>
                  <i class="fas fa-graduation-cap"></i>
                  Academic Overview
                </h3>
              </div>
              <div class="glass-card-body">
                <?php
                  // Quick academic stats
                  $stmt = $db->prepare(
                      "SELECT COUNT(DISTINCT e.semester_id) AS semesters,
                              COUNT(e.id)                  AS total_subjects,
                              AVG(g.final_grade)           AS overall_avg,
                              SUM(sub.units)               AS total_units,
                              SUM(g.final_grade >= 75)     AS passed,
                              SUM(g.final_grade <  75)     AS failed
                       FROM   enrollments e
                       JOIN   subjects sub ON sub.id = e.subject_id
                       LEFT JOIN grades g  ON g.enrollment_id = e.id
                       WHERE  e.student_id = :sid"
                  );
                  $stmt->execute([':sid' => $studentId ?? 0]);
                  $acadStats = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <div class="grid-2" style="gap:var(--space-4)">
                  <div class="glass-card" style="
                    background:rgba(108,99,255,0.08);
                    border-color:rgba(108,99,255,0.2)">
                    <div class="glass-card-body text-center">
                      <div class="text-xs text-muted mb-1">Semesters</div>
                      <div class="font-bold text-xl text-primary">
                        <?= $acadStats['semesters'] ?? 0 ?>
                      </div>
                    </div>
                  </div>
                  <div class="glass-card" style="
                    background:rgba(0,212,170,0.08);
                    border-color:rgba(0,212,170,0.2)">
                    <div class="glass-card-body text-center">
                      <div class="text-xs text-muted mb-1">Subjects Taken</div>
                      <div class="font-bold text-xl text-success">
                        <?= $acadStats['total_subjects'] ?? 0 ?>
                      </div>
                    </div>
                  </div>
                  <div class="glass-card" style="
                    background:rgba(255,159,67,0.08);
                    border-color:rgba(255,159,67,0.2)">
                    <div class="glass-card-body text-center">
                      <div class="text-xs text-muted mb-1">Overall Average</div>
                      <div class="font-bold text-xl text-warning">
                        <?= isset($acadStats['overall_avg'])
                            ? number_format($acadStats['overall_avg'], 2)
                            : '—' ?>
                      </div>
                    </div>
                  </div>
                  <div class="glass-card" style="
                    background:rgba(84,160,255,0.08);
                    border-color:rgba(84,160,255,0.2)">
                    <div class="glass-card-body text-center">
                      <div class="text-xs text-muted mb-1">Total Units</div>
                      <div class="font-bold text-xl text-info">
                        <?= $acadStats['total_units'] ?? 0 ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="d-flex gap-4 mt-5">
                  <div class="flex-1 text-center p-4 rounded-lg"
                       style="background:var(--success-bg);
                              border-radius:var(--radius-md)">
                    <div class="text-xs text-muted mb-1">Passed</div>
                    <div class="font-bold text-xl text-success">
                      <?= $acadStats['passed'] ?? 0 ?>
                    </div>
                  </div>
                  <div class="flex-1 text-center p-4"
                       style="background:var(--danger-bg);
                              border-radius:var(--radius-md)">
                    <div class="text-xs text-muted mb-1">Failed</div>
                    <div class="font-bold text-xl text-danger">
                      <?= $acadStats['failed'] ?? 0 ?>
                    </div>
                  </div>
                </div>

                <div class="mt-5">
                  <a href="student/grades.php"
                     class="btn btn-primary w-full text-center">
                    <i class="fas fa-graduation-cap"></i>
                    View Full Grade History
                  </a>
                </div>

              </div>
            </div>
          </div>
        <?php endif; ?>

      </div><!-- /right column -->

    </div><!-- /grid-2 -->

  </div><!-- /page-content -->
</div><!-- /main-content -->

<!-- Password strength JS -->
<script>
const pwInput    = document.getElementById('new-password');
const confirmPw  = document.getElementById('confirm-password');
const strengthWrap= document.getElementById('pw-strength-wrap');
const strengthBar = document.getElementById('pw-strength-bar');
const strengthLbl = document.getElementById('pw-strength-label');

if (pwInput) {
  pwInput.addEventListener('input', function () {
    const val = this.value;
    strengthWrap.style.display = val.length > 0 ? 'block' : 'none';

    let score = 0;
    if (val.length >= 8)              score++;
    if (/[A-Z]/.test(val))           score++;
    if (/[0-9]/.test(val))           score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;

    const levels = [
      { pct: 25,  cls: 'danger',  lbl: 'Weak',      color: 'var(--danger)'  },
      { pct: 50,  cls: 'warning', lbl: 'Fair',      color: 'var(--warning)' },
      { pct: 75,  cls: 'info',    lbl: 'Good',      color: 'var(--info)'    },
      { pct: 100, cls: 'success', lbl: 'Strong',    color: 'var(--success)' },
    ];

    const level = levels[score - 1] || levels[0];
    strengthBar.style.width      = level.pct + '%';
    strengthBar.style.background = level.color;
    strengthLbl.textContent      = level.lbl;
    strengthLbl.style.color      = level.color;
  });
}

// Confirm password match indicator
if (confirmPw) {
  confirmPw.addEventListener('input', function () {
    if (!pwInput.value) return;
    if (this.value === pwInput.value) {
      this.classList.add('is-valid');
      this.classList.remove('is-invalid');
    } else {
      this.classList.add('is-invalid');
      this.classList.remove('is-valid');
    }
  });
}
</script>

<?php include 'shared/footer.php'; ?>