<?php
// ============================================================
// ApiController.php — API Routing Logic
// School Grade Management System
// ============================================================

require_once __DIR__ . '/../config/DB.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/GradeCalculator.php';
require_once __DIR__ . '/../models/Grade.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Subject.php';

class ApiController {

    private PDO   $db;
    private array $payload  = [];
    private int   $userId   = 0;
    private string $role    = '';

    public function __construct() {
        $this->db = DB::getInstance();
        $this->setJsonHeaders();
        $this->authenticate();
    }

    // ══════════════════════════════════════════════════════════
    // 1. SETUP
    // ══════════════════════════════════════════════════════════

    private function setJsonHeaders(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit();
        }
    }

    private function authenticate(): void {
        // Bearer token from Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        $token = '';
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        }

        if (empty($token)) {
            $this->error('Unauthorized. Bearer token required.', 401);
        }

        // Validate token against users table
        $stmt = $this->db->prepare(
            "SELECT id, role, is_active
             FROM   users
             WHERE  api_token = :token
             LIMIT  1"
        );
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['is_active']) {
            $this->error('Invalid or expired token.', 401);
        }

        $this->userId  = (int) $user['id'];
        $this->role    = $user['role'];
    }

    // ══════════════════════════════════════════════════════════
    // 2. ROUTER
    // ══════════════════════════════════════════════════════════

    public function route(): void {
        $method   = $_SERVER['REQUEST_METHOD'];
        $endpoint = trim($_GET['endpoint'] ?? '', '/');

        // Parse request body for POST/PUT
        if (in_array($method, ['POST', 'PUT'])) {
            $raw           = file_get_contents('php://input');
            $this->payload = json_decode($raw, true) ?? [];
        }

        // Route map
        $routes = [
            'GET' => [
                'grades'            => 'getGrades',
                'grades/student'    => 'getStudentGrades',
                'grades/subject'    => 'getSubjectGrades',
                'students'          => 'getStudents',
                'teachers'          => 'getTeachers',
                'subjects'          => 'getSubjects',
                'semesters'         => 'getSemesters',
                'summary'           => 'getSummary',
                'reports/overview'  => 'getReportsOverview',
            ],
            'POST' => [
                'grades'            => 'saveGrade',
                'grades/lock'       => 'lockGrade',
                'grades/lock-all'   => 'lockAllGrades',
            ],
            'PUT' => [
                'grades'            => 'updateGrade',
            ],
        ];

        $methodRoutes = $routes[$method] ?? [];

        if (isset($methodRoutes[$endpoint])) {
            $handler = $methodRoutes[$endpoint];
            $this->$handler();
        } else {
            $this->error("Endpoint [{$method} /{$endpoint}] not found.", 404);
        }
    }

    // ══════════════════════════════════════════════════════════
    // 3. GET ENDPOINTS
    // ══════════════════════════════════════════════════════════

    // GET /grades
    private function getGrades(): void {
        $this->requireRole(['admin']);

        $semesterId = (int) ($_GET['semester_id'] ?? 0);
        $subjectId  = (int) ($_GET['subject_id']  ?? 0);
        $limit      = min((int) ($_GET['limit']   ?? 50), 200);
        $offset     = (int) ($_GET['offset']      ?? 0);

        $sql    = "SELECT g.*,
                          s.first_name, s.last_name,
                          s.student_id AS student_number,
                          sub.code AS subject_code,
                          sub.name AS subject_name,
                          sem.name AS semester_name,
                          sem.school_year
                   FROM   grades g
                   JOIN   enrollments e   ON e.id = g.enrollment_id
                   JOIN   students s      ON s.id = e.student_id
                   JOIN   subjects sub    ON sub.id = e.subject_id
                   JOIN   semesters sem   ON sem.id = e.semester_id
                   WHERE  1=1";
        $params = [];

        if ($semesterId) {
            $sql .= " AND e.semester_id = :semester_id";
            $params[':semester_id'] = $semesterId;
        }
        if ($subjectId) {
            $sql .= " AND e.subject_id = :subject_id";
            $params[':subject_id'] = $subjectId;
        }

        $sql .= " ORDER BY s.last_name, s.first_name
                  LIMIT :limit OFFSET :offset";
        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->success($grades, [
            'count'  => count($grades),
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    // GET /grades/student
    private function getStudentGrades(): void {
        // Students can only get their own grades
        if ($this->role === 'student') {
            $studentModel = new Student();
            $student      = $studentModel->getByUserId($this->userId);
            $studentId    = $student['id'] ?? 0;
        } else {
            $this->requireRole(['admin', 'teacher']);
            $studentId = (int) ($_GET['student_id'] ?? 0);
        }

        if (!$studentId) {
            $this->error('student_id is required.', 422);
        }

        $semesterId = (int) ($_GET['semester_id'] ?? 0);

        $studentModel = new Student();
        $grades       = $studentModel->getGrades($studentId, $semesterId ?: null);

        // Compute GPA
        $totalPoints = 0;
        $totalUnits  = 0;
        foreach ($grades as $g) {
            if ($g['final_grade'] !== null) {
                $gpa          = GradeCalculator::getGPA((float) $g['final_grade']);
                $totalPoints += $gpa * $g['units'];
                $totalUnits  += $g['units'];
            }
        }
        $semesterGPA = $totalUnits > 0
            ? round($totalPoints / $totalUnits, 2)
            : null;

        $this->success($grades, [
            'student_id'   => $studentId,
            'semester_gpa' => $semesterGPA,
            'count'        => count($grades),
        ]);
    }

    // GET /grades/subject
    private function getSubjectGrades(): void {
        $this->requireRole(['admin', 'teacher']);

        $subjectId  = (int) ($_GET['subject_id']  ?? 0);
        $semesterId = (int) ($_GET['semester_id'] ?? 0);

        if (!$subjectId || !$semesterId) {
            $this->error('subject_id and semester_id are required.', 422);
        }

        // Teacher can only access their own subjects
        if ($this->role === 'teacher') {
            $teacherModel = new Teacher();
            $teacher      = $teacherModel->getByUserId($this->userId);

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM teacher_subjects
                 WHERE teacher_id  = :tid
                 AND   subject_id  = :sid
                 AND   semester_id = :semid"
            );
            $stmt->execute([
                ':tid'   => $teacher['id'],
                ':sid'   => $subjectId,
                ':semid' => $semesterId,
            ]);
            if ((int) $stmt->fetchColumn() === 0) {
                $this->error('Access denied to this subject.', 403);
            }
        }

        $gradeModel = new Grade();
        $grades     = $gradeModel->getBySubjectSemester($subjectId, $semesterId);

        $this->success($grades, ['count' => count($grades)]);
    }

    // GET /students
    private function getStudents(): void {
        $this->requireRole(['admin', 'teacher']);

        $search    = trim($_GET['search'] ?? '');
        $isActive  = isset($_GET['is_active'])
            ? (int) $_GET['is_active']
            : null;

        $studentModel = new Student();
        $students     = $studentModel->getAll([
            'search'    => $search,
            'is_active' => $isActive,
        ]);

        $this->success($students, ['count' => count($students)]);
    }

    // GET /teachers
    private function getTeachers(): void {
        $this->requireRole(['admin']);

        $teacherModel = new Teacher();
        $teachers     = $teacherModel->getAll([
            'search' => trim($_GET['search'] ?? ''),
        ]);

        $this->success($teachers, ['count' => count($teachers)]);
    }

    // GET /subjects
    private function getSubjects(): void {
        $this->requireRole(['admin', 'teacher']);

        $subjectModel = new Subject();
        $subjects     = $subjectModel->getAll([
            'search'    => trim($_GET['search'] ?? ''),
            'is_active' => 1,
        ]);

        $this->success($subjects, ['count' => count($subjects)]);
    }

    // GET /semesters
    private function getSemesters(): void {
        $stmt = $this->db->query(
            "SELECT * FROM semesters ORDER BY school_year DESC, id DESC"
        );
        $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->success($semesters);
    }

    // GET /summary
    private function getSummary(): void {
        $this->requireRole(['admin']);

        $semesterId = (int) ($_GET['semester_id'] ?? 0);

        $gradeModel = new Grade();
        $summary    = $gradeModel->getSummary($semesterId);
        $dist       = $gradeModel->getDistribution($semesterId);

        $this->success([
            'summary'      => $summary,
            'distribution' => $dist,
        ]);
    }

    // GET /reports/overview
    private function getReportsOverview(): void {
        $this->requireRole(['admin']);

        $totalStudents = (int) $this->db->query(
            "SELECT COUNT(*) FROM students s
             JOIN users u ON u.id = s.user_id WHERE u.is_active = 1"
        )->fetchColumn();

        $totalTeachers = (int) $this->db->query(
            "SELECT COUNT(*) FROM teachers t
             JOIN users u ON u.id = t.user_id WHERE u.is_active = 1"
        )->fetchColumn();

        $totalSubjects = (int) $this->db->query(
            "SELECT COUNT(*) FROM subjects WHERE is_active = 1"
        )->fetchColumn();

        $activeSemester = $this->db->query(
            "SELECT * FROM semesters WHERE is_active = 1 LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        $this->success([
            'total_students'  => $totalStudents,
            'total_teachers'  => $totalTeachers,
            'total_subjects'  => $totalSubjects,
            'active_semester' => $activeSemester,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 4. POST ENDPOINTS
    // ══════════════════════════════════════════════════════════

    // POST /grades
    private function saveGrade(): void {
        $this->requireRole(['admin', 'teacher']);

        $enrollmentId = (int) ($this->payload['enrollment_id'] ?? 0);
        $scores       = [
            'prelim'     => $this->payload['prelim']     ?? null,
            'midterm'    => $this->payload['midterm']    ?? null,
            'prefinal'   => $this->payload['prefinal']   ?? null,
            'final_exam' => $this->payload['final_exam'] ?? null,
        ];

        if (!$enrollmentId) {
            $this->error('enrollment_id is required.', 422);
        }

        // Validate scores
        foreach ($scores as $key => $val) {
            if ($val !== null) {
                $val = (float) $val;
                if ($val < 0 || $val > 100) {
                    $this->error(
                        "{$key} must be between 0 and 100.",
                        422
                    );
                }
            }
        }

        // Teacher access check
        if ($this->role === 'teacher') {
            $this->checkTeacherEnrollmentAccess($enrollmentId);
        }

        $gradeModel = new Grade();
        $result     = $gradeModel->save($enrollmentId, $scores);

        if (!$result) {
            $this->error(
                'Failed to save grade. It may be locked.',
                400
            );
        }

        // Return updated grade
        $updated = $gradeModel->getByEnrollment($enrollmentId);

        Auth::logAction("API: Saved grade for enrollment {$enrollmentId}");
        $this->success($updated, [], 'Grade saved successfully.');
    }

    // POST /grades/lock
    private function lockGrade(): void {
        $this->requireRole(['admin']);

        $enrollmentId = (int) ($this->payload['enrollment_id'] ?? 0);
        if (!$enrollmentId) {
            $this->error('enrollment_id is required.', 422);
        }

        $gradeModel = new Grade();
        $result     = $gradeModel->toggleLock($enrollmentId);

        if (!$result) {
            $this->error('Failed to toggle lock.', 400);
        }

        Auth::logAction("API: Toggled lock for enrollment {$enrollmentId}");
        $this->success([], [], 'Grade lock status updated.');
    }

    // POST /grades/lock-all
    private function lockAllGrades(): void {
        $this->requireRole(['admin']);

        $subjectId  = (int) ($this->payload['subject_id']  ?? 0);
        $semesterId = (int) ($this->payload['semester_id'] ?? 0);

        if (!$subjectId || !$semesterId) {
            $this->error(
                'subject_id and semester_id are required.',
                422
            );
        }

        $gradeModel = new Grade();
        $result     = $gradeModel->lockAll($subjectId, $semesterId);

        if (!$result) {
            $this->error('Failed to lock all grades.', 400);
        }

        Auth::logAction(
            "API: Locked all grades for subject {$subjectId} sem {$semesterId}"
        );
        $this->success([], [], 'All grades locked successfully.');
    }

    // PUT /grades
    private function updateGrade(): void {
        $this->saveGrade(); // Same logic as save
    }

    // ══════════════════════════════════════════════════════════
    // 5. HELPERS
    // ══════════════════════════════════════════════════════════

    private function requireRole(array $roles): void {
        if (!in_array($this->role, $roles)) {
            $this->error(
                'Access denied. Required role: '
                . implode(' or ', $roles) . '.',
                403
            );
        }
    }

    private function checkTeacherEnrollmentAccess(int $enrollmentId): void {
        $teacherModel = new Teacher();
        $teacher      = $teacherModel->getByUserId($this->userId);

        if (!$teacher) {
            $this->error('Teacher profile not found.', 403);
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM   enrollments e
             JOIN   teacher_subjects ts ON ts.subject_id  = e.subject_id
                        AND ts.semester_id = e.semester_id
             WHERE  e.id          = :enrollment_id
             AND    ts.teacher_id = :teacher_id"
        );
        $stmt->execute([
            ':enrollment_id' => $enrollmentId,
            ':teacher_id'    => $teacher['id'],
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            $this->error(
                'Access denied. This enrollment is not in your class.',
                403
            );
        }
    }

    // ── Response Helpers ──────────────────────────────────────

    private function success(
        mixed  $data    = [],
        array  $meta    = [],
        string $message = 'Success'
    ): void {
        http_response_code(200);
        echo json_encode([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }

    private function error(
        string $message,
        int    $code = 400
    ): void {
        http_response_code($code);
        echo json_encode([
            'status'  => 'error',
            'message' => $message,
            'data'    => null,
        ], JSON_PRETTY_PRINT);
        exit();
    }
}