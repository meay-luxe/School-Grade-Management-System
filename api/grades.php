<?php
/* ============================================================
   GradeMS — JSON Grades API
   File: api/grades.php
   Method: GET
   Auth:   Bearer token (Authorization header)
   Params: ?student_id=X  OR  ?subject_id=X
   ============================================================ */

require_once __DIR__ . '/../config/DB.php';
require_once __DIR__ . '/../helpers/GradeCalculator.php';

/* ── Always return JSON ── */
header('Content-Type: application/json; charset=utf-8');

/* ── CORS (adjust origin in production) ── */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* ── Only accept GET ── */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

/* ── SECURITY: Bearer token authentication ── */
$authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? apache_request_headers()['Authorization']
    ?? '';

if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Provide a Bearer token in the Authorization header.']);
    exit;
}

$token = trim($m[1]);

// SECURITY: prepared statement — validate token against DB
$apiUser = DB::fetchOne(
    'SELECT at.user_id, u.role FROM api_tokens at
     JOIN users u ON at.user_id = u.id
     WHERE at.token = ? AND at.is_active = 1',
    [$token]
);

if (!$apiUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token.']);
    exit;
}

/* ── Route: ?student_id=X ── */
if (isset($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];

    // Role restriction: students can only access their own data
    if ($apiUser['role'] === 'student') {
        $self = DB::fetchOne(
            'SELECT s.id FROM students s WHERE s.user_id = ?',
            [$apiUser['user_id']]
        );
        if (!$self || $self['id'] !== $studentId) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden. You may only access your own grades.']);
            exit;
        }
    }

    $student = DB::fetchOne(
        'SELECT s.id, u.name, s.student_number, s.course, s.year_level, s.section
         FROM students s JOIN users u ON s.user_id = u.id
         WHERE s.id = ?',
        [$studentId]
    );

    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => "Student with ID {$studentId} not found."]);
        exit;
    }

    $grades = DB::fetchAll(
        'SELECT sub.code, sub.name AS subject_name, sub.units,
                g.midterm, g.finals, g.final_grade, g.remarks,
                sem.school_year, sem.semester
         FROM grades g
         JOIN enrollments e ON g.enrollment_id = e.id
         JOIN subjects sub  ON e.subject_id    = sub.id
         JOIN semesters sem ON e.semester_id   = sem.id
         WHERE e.student_id = ?
         ORDER BY sem.school_year DESC, sem.semester, sub.code',
        [$studentId]
    );

    $gpa      = GradeCalculator::computeGPA($grades);
    $standing = GradeCalculator::getStanding($gpa);

    http_response_code(200);
    echo json_encode([
        'status'  => 200,
        'message' => 'OK',
        'student' => $student,
        'grades'  => array_map(fn($g) => [
            'subject_code'  => $g['code'],
            'subject_name'  => $g['subject_name'],
            'units'         => (int)$g['units'],
            'midterm'       => $g['midterm'] !== null ? (float)$g['midterm'] : null,
            'finals'        => $g['finals']  !== null ? (float)$g['finals']  : null,
            'final_grade'   => $g['final_grade'] !== null ? (float)$g['final_grade'] : null,
            'remarks'       => $g['remarks'],
            'school_year'   => $g['school_year'],
            'semester'      => $g['semester'],
        ], $grades),
        'gpa'      => $gpa,
        'standing' => $standing,
    ], JSON_PRETTY_PRINT);
    exit;
}

/* ── Route: ?subject_id=X ── */
if (isset($_GET['subject_id'])) {
    $subjectId = (int)$_GET['subject_id'];

    // Teachers may only access their own subject
    if ($apiUser['role'] === 'teacher') {
        $owns = DB::fetchOne(
            'SELECT s.id FROM subjects s
             JOIN teachers t ON s.teacher_id = t.id
             WHERE s.id = ? AND t.user_id = ?',
            [$subjectId, $apiUser['user_id']]
        );
        if (!$owns) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden. You may only access grades for your own subjects.']);
            exit;
        }
    }

    $subject = DB::fetchOne('SELECT * FROM subjects WHERE id = ?', [$subjectId]);
    if (!$subject) {
        http_response_code(404);
        echo json_encode(['error' => "Subject with ID {$subjectId} not found."]);
        exit;
    }

    $grades = DB::fetchAll(
        'SELECT u.name AS student_name, s.student_number,
                g.midterm, g.finals, g.final_grade, g.remarks
         FROM grades g
         JOIN enrollments e ON g.enrollment_id = e.id
         JOIN students s    ON e.student_id    = s.id
         JOIN users u       ON s.user_id       = u.id
         WHERE e.subject_id = ?
         ORDER BY u.name ASC',
        [$subjectId]
    );

    $passCount = count(array_filter($grades, fn($g) => $g['remarks'] === 'Passed'));
    $total     = count($grades);
    $passRate  = $total > 0 ? round(($passCount / $total) * 100, 1) : 0;

    http_response_code(200);
    echo json_encode([
        'status'    => 200,
        'message'   => 'OK',
        'subject'   => $subject,
        'grades'    => $grades,
        'summary'   => [
            'total_students' => $total,
            'passed'         => $passCount,
            'failed'         => count(array_filter($grades, fn($g) => $g['remarks'] === 'Failed')),
            'incomplete'     => count(array_filter($grades, fn($g) => $g['remarks'] === 'Incomplete')),
            'pass_rate'      => $passRate,
        ],
    ], JSON_PRETTY_PRINT);
    exit;
}

/* ── No valid param ── */
http_response_code(400);
echo json_encode([
    'error'   => 'Bad Request. Provide student_id or subject_id as a query parameter.',
    'example' => '/api/grades.php?student_id=5',
]);
