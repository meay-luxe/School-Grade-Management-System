<?php
/* ============================================================
   GradeMS — Admin: Manage Students
   File: admin/manage_students.php
   ============================================================ */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../config/DB.php';

Auth::startSession();
Auth::requireRole('admin');

$pageTitle  = 'Student Records';
$activePage = '/admin/manage_students.php';
$success = $error = '';
$csvSummary = null;

/* ── HANDLE POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';

        /* ── ADD STUDENT ── */
        if ($action === 'add') {
            $studentNum = trim($_POST['student_number'] ?? '');
            $name       = trim($_POST['name'] ?? '');
            $email      = strtolower(trim($_POST['email'] ?? ''));
            $course     = trim($_POST['course'] ?? '');
            $year       = trim($_POST['year_level'] ?? '');
            $section    = strtoupper(trim($_POST['section'] ?? ''));

            if (!$studentNum || !$name || !$email || !$course || !$year || !$section) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } else {
                $exists = DB::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
                if ($exists) {
                    $error = 'A user with that email already exists.';
                } else {
                    // Create user account with default password = student number
                    $hash = Auth::hashPassword($studentNum);
                    DB::execute(
                        'INSERT INTO users (name, email, password, role, is_active, created_at)
                         VALUES (?, ?, ?, "student", 1, NOW())',
                        [$name, $email, $hash]
                    );
                    $userId = DB::lastInsertId();
                    DB::execute(
                        'INSERT INTO students (user_id, student_number, course, year_level, section)
                         VALUES (?, ?, ?, ?, ?)',
                        [$userId, $studentNum, $course, $year, $section]
                    );
                    Auth::logAction('Student Added', "Student: {$name} ({$studentNum})");
                    $success = "Student {$name} added. Default password is their student number.";
                }
            }
        }

        /* ── DELETE STUDENT ── */
        if ($action === 'delete') {
            $sid = (int)($_POST['student_id'] ?? 0);
            if ($sid) {
                $s = DB::fetchOne('SELECT user_id FROM students WHERE id = ?', [$sid]);
                if ($s) {
                    DB::execute('DELETE FROM students WHERE id = ?', [$sid]);
                    DB::execute('DELETE FROM users WHERE id = ?', [$s['user_id']]);
                    Auth::logAction('Student Deleted', "Student ID: {$sid}");
                    $success = 'Student record deleted.';
                }
            }
        }

        /* ── CSV IMPORT ── */
        if ($action === 'import_csv') {
            // SECURITY: validate file upload
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'File upload failed. Please try again.';
            } else {
                $file     = $_FILES['csv_file'];
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
                if (!in_array($mimeType, $allowedMimes)) {
                    $error = 'Only CSV files are allowed.';
                } else {
                    $added = $skipped = 0;
                    $skipReasons = [];

                    if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                        $headers = fgetcsv($handle); // skip header row
                        $row = 0;
                        while (($data = fgetcsv($handle)) !== false) {
                            $row++;
                            if (count($data) < 6) {
                                $skipped++;
                                $skipReasons[] = "Row {$row}: Not enough columns";
                                continue;
                            }
                            [$sNum, $name, $email, $course, $year, $section] = array_map('trim', $data);
                            $email = strtolower($email);

                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $skipped++;
                                $skipReasons[] = "Row {$row}: Invalid email ({$email})";
                                continue;
                            }
                            $exists = DB::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
                            if ($exists) {
                                $skipped++;
                                $skipReasons[] = "Row {$row}: Email already exists ({$email})";
                                continue;
                            }

                            $hash = Auth::hashPassword($sNum);
                            DB::execute(
                                'INSERT INTO users (name, email, password, role, is_active, created_at)
                                 VALUES (?, ?, ?, "student", 1, NOW())',
                                [$name, $email, $hash]
                            );
                            $uid = DB::lastInsertId();
                            DB::execute(
                                'INSERT INTO students (user_id, student_number, course, year_level, section)
                                 VALUES (?, ?, ?, ?, ?)',
                                [$uid, $sNum, $course, $year, strtoupper($section)]
                            );
                            $added++;
                        }
                        fclose($handle);
                    }
                    Auth::logAction('CSV Import', "Added: {$added}, Skipped: {$skipped}");
                    $csvSummary = compact('added', 'skipped', 'skipReasons');
                }
            }
        }
    }
}

