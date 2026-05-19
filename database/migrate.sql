-- ============================================================
--  GradeMS — Migration Script
--  File: database/migrate.sql
--
--  Run this if you already have an existing database and need
--  to apply the schema changes without dropping all data.
--
--  Run: mysql -u root -p grade_management < database/migrate.sql
-- ============================================================

USE grade_management;

-- ── students: add missing columns ──────────────────────────
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS first_name  VARCHAR(80) NOT NULL DEFAULT '' AFTER student_number,
  ADD COLUMN IF NOT EXISTS last_name   VARCHAR(80) NOT NULL DEFAULT '' AFTER first_name,
  ADD COLUMN IF NOT EXISTS middle_name VARCHAR(80) DEFAULT NULL        AFTER last_name,
  ADD COLUMN IF NOT EXISTS contact_no  VARCHAR(30) DEFAULT NULL        AFTER section,
  MODIFY COLUMN course     VARCHAR(50) NOT NULL DEFAULT '',
  MODIFY COLUMN year_level VARCHAR(20) NOT NULL DEFAULT '',
  MODIFY COLUMN section    VARCHAR(10) DEFAULT NULL;

-- ── teachers: add missing columns ──────────────────────────
ALTER TABLE teachers
  ADD COLUMN IF NOT EXISTS employee_id    VARCHAR(30)  DEFAULT NULL UNIQUE AFTER user_id,
  ADD COLUMN IF NOT EXISTS first_name     VARCHAR(80)  NOT NULL DEFAULT '' AFTER employee_id,
  ADD COLUMN IF NOT EXISTS last_name      VARCHAR(80)  NOT NULL DEFAULT '' AFTER first_name,
  ADD COLUMN IF NOT EXISTS middle_name    VARCHAR(80)  DEFAULT NULL        AFTER last_name,
  ADD COLUMN IF NOT EXISTS specialization VARCHAR(150) DEFAULT NULL        AFTER department,
  ADD COLUMN IF NOT EXISTS contact_no     VARCHAR(30)  DEFAULT NULL        AFTER specialization;

-- ── semesters: add missing columns ─────────────────────────
ALTER TABLE semesters
  ADD COLUMN IF NOT EXISTS name       VARCHAR(50) NOT NULL DEFAULT '' AFTER id,
  ADD COLUMN IF NOT EXISTS start_date DATE DEFAULT NULL                AFTER is_active,
  ADD COLUMN IF NOT EXISTS end_date   DATE DEFAULT NULL                AFTER start_date;

-- Back-fill the name column from existing semester + school_year data
UPDATE semesters
SET name = CONCAT(semester, ' ', school_year)
WHERE name = '' OR name IS NULL;

-- ── subjects: add missing columns ──────────────────────────
ALTER TABLE subjects
  ADD COLUMN IF NOT EXISTS description TEXT       DEFAULT NULL     AFTER units,
  ADD COLUMN IF NOT EXISTS is_active   TINYINT(1) NOT NULL DEFAULT 1 AFTER teacher_id;

-- ── enrollments: add status column ─────────────────────────
ALTER TABLE enrollments
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'enrolled' AFTER semester_id;

-- ── grades: add missing columns ────────────────────────────
ALTER TABLE grades
  ADD COLUMN IF NOT EXISTS prelim     DECIMAL(5,2) DEFAULT NULL AFTER enrollment_id,
  ADD COLUMN IF NOT EXISTS prefinal   DECIMAL(5,2) DEFAULT NULL AFTER midterm,
  ADD COLUMN IF NOT EXISTS final_exam DECIMAL(5,2) DEFAULT NULL AFTER prefinal,
  ADD COLUMN IF NOT EXISTS finals     DECIMAL(5,2) DEFAULT NULL AFTER final_exam,
  ADD COLUMN IF NOT EXISTS gpa        DECIMAL(4,2) DEFAULT NULL AFTER final_grade,
  ADD COLUMN IF NOT EXISTS standing   VARCHAR(50)  DEFAULT NULL AFTER gpa,
  ADD COLUMN IF NOT EXISTS locked_at  DATETIME     DEFAULT NULL AFTER is_locked;

-- Back-fill finals from existing midterm/finals data where applicable
-- (finals column is an alias kept for API compatibility)
UPDATE grades SET finals = finals WHERE finals IS NULL AND final_grade IS NOT NULL;

-- ── teacher_subjects: create if not exists ──────────────────
CREATE TABLE IF NOT EXISTS teacher_subjects (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id  INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_ts (teacher_id, subject_id, semester_id),
  FOREIGN KEY (teacher_id)  REFERENCES teachers(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id)  REFERENCES subjects(id)  ON DELETE CASCADE,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
  INDEX idx_ts_teacher  (teacher_id),
  INDEX idx_ts_subject  (subject_id),
  INDEX idx_ts_semester (semester_id)
) ENGINE=InnoDB;

-- Migrate existing subjects.teacher_id assignments into teacher_subjects
-- for the currently active semester (safe to run multiple times due to INSERT IGNORE)
INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id, semester_id)
SELECT
  s.teacher_id,
  s.id          AS subject_id,
  sem.id        AS semester_id
FROM subjects s
JOIN semesters sem ON sem.is_active = 1
WHERE s.teacher_id IS NOT NULL;

-- ── api_tokens: create if not exists ───────────────────────
CREATE TABLE IF NOT EXISTS api_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token      VARCHAR(128) NOT NULL UNIQUE,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SELECT 'Migration complete.' AS status;
