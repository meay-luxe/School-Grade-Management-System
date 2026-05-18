<?php
/* ============================================================
   GradeMS — Profile Page (shared for all roles)
   File: profile.php
   ============================================================ */

require_once __DIR__ . '/helpers/Auth.php';
require_once __DIR__ . '/config/DB.php';

Auth::startSession();
Auth::requireRole(['admin', 'teacher', 'student']);

$pageTitle  = 'My Profile';
$activePage = '/profile.php';

$success = $error = '';

$user = Auth::currentUser();

/* ── HANDLE POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';

        /* ── UPDATE INFO ── */
        if ($action === 'update_info') {
            $name  = trim($_POST['name']  ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            if (!$name || !$email) {
                $error = 'Name and email are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } else {
                $taken = DB::fetchOne(
                    'SELECT id FROM users WHERE email = ? AND id != ?',
                    [$email, $user['id']]
                );
                if ($taken) {
                    $error = 'That email is already in use by another account.';
                } else {
                    DB::execute(
                        'UPDATE users SET name = ?, email = ? WHERE id = ?',
                        [$name, $email, $user['id']]
                    );
                    $_SESSION['name']  = $name;
                    $_SESSION['email'] = $email;
                    Auth::logAction('Profile Updated', "Name: {$name}, Email: {$email}");
                    $success = 'Profile updated successfully.';
                    $user = Auth::currentUser();
                }
            }
        }

        /* ── CHANGE PASSWORD ── */
        if ($action === 'change_password') {
            $current  = $_POST['current_password']  ?? '';
            $new      = $_POST['new_password']       ?? '';
            $confirm  = $_POST['confirm_password']   ?? '';

            $stored = DB::fetchOne('SELECT password FROM users WHERE id = ?', [$user['id']]);
            if (!Auth::verifyPassword($current, $stored['password'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                $error = 'New passwords do not match.';
            } else {
                $hash = Auth::hashPassword($new);
                DB::execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $user['id']]);
                Auth::logAction('Password Changed', 'User changed their own password');
                $success = 'Password changed successfully.';
            }
        }

        /* ── UPLOAD PHOTO ── */
        if ($action === 'upload_photo') {
            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload failed. Please try again.';
            } else {
                $file = $_FILES['photo'];

                // SECURITY: validate MIME type with finfo (not just extension)
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                if (!array_key_exists($mimeType, $allowed)) {
                    $error = 'Only JPG and PNG files are allowed.';
                } elseif ($file['size'] > 2 * 1024 * 1024) {
                    $error = 'File size must be under 2MB.';
                } else {
                    // SECURITY: random filename — never trust original name
                    $ext      = $allowed[$mimeType];
                    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                    $uploadDir= __DIR__ . '/uploads/avatars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $dest = $uploadDir . $filename;

                    // GD: resize to 200×200
                    $src = $mimeType === 'image/jpeg'
                        ? imagecreatefromjpeg($file['tmp_name'])
                        : imagecreatefrompng($file['tmp_name']);

                    if ($src) {
                        $resized = imagescale($src, 200, 200);
                        $saved   = $mimeType === 'image/jpeg'
                            ? imagejpeg($resized, $dest, 90)
                            : imagepng($resized, $dest);
                        imagedestroy($src);
                        imagedestroy($resized);

                        if ($saved) {
                            // Delete old photo
                            if ($user['photo_path']) {
                                $old = __DIR__ . '/uploads/avatars/' . basename($user['photo_path']);
                                if (file_exists($old)) unlink($old);
                            }
                            DB::execute(
                                'UPDATE users SET photo_path = ? WHERE id = ?',
                                ['/uploads/avatars/' . $filename, $user['id']]
                            );
                            Auth::logAction('Profile Photo Updated', '');
                            $success = 'Profile photo updated.';
                            $user = Auth::currentUser();
                        } else {
                            $error = 'Failed to save image.';
                        }
                    } else {
                        $error = 'Could not read the image file.';
                    }
                }
            }
        }
    }
}

/* ── Avatar initials ── */
$parts    = explode(' ', $user['name']);
$initials = strtoupper(substr($parts[0], 0, 1) . (count($parts) > 1 ? substr($parts[count($parts)-1], 0, 1) : ''));
$avatarBgs= ['admin'=>'linear-gradient(135deg,#a78bfa,#7c3aed)','teacher'=>'linear-gradient(135deg,#4f9eff,#2563eb)','student'=>'linear-gradient(135deg,#2dd4bf,#0f766e)'];
$avatarBg = $avatarBgs[$_SESSION['role']] ?? $avatarBgs['student'];

include __DIR__ . '/shared/header.php';
?>

<div class="topbar">
  <div>
    <div class="topbar-title">My Profile</div>
    <div class="topbar-subtitle text-secondary">Manage your account information</div>
  </div>
</div>

<?php if ($success): ?>
  <div style="background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#4ade80;font-size:13.5px;">✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div style="background:rgba(251,113,133,0.1);border:1px solid rgba(251,113,133,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#fb7185;font-size:13.5px;">❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="two-col">

  <!-- Left: info edit -->
  <div class="glass-card" style="padding:28px">
    <div style="display:flex;align-items:center;gap:20px;margin-bottom:28px">
      <?php if ($user['photo_path']): ?>
        <img src="<?= htmlspecialchars($user['photo_path'], ENT_QUOTES, 'UTF-8') ?>"
          style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--glass-border-hover)">
      <?php else: ?>
        <div class="profile-avatar" style="background:<?= $avatarBg ?>"><?= $initials ?></div>
      <?php endif; ?>
      <div>
        <div style="font-size:18px;font-weight:700"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px"><?= ucfirst($_SESSION['role']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>

    <div class="divider"></div>

    <form method="POST">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="update_info">
      <div class="form-grid">
        <div class="form-group form-full">
          <label class="form-label">Full Name</label>
          <input class="form-input" name="name" value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-group form-full">
          <label class="form-label">Email Address</label>
          <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-blue">Save Changes</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Right: photo + password -->
  <div style="display:flex;flex-direction:column;gap:18px">

    <!-- Photo upload -->
    <div class="glass-card" style="padding:24px">
      <div style="font-size:15px;font-weight:600;margin-bottom:16px">📷 Profile Photo</div>
      <form method="POST" enctype="multipart/form-data">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="upload_photo">
        <label class="profile-upload" for="photo-input">
          <div style="font-size:28px">☁️</div>
          <div id="photo-label">Click to upload photo</div>
          <div style="font-size:11px;color:var(--text-muted)">JPG, PNG · Max 2MB · Auto-resized to 200×200</div>
        </label>
        <input type="file" id="photo-input" name="photo" accept="image/jpeg,image/png" style="display:none"
          onchange="document.getElementById('photo-label').textContent = this.files[0]?.name || 'Click to upload photo'">
        <button type="submit" class="btn btn-teal w-full" style="margin-top:12px;justify-content:center">Upload Photo</button>
      </form>
    </div>

    <!-- Change password -->
    <div class="glass-card" style="padding:24px">
      <div style="font-size:15px;font-weight:600;margin-bottom:16px">🔒 Change Password</div>
      <form method="POST">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="change_password">
        <div style="display:flex;flex-direction:column;gap:12px">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input class="form-input" type="password" name="current_password" placeholder="••••••••" required>
          </div>
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input class="form-input" type="password" name="new_password" placeholder="Min. 8 characters" minlength="8" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input class="form-input" type="password" name="confirm_password" placeholder="Repeat new password" required>
          </div>
          <button type="submit" class="btn btn-blue">Update Password</button>
        </div>
      </form>
    </div>
  </div>

</div>

<?php include __DIR__ . '/shared/footer.php'; ?>