/* ── FETCH STUDENTS ── */
$search  = $_GET['search']  ?? '';
$course  = $_GET['course']  ?? '';
$yearLvl = $_GET['year']    ?? '';

$sql = 'SELECT s.*, u.name, u.email, u.is_active
        FROM students s JOIN users u ON s.user_id = u.id
        WHERE 1=1';
$params = [];
if ($search) { $sql .= ' AND (u.name LIKE ? OR s.student_number LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
if ($course) { $sql .= ' AND s.course = ?'; $params[] = $course; }
if ($yearLvl){ $sql .= ' AND s.year_level = ?'; $params[] = $yearLvl; }
$sql .= ' ORDER BY s.student_number ASC';
$students = DB::fetchAll($sql, $params);

include __DIR__ . '/../shared/header.php';
?>

<div class="topbar">
  <div class="topbar-left">
    <div>
      <div class="topbar-title">Student Records</div>
      <div class="topbar-subtitle">Manage all enrolled students</div>
    </div>
  </div>
  <div class="topbar-right">
    <button class="btn btn-secondary btn-sm" onclick="showModal('modal-import-csv')">
      <i class="fas fa-file-csv"></i> Import CSV
    </button>
    <button class="btn btn-primary btn-sm" onclick="showModal('modal-add-student')">
      <i class="fas fa-plus"></i> Add Student
    </button>
  </div>
</div>

<div class="page-content">

<?php if ($success): ?>
  <div style="background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#4ade80;font-size:13.5px;">
    ✅ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>
<?php if ($error): ?>
  <div style="background:rgba(251,113,133,0.1);border:1px solid rgba(251,113,133,0.25);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#fb7185;font-size:13.5px;">
    ❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>
<?php if ($csvSummary): ?>
  <div style="background:rgba(79,158,255,0.1);border:1px solid rgba(79,158,255,0.25);border-radius:10px;padding:16px 20px;margin-bottom:20px;font-size:13.5px;">
    <div style="font-weight:600;color:var(--accent-blue);margin-bottom:8px">📊 CSV Import Summary</div>
    <div>✅ <strong><?= $csvSummary['added'] ?></strong> students added</div>
    <div>⚠️ <strong><?= $csvSummary['skipped'] ?></strong> rows skipped</div>
    <?php if ($csvSummary['skipReasons']): ?>
      <details style="margin-top:8px;cursor:pointer">
        <summary style="color:var(--text-secondary);font-size:12px">View skipped rows</summary>
        <ul style="margin-top:6px;padding-left:16px;font-size:12px;color:var(--text-muted)">
          <?php foreach ($csvSummary['skipReasons'] as $r): ?>
            <li><?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="table-card">
  <div class="table-header">
    <div class="table-title">All Students (<?= count($students) ?>)</div>
    <div class="table-actions">
      <form method="GET" style="display:flex;gap:8px">
        <input class="search-input" name="search" placeholder="🔍  Search name or ID..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        <select class="search-input" name="course" style="width:120px" onchange="this.form.submit()">
          <option value="">All Courses</option>
          <?php foreach (['BSCS','BSIT','BSEd','BSA','BSN'] as $c): ?>
            <option value="<?= $c ?>" <?= $course===$c?'selected':'' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
        <select class="search-input" name="year" style="width:130px" onchange="this.form.submit()">
          <option value="">All Years</option>
          <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $y): ?>
            <option value="<?= $y ?>" <?= $yearLvl===$y?'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-glass btn-sm" type="submit">Filter</button>
      </form>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>Student No.</th><th>Name</th><th>Email</th><th>Course</th><th>Year</th><th>Section</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($students as $s): ?>
        <tr>
          <td class="font-mono fs-13"><?= htmlspecialchars($s['student_number'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-secondary fs-13"><?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge badge-student"><?= htmlspecialchars($s['course'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td class="text-secondary"><?= htmlspecialchars($s['year_level'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-muted"><?= htmlspecialchars($s['section'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="<?= App::url('/admin/manage_students.php') ?>?view=<?= $s['id'] ?>" class="btn btn-glass btn-sm btn-icon" title="View">👁</a>
              <form method="POST" style="display:inline"
                onsubmit="return confirm('Delete student <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>? This cannot be undone.')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action"     value="delete">
                <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                <button class="btn btn-rose btn-sm btn-icon" type="submit" title="Delete">🗑</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($students)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">No students found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Student Modal -->
<div class="modal-overlay" id="modal-add-student">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-add-student')">✕</button>
    <div class="modal-title">Add New Student</div>
    <div class="modal-sub">Default password will be the student number</div>
    <form method="POST">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Student Number</label>
          <input class="form-input" name="student_number" placeholder="2024-0001" required>
        </div>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input class="form-input" name="name" placeholder="Maria Santos" required>
        </div>
        <div class="form-group form-full">
          <label class="form-label">Email Address</label>
          <input class="form-input" type="email" name="email" placeholder="student@school.edu" required>
        </div>
        <div class="form-group">
          <label class="form-label">Course</label>
          <select class="form-select" name="course" required>
            <option value="">Select course...</option>
            <?php foreach (['BSCS','BSIT','BSEd','BSA','BSN'] as $c): ?>
              <option value="<?= $c ?>"><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Year Level</label>
          <select class="form-select" name="year_level" required>
            <option value="">Select year...</option>
            <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $y): ?>
              <option value="<?= $y ?>"><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Section</label>
          <input class="form-input" name="section" placeholder="A" maxlength="5" required>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-glass" onclick="closeModal('modal-add-student')">Cancel</button>
          <button type="submit" class="btn btn-blue">Add Student</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- CSV Import Modal -->
<div class="modal-overlay" id="modal-import-csv">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-import-csv')">✕</button>
    <div class="modal-title">Import Students via CSV</div>
    <div class="modal-sub">Bulk-add student records from a spreadsheet file</div>
    <form method="POST" enctype="multipart/form-data">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="import_csv">
      <label class="profile-upload" style="padding:40px" for="csv-file-input">
        <div style="font-size:36px">📂</div>
        <div style="font-size:15px;font-weight:600">Click to select CSV file</div>
        <div style="font-size:12px;color:var(--text-muted)" id="csv-filename">
          Required columns: student_number, name, email, course, year_level, section
        </div>
      </label>
      <input type="file" id="csv-file-input" name="csv_file" accept=".csv,text/csv"
        style="display:none" onchange="document.getElementById('csv-filename').textContent=this.files[0]?.name||'No file chosen'">

      <div style="background:rgba(79,158,255,0.08);border:1px solid rgba(79,158,255,0.2);border-radius:8px;padding:14px;margin-top:16px;font-size:13px;color:var(--text-secondary)">
        💡 <strong style="color:var(--accent-blue)">CSV Format:</strong>
        student_number, name, email, course, year_level, section<br>
        Example: 2024-0001, Maria Santos, maria@school.edu, BSCS, 1st Year, A
      </div>

      <div class="form-actions" style="padding-top:16px">
        <button type="button" class="btn btn-glass" onclick="closeModal('modal-import-csv')">Cancel</button>
        <button type="submit" class="btn btn-teal">Import File</button>
      </div>
    </form>
  </div>
</div>

</div><!-- /page-content -->

<?php include __DIR__ . '/../shared/footer.php'; ?>
