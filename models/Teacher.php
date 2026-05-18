<?php
// ============================================================
// Teacher.php — Teacher OOP Model
// School Grade Management System
// ============================================================

require_once __DIR__ . '/../config/DB.php';

class Teacher {

    private PDO $db;

    public function __construct() {
        $this->db = DB::getInstance();
    }

    // ── Get All Teachers ──────────────────────────────────────
    public function getAll(array $filters = []): array {
        $sql    = "SELECT t.*, u.username, u.email, u.is_active,
                          u.created_at AS account_created
                   FROM   teachers t
                   JOIN   users u ON u.id = t.user_id
                   WHERE  1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (t.first_name LIKE :search
                      OR   t.last_name  LIKE :search
                      OR   t.employee_id LIKE :search
                      OR   u.email      LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['department'])) {
            $sql .= " AND t.department = :department";
            $params[':department'] = $filters['department'];
        }

        $sql .= " ORDER BY t.last_name, t.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Get By ID ─────────────────────────────────────────────
    public function getById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT t.*, u.username, u.email, u.is_active
             FROM   teachers t
             JOIN   users u ON u.id = t.user_id
             WHERE  t.id = :id
             LIMIT  1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Get By User ID ────────────────────────────────────────
    public function getByUserId(int $userId): array|false {
        $stmt = $this->db->prepare(
            "SELECT t.*, u.username, u.email, u.is_active
             FROM   teachers t
             JOIN   users u ON u.id = t.user_id
             WHERE  t.user_id = :user_id
             LIMIT  1"
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Create Teacher ────────────────────────────────────────
    public function create(array $data): int|false {
        try {
            $this->db->beginTransaction();

            // 1. Create user account
            $stmt = $this->db->prepare(
                "INSERT INTO users (username, email, password, role, is_active)
                 VALUES (:username, :email, :password, 'teacher', 1)"
            );
            $stmt->execute([
                ':username' => $data['username'],
                ':email'    => $data['email'],
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
            ]);
            $userId = (int) $this->db->lastInsertId();

            // 2. Create teacher profile
            $stmt = $this->db->prepare(
                "INSERT INTO teachers
                    (user_id, employee_id, first_name, last_name,
                     middle_name, department, contact_no, specialization)
                 VALUES
                    (:user_id, :employee_id, :first_name, :last_name,
                     :middle_name, :department, :contact_no, :specialization)"
            );
            $stmt->execute([
                ':user_id'        => $userId,
                ':employee_id'    => $data['employee_id'],
                ':first_name'     => $data['first_name'],
                ':last_name'      => $data['last_name'],
                ':middle_name'    => $data['middle_name']    ?? null,
                ':department'     => $data['department']     ?? null,
                ':contact_no'     => $data['contact_no']     ?? null,
                ':specialization' => $data['specialization'] ?? null,
            ]);
            $teacherId = (int) $this->db->lastInsertId();

            $this->db->commit();
            return $teacherId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('[Teacher::create] ' . $e->getMessage());
            return false;
        }
    }

    // ── Update Teacher ────────────────────────────────────────
    public function update(int $id, array $data): bool {
        try {
            $this->db->beginTransaction();

            $teacher = $this->getById($id);
            if (!$teacher) return false;

            // Update user
            $stmt = $this->db->prepare(
                "UPDATE users
                 SET    email     = :email,
                        is_active = :is_active
                 WHERE  id = :user_id"
            );
            $stmt->execute([
                ':email'     => $data['email'],
                ':is_active' => $data['is_active'] ?? 1,
                ':user_id'   => $teacher['user_id'],
            ]);

            if (!empty($data['password'])) {
                $stmt = $this->db->prepare(
                    "UPDATE users SET password = :password WHERE id = :user_id"
                );
                $stmt->execute([
                    ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
                    ':user_id'  => $teacher['user_id'],
                ]);
            }

            // Update teacher profile
            $stmt = $this->db->prepare(
                "UPDATE teachers
                 SET    first_name     = :first_name,
                        last_name      = :last_name,
                        middle_name    = :middle_name,
                        department     = :department,
                        contact_no     = :contact_no,
                        specialization = :specialization
                 WHERE  id = :id"
            );
            $stmt->execute([
                ':first_name'     => $data['first_name'],
                ':last_name'      => $data['last_name'],
                ':middle_name'    => $data['middle_name']    ?? null,
                ':department'     => $data['department']     ?? null,
                ':contact_no'     => $data['contact_no']     ?? null,
                ':specialization' => $data['specialization'] ?? null,
                ':id'             => $id,
            ]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('[Teacher::update] ' . $e->getMessage());
            return false;
        }
    }

    // ── Toggle Active ─────────────────────────────────────────
    public function toggleActive(int $id): bool {
        $teacher = $this->getById($id);
        if (!$teacher) return false;

        $stmt = $this->db->prepare(
            "UPDATE users SET is_active = !is_active WHERE id = :user_id"
        );
        return $stmt->execute([':user_id' => $teacher['user_id']]);
    }

    // ── Get Assigned Subjects ─────────────────────────────────
    public function getSubjects(int $teacherId, int $semesterId = null): array {
        $sql = "SELECT ts.*,
                       sub.name        AS subject_name,
                       sub.code        AS subject_code,
                       sub.units,
                       sub.description AS subject_desc,
                       sem.name        AS semester_name,
                       sem.school_year,
                       COUNT(e.id)     AS enrolled_count
                FROM   teacher_subjects ts
                JOIN   subjects sub  ON sub.id = ts.subject_id
                JOIN   semesters sem ON sem.id = ts.semester_id
                LEFT JOIN enrollments e ON e.subject_id = sub.id
                              AND e.semester_id = sem.id
                WHERE  ts.teacher_id = :teacher_id";
        $params = [':teacher_id' => $teacherId];

        if ($semesterId) {
            $sql .= " AND ts.semester_id = :semester_id";
            $params[':semester_id'] = $semesterId;
        }

        $sql .= " GROUP BY ts.id ORDER BY sem.school_year DESC, sub.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Count ─────────────────────────────────────────────────
    public function count(bool $activeOnly = false): int {
        $sql = "SELECT COUNT(*) FROM teachers t
                JOIN users u ON u.id = t.user_id";
        if ($activeOnly) $sql .= " WHERE u.is_active = 1";
        return (int) $this->db->query($sql)->fetchColumn();
    }

    // ── Get All for Dropdown ──────────────────────────────────
    public function getDropdownList(): array {
        $stmt = $this->db->query(
            "SELECT t.id,
                    CONCAT(t.first_name, ' ', t.last_name) AS full_name,
                    t.department
             FROM   teachers t
             JOIN   users u ON u.id = t.user_id
             WHERE  u.is_active = 1
             ORDER  BY t.last_name, t.first_name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}