<?php
// ============================================================
// Subject.php — Subject OOP Model
// School Grade Management System
// ============================================================

require_once __DIR__ . '/../config/DB.php';

class Subject {

    private PDO $db;

    public function __construct() {
        $this->db = DB::getInstance();
    }

    // ── Get All Subjects ──────────────────────────────────────
    public function getAll(array $filters = []): array {
        $sql    = "SELECT sub.*,
                          COUNT(DISTINCT ts.teacher_id) AS teacher_count,
                          COUNT(DISTINCT e.student_id)  AS enrolled_count
                   FROM   subjects sub
                   LEFT JOIN teacher_subjects ts ON ts.subject_id = sub.id
                   LEFT JOIN enrollments e        ON e.subject_id = sub.id
                   WHERE  1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (sub.name LIKE :search OR sub.code LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND sub.is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['semester_id'])) {
            $sql .= " AND ts.semester_id = :semester_id";
            $params[':semester_id'] = $filters['semester_id'];
        }

        $sql .= " GROUP BY sub.id ORDER BY sub.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Get By ID ─────────────────────────────────────────────
    public function getById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT sub.*
             FROM   subjects sub
             WHERE  sub.id = :id
             LIMIT  1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Create Subject ────────────────────────────────────────
    public function create(array $data): int|false {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO subjects
                    (code, name, units, description, is_active)
                 VALUES
                    (:code, :name, :units, :description, 1)"
            );
            $stmt->execute([
                ':code'        => strtoupper(trim($data['code'])),
                ':name'        => $data['name'],
                ':units'       => $data['units'] ?? 3,
                ':description' => $data['description'] ?? null,
            ]);
            return (int) $this->db->lastInsertId();

        } catch (PDOException $e) {
            error_log('[Subject::create] ' . $e->getMessage());
            return false;
        }
    }

    // ── Update Subject ────────────────────────────────────────
    public function update(int $id, array $data): bool {
        try {
            $stmt = $this->db->prepare(
                "UPDATE subjects
                 SET    code        = :code,
                        name        = :name,
                        units       = :units,
                        description = :description,
                        is_active   = :is_active
                 WHERE  id = :id"
            );
            return $stmt->execute([
                ':code'        => strtoupper(trim($data['code'])),
                ':name'        => $data['name'],
                ':units'       => $data['units'] ?? 3,
                ':description' => $data['description'] ?? null,
                ':is_active'   => $data['is_active'] ?? 1,
                ':id'          => $id,
            ]);
        } catch (PDOException $e) {
            error_log('[Subject::update] ' . $e->getMessage());
            return false;
        }
    }

    // ── Delete Subject ────────────────────────────────────────
    public function delete(int $id): bool {
        try {
            // Check if subject has grades
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM enrollments WHERE subject_id = :id"
            );
            $stmt->execute([':id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) return false;

            $stmt = $this->db->prepare("DELETE FROM subjects WHERE id = :id");
            return $stmt->execute([':id' => $id]);

        } catch (PDOException $e) {
            error_log('[Subject::delete] ' . $e->getMessage());
            return false;
        }
    }

    // ── Assign Teacher ────────────────────────────────────────
    public function assignTeacher(
        int $subjectId,
        int $teacherId,
        int $semesterId
    ): bool {
        try {
            // Check if assignment already exists
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM teacher_subjects
                 WHERE  subject_id = :subject_id
                 AND    teacher_id = :teacher_id
                 AND    semester_id = :semester_id"
            );
            $stmt->execute([
                ':subject_id'  => $subjectId,
                ':teacher_id'  => $teacherId,
                ':semester_id' => $semesterId,
            ]);
            if ((int) $stmt->fetchColumn() > 0) return true; // already assigned

            $stmt = $this->db->prepare(
                "INSERT INTO teacher_subjects
                    (subject_id, teacher_id, semester_id)
                 VALUES
                    (:subject_id, :teacher_id, :semester_id)"
            );
            return $stmt->execute([
                ':subject_id'  => $subjectId,
                ':teacher_id'  => $teacherId,
                ':semester_id' => $semesterId,
            ]);
        } catch (PDOException $e) {
            error_log('[Subject::assignTeacher] ' . $e->getMessage());
            return false;
        }
    }

    // ── Remove Teacher Assignment ─────────────────────────────
    public function removeTeacher(
        int $subjectId,
        int $teacherId,
        int $semesterId
    ): bool {
        $stmt = $this->db->prepare(
            "DELETE FROM teacher_subjects
             WHERE  subject_id  = :subject_id
             AND    teacher_id  = :teacher_id
             AND    semester_id = :semester_id"
        );
        return $stmt->execute([
            ':subject_id'  => $subjectId,
            ':teacher_id'  => $teacherId,
            ':semester_id' => $semesterId,
        ]);
    }

    // ── Get Assigned Teachers ─────────────────────────────────
    public function getTeachers(int $subjectId, int $semesterId): array {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.first_name, t.last_name,
                    t.employee_id, t.department,
                    u.email
             FROM   teacher_subjects ts
             JOIN   teachers t ON t.id = ts.teacher_id
             JOIN   users    u ON u.id = t.user_id
             WHERE  ts.subject_id  = :subject_id
             AND    ts.semester_id = :semester_id"
        );
        $stmt->execute([
            ':subject_id'  => $subjectId,
            ':semester_id' => $semesterId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Code Exists ───────────────────────────────────────────
    public function codeExists(string $code, int $excludeId = 0): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM subjects
             WHERE  code = :code AND id != :exclude_id"
        );
        $stmt->execute([
            ':code'       => strtoupper($code),
            ':exclude_id' => $excludeId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // ── Dropdown List ─────────────────────────────────────────
    public function getDropdownList(): array {
        $stmt = $this->db->query(
            "SELECT id, code, name, units
             FROM   subjects
             WHERE  is_active = 1
             ORDER  BY name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Count ─────────────────────────────────────────────────
    public function count(bool $activeOnly = false): int {
        $sql = "SELECT COUNT(*) FROM subjects";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        return (int) $this->db->query($sql)->fetchColumn();
    }
}