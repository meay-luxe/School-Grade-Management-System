<?php
// ============================================================
// Student.php — Student OOP Model
// School Grade Management System
// ============================================================

require_once __DIR__ . '/../config/DB.php';

class Student {

    private PDO $db;

    public function __construct() {
        $this->db = DB::getInstance();
    }

    // ── Get All Students ──────────────────────────────────────
    public function getAll(array $filters = []): array {
        $sql    = "SELECT s.*, u.username, u.email, u.is_active,
                          u.created_at AS account_created
                   FROM   students s
                   JOIN   users u ON u.id = s.user_id
                   WHERE  1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (s.first_name LIKE :search
                      OR   s.last_name  LIKE :search
                      OR   s.student_id LIKE :search
                      OR   u.email      LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['year_level'])) {
            $sql .= " AND s.year_level = :year_level";
            $params[':year_level'] = $filters['year_level'];
        }

        $sql .= " ORDER BY s.last_name, s.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Get By ID ─────────────────────────────────────────────
    public function getById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username, u.email, u.is_active, u.created_at
             FROM   students s
             JOIN   users u ON u.id = s.user_id
             WHERE  s.id = :id
             LIMIT  1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Get By User ID ────────────────────────────────────────
    public function getByUserId(int $userId): array|false {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username, u.email, u.is_active
             FROM   students s
             JOIN   users u ON u.id = s.user_id
             WHERE  s.user_id = :user_id
             LIMIT  1"
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Get By Student Number ─────────────────────────────────
    public function getByStudentId(string $studentId): array|false {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username, u.email
             FROM   students s
             JOIN   users u ON u.id = s.user_id
             WHERE  s.student_id = :student_id
             LIMIT  1"
        );
        $stmt->execute([':student_id' => $studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Create Student ────────────────────────────────────────
    public function create(array $data): int|false {
        try {
            $this->db->beginTransaction();

            // 1. Create user account
            $stmt = $this->db->prepare(
                "INSERT INTO users (username, email, password, role, is_active)
                 VALUES (:username, :email, :password, 'student', 1)"
            );
            $stmt->execute([
                ':username' => $data['username'],
                ':email'    => $data['email'],
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
            ]);
            $userId = (int) $this->db->lastInsertId();

            // 2. Create student profile
            $stmt = $this->db->prepare(
                "INSERT INTO students
                    (user_id, student_id, first_name, last_name,
                     middle_name, year_level, section, course, contact_no)
                 VALUES
                    (:user_id, :student_id, :first_name, :last_name,
                     :middle_name, :year_level, :section, :course, :contact_no)"
            );
            $stmt->execute([
                ':user_id'     => $userId,
                ':student_id'  => $data['student_id'],
                ':first_name'  => $data['first_name'],
                ':last_name'   => $data['last_name'],
                ':middle_name' => $data['middle_name'] ?? null,
                ':year_level'  => $data['year_level'],
                ':section'     => $data['section']    ?? null,
                ':course'      => $data['course']     ?? null,
                ':contact_no'  => $data['contact_no'] ?? null,
            ]);
            $studentId = (int) $this->db->lastInsertId();

            $this->db->commit();
            return $studentId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('[Student::create] ' . $e->getMessage());
            return false;
        }
    }

    // ── Update Student ────────────────────────────────────────
    public function update(int $id, array $data): bool {
        try {
            $this->db->beginTransaction();

            // Get user_id first
            $student = $this->getById($id);
            if (!$student) return false;

            // Update user account
            $stmt = $this->db->prepare(
                "UPDATE users
                 SET    email     = :email,
                        is_active = :is_active
                 WHERE  id = :user_id"
            );
            $stmt->execute([
                ':email'     => $data['email'],
                ':is_active' => $data['is_active'] ?? 1,
                ':user_id'   => $student['user_id'],
            ]);

            // Update password if provided
            if (!empty($data['password'])) {
                $stmt = $this->db->prepare(
                    "UPDATE users SET password = :password WHERE id = :user_id"
                );
                $stmt->execute([
                    ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
                    ':user_id'  => $student['user_id'],
                ]);
            }

            // Update student profile
            $stmt = $this->db->prepare(
                "UPDATE students
                 SET    first_name  = :first_name,
                        last_name   = :last_name,
                        middle_name = :middle_name,
                        year_level  = :year_level,
                        section     = :section,
                        course      = :course,
                        contact_no  = :contact_no
                 WHERE  id = :id"
            );
            $stmt->execute([
                ':first_name'  => $data['first_name'],
                ':last_name'   => $data['last_name'],
                ':middle_name' => $data['middle_name'] ?? null,
                ':year_level'  => $data['year_level'],
                ':section'     => $data['section']    ?? null,
                ':course'      => $data['course']     ?? null,
                ':contact_no'  => $data['contact_no'] ?? null,
                ':id'          => $id,
            ]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('[Student::update] ' . $e->getMessage());
            return false;
        }
    }

    // ── Toggle Active Status ──────────────────────────────────
    public function toggleActive(int $id): bool {
        $student = $this->getById($id);
        if (!$student) return false;

        $stmt = $this->db->prepare(
            "UPDATE users SET is_active = !is_active WHERE id = :user_id"
        );
        return $stmt->execute([':user_id' => $student['user_id']]);
    }

    // ── Delete Student ────────────────────────────────────────
    public function delete(int $id): bool {
        try {
            $this->db->beginTransaction();

            $student = $this->getById($id);
            if (!$student) return false;

            // Cascade: grades, enrollments deleted by FK
            $this->db->prepare("DELETE FROM students WHERE id = :id")
                      ->execute([':id' => $id]);

            $this->db->prepare("DELETE FROM users WHERE id = :user_id")
                      ->execute([':user_id' => $student['user_id']]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('[Student::delete] ' . $e->getMessage());
            return false;
        }
    }

    // ── Get Student Grades ────────────────────────────────────
    public function getGrades(int $studentId, int $semesterId = null): array {
        $sql = "SELECT g.*,
                       sub.name        AS subject_name,
                       sub.code        AS subject_code,
                       sub.units,
                       sem.name        AS semester_name,
                       sem.school_year,
                       t.first_name    AS teacher_fname,
                       t.last_name     AS teacher_lname
                FROM   grades g
                JOIN   enrollments e  ON e.id = g.enrollment_id
                JOIN   subjects sub   ON sub.id = e.subject_id
                JOIN   semesters sem  ON sem.id = e.semester_id
                JOIN   teacher_subjects ts ON ts.subject_id = sub.id
                           AND ts.semester_id = sem.id
                JOIN   teachers t     ON t.id = ts.teacher_id
                WHERE  e.student_id = :student_id";
        $params = [':student_id' => $studentId];

        if ($semesterId) {
            $sql .= " AND e.semester_id = :semester_id";
            $params[':semester_id'] = $semesterId;
        }

        $sql .= " ORDER BY sem.school_year DESC, sem.name, sub.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Get Enrolled Subjects ─────────────────────────────────
    public function getEnrollments(int $studentId, int $semesterId = null): array {
        $sql = "SELECT e.*,
                       sub.name  AS subject_name,
                       sub.code  AS subject_code,
                       sub.units,
                       sem.name  AS semester_name,
                       sem.school_year
                FROM   enrollments e
                JOIN   subjects sub  ON sub.id = e.subject_id
                JOIN   semesters sem ON sem.id = e.semester_id
                WHERE  e.student_id = :student_id";
        $params = [':student_id' => $studentId];

        if ($semesterId) {
            $sql .= " AND e.semester_id = :semester_id";
            $params[':semester_id'] = $semesterId;
        }

        $sql .= " ORDER BY sem.school_year DESC, sub.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Count Total Students ──────────────────────────────────
    public function count(bool $activeOnly = false): int {
        $sql = "SELECT COUNT(*) FROM students s
                JOIN users u ON u.id = s.user_id";
        if ($activeOnly) $sql .= " WHERE u.is_active = 1";

        return (int) $this->db->query($sql)->fetchColumn();
    }

    // ── Student ID Exists ─────────────────────────────────────
    public function studentIdExists(string $studentId, int $excludeId = 0): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM students
             WHERE  student_id = :student_id AND id != :exclude_id"
        );
        $stmt->execute([':student_id' => $studentId, ':exclude_id' => $excludeId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}