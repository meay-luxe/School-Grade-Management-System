<?php
// ============================================================
// Grade.php — Grade OOP Model
// School Grade Management System
// ============================================================

require_once __DIR__ . '/../config/DB.php';
require_once __DIR__ . '/../helpers/GradeCalculator.php';

class Grade {

    private PDO $db;

    public function __construct() {
        $this->db = DB::getInstance();
    }

    // ── Get Grades By Enrollment ──────────────────────────────
    public function getByEnrollment(int $enrollmentId): array|false {
        $stmt = $this->db->prepare(
            "SELECT g.*,
                    e.student_id,
                    e.subject_id,
                    e.semester_id
             FROM   grades g
             JOIN   enrollments e ON e.id = g.enrollment_id
             WHERE  g.enrollment_id = :enrollment_id
             LIMIT  1"
        );
        $stmt->execute([':enrollment_id' => $enrollmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Get Grades By Subject + Semester ──────────────────────
    public function getBySubjectSemester(
        int $subjectId,
        int $semesterId
    ): array {
        $stmt = $this->db->prepare(
            "SELECT g.*,
                    s.first_name, s.last_name,
                    s.student_number,
                    e.id         AS enrollment_id,
                    e.status     AS enrollment_status
             FROM   enrollments e
             JOIN   students s   ON s.id = e.student_id
             LEFT JOIN grades g  ON g.enrollment_id = e.id
             WHERE  e.subject_id  = :subject_id
             AND    e.semester_id = :semester_id
             ORDER  BY s.last_name, s.first_name"
        );
        $stmt->execute([
            ':subject_id'  => $subjectId,
            ':semester_id' => $semesterId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Get All Grades (Admin View) ───────────────────────────
    public function getAll(array $filters = []): array {
        $sql = "SELECT g.*,
                       s.first_name    AS student_fname,
                       s.last_name     AS student_lname,
                       s.student_id    AS student_number,
                       sub.name        AS subject_name,
                       sub.code        AS subject_code,
                       sub.units,
                       sem.name        AS semester_name,
                       sem.school_year,
                       t.first_name    AS teacher_fname,
                       t.last_name     AS teacher_lname
                FROM   grades g
                JOIN   enrollments e   ON e.id = g.enrollment_id
                JOIN   students s      ON s.id = e.student_id
                JOIN   subjects sub    ON sub.id = e.subject_id
                JOIN   semesters sem   ON sem.id = e.semester_id
                LEFT JOIN teacher_subjects ts ON ts.subject_id = sub.id
                              AND ts.semester_id = sem.id
                LEFT JOIN teachers t   ON t.id = ts.teacher_id
                WHERE  1=1";
        $params = [];

        if (!empty($filters['semester_id'])) {
            $sql .= " AND e.semester_id = :semester_id";
            $params[':semester_id'] = $filters['semester_id'];
        }

        if (!empty($filters['subject_id'])) {
            $sql .= " AND e.subject_id = :subject_id";
            $params[':subject_id'] = $filters['subject_id'];
        }

        if (!empty($filters['student_id'])) {
            $sql .= " AND e.student_id = :student_id";
            $params[':student_id'] = $filters['student_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (s.first_name LIKE :search
                      OR   s.last_name  LIKE :search
                      OR   s.student_number LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_locked'])) {
            $sql .= " AND g.is_locked = :is_locked";
            $params[':is_locked'] = $filters['is_locked'];
        }

        $sql .= " ORDER BY sem.school_year DESC, s.last_name, s.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Save / Update Grade ───────────────────────────────────
    public function save(int $enrollmentId, array $scores): bool {
        try {
            // Check if locked
            $existing = $this->getByEnrollment($enrollmentId);
            if ($existing && $existing['is_locked']) return false;

            // Calculate final grade
            $midterm    = $scores['midterm']    ?? null;
            $finalExam  = $scores['final_exam'] ?? null;
            $finalGrade = GradeCalculator::computeFinal(
                $midterm  !== null ? (float) $midterm  : null,
                $finalExam !== null ? (float) $finalExam : null
            );
            $gpa      = $finalGrade !== null ? GradeCalculator::gradeToGPA($finalGrade) : null;
            $standing = GradeCalculator::assignRemarks($finalGrade);

            if ($existing) {
                // Update
                $stmt = $this->db->prepare(
                    "UPDATE grades
                     SET    prelim      = :prelim,
                            midterm     = :midterm,
                            prefinal    = :prefinal,
                            final_exam  = :final_exam,
                            final_grade = :final_grade,
                            gpa         = :gpa,
                            standing    = :standing,
                            updated_at  = NOW()
                     WHERE  enrollment_id = :enrollment_id"
                );
            } else {
                // Insert
                $stmt = $this->db->prepare(
                    "INSERT INTO grades
                        (enrollment_id, prelim, midterm,
                         prefinal, final_exam, final_grade, gpa, standing)
                     VALUES
                        (:enrollment_id, :prelim, :midterm,
                         :prefinal, :final_exam, :final_grade, :gpa, :standing)"
                );
            }

            return $stmt->execute([
                ':enrollment_id' => $enrollmentId,
                ':prelim'        => $scores['prelim']     ?? null,
                ':midterm'       => $scores['midterm']    ?? null,
                ':prefinal'      => $scores['prefinal']   ?? null,
                ':final_exam'    => $scores['final_exam'] ?? null,
                ':final_grade'   => $finalGrade,
                ':gpa'           => $gpa,
                ':standing'      => $standing,
            ]);

        } catch (PDOException $e) {
            error_log('[Grade::save] ' . $e->getMessage());
            return false;
        }
    }

    // ── Lock / Unlock Grade ───────────────────────────────────
    public function toggleLock(int $enrollmentId): bool {
        $stmt = $this->db->prepare(
            "UPDATE grades
             SET    is_locked = !is_locked,
                    locked_at = IF(is_locked = 0, NOW(), NULL)
             WHERE  enrollment_id = :enrollment_id"
        );
        return $stmt->execute([':enrollment_id' => $enrollmentId]);
    }

    // ── Lock All In Subject/Semester ──────────────────────────
    public function lockAll(int $subjectId, int $semesterId): bool {
        $stmt = $this->db->prepare(
            "UPDATE grades g
             JOIN   enrollments e ON e.id = g.enrollment_id
             SET    g.is_locked = 1,
                    g.locked_at = NOW()
             WHERE  e.subject_id  = :subject_id
             AND    e.semester_id = :semester_id"
        );
        return $stmt->execute([
            ':subject_id'  => $subjectId,
            ':semester_id' => $semesterId,
        ]);
    }

    // ── Get Grade Summary (for reports) ──────────────────────
    public function getSummary(int $semesterId): array {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*)                         AS total_grades,
                AVG(g.final_grade)               AS average_grade,
                MAX(g.final_grade)               AS highest_grade,
                MIN(g.final_grade)               AS lowest_grade,
                SUM(g.final_grade >= 75)         AS passed_count,
                SUM(g.final_grade < 75)          AS failed_count,
                SUM(g.is_locked)                 AS locked_count
             FROM   grades g
             JOIN   enrollments e ON e.id = g.enrollment_id
             WHERE  e.semester_id = :semester_id"
        );
        $stmt->execute([':semester_id' => $semesterId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Grade Distribution ────────────────────────────────────
    public function getDistribution(int $semesterId): array {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN g.final_grade BETWEEN 90 AND 100 THEN 1 ELSE 0 END) AS excellent,
                SUM(CASE WHEN g.final_grade BETWEEN 80 AND  89 THEN 1 ELSE 0 END) AS good,
                SUM(CASE WHEN g.final_grade BETWEEN 70 AND  79 THEN 1 ELSE 0 END) AS average,
                SUM(CASE WHEN g.final_grade BETWEEN 60 AND  69 THEN 1 ELSE 0 END) AS poor,
                SUM(CASE WHEN g.final_grade  <  60              THEN 1 ELSE 0 END) AS failed
             FROM   grades g
             JOIN   enrollments e ON e.id = g.enrollment_id
             WHERE  e.semester_id = :semester_id"
        );
        $stmt->execute([':semester_id' => $semesterId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}