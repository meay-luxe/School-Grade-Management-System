-- ============================================================
--  GradeMS — Database Schema
--  File: database/schema.sql
--  Run: mysql -u root -p grade_management < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS grade_management
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE grade_management;

-- ── USERS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150)                        NOT NULL,
  email        VARCHAR(200)                        NOT NULL UNIQUE,
  password     VARCHAR(255)                        NOT NULL,  -- bcrypt
  role         ENUM('admin','teacher','student')   NOT NULL,
  photo_path   VARCHAR(300)                        DEFAULT NULL,
  is_active    TINYINT(1)                          NOT NULL DEFAULT 1,
  created_at   DATETIME                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME                            ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role),
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- ── STUDENTS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS students (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED  NOT NULL UNIQUE,
  student_number VARCHAR(30)   NOT NULL UNIQUE,
  course         VARCHAR(20)   NOT NULL,
  year_level     VARCHAR(20)   NOT NULL,
  section        VARCHAR(10)   NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── TEACHERS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS teachers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL UNIQUE,
  department VARCHAR(100) DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── SEMESTERS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS semesters (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_year    VARCHAR(20)                           NOT NULL,
  semester       ENUM('1st Semester','2nd Semester','Summer') NOT NULL,
  is_active      TINYINT(1)                            NOT NULL DEFAULT 0,
  grade_deadline DATE                                  DEFAULT NULL,
  created_at     DATETIME                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sem (school_year, semester)
) ENGINE=InnoDB;

-- ── SUBJECTS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subjects (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(20)  NOT NULL UNIQUE,
  name       VARCHAR(150) NOT NULL,
  units      TINYINT      NOT NULL DEFAULT 3,
  teacher_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
  INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB;

-- ── ENROLLMENTS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS enrollments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  enrolled_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enroll (student_id, subject_id, semester_id),
  FOREIGN KEY (student_id)  REFERENCES students(id)  ON DELETE CASCADE,
  FOREIGN KEY (subject_id)  REFERENCES subjects(id)  ON DELETE CASCADE,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
  INDEX idx_student (student_id),
  INDEX idx_subject (subject_id)
) ENGINE=InnoDB;

-- ── GRADES ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS grades (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT UNSIGNED                       NOT NULL UNIQUE,
  midterm       DECIMAL(5,2)                       DEFAULT NULL,
  finals        DECIMAL(5,2)                       DEFAULT NULL,
  final_grade   DECIMAL(5,2)                       DEFAULT NULL,
  remarks       ENUM('Passed','Failed','Incomplete') NOT NULL DEFAULT 'Incomplete',
  is_locked     TINYINT(1)                         NOT NULL DEFAULT 0,
  updated_at    DATETIME                           ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
  INDEX idx_remarks (remarks)
) ENGINE=InnoDB;

-- ── AUDIT LOGS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  action     VARCHAR(100) NOT NULL,
  details    TEXT         DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user   (user_id),
  INDEX idx_action (action),
  INDEX idx_date   (created_at)
) ENGINE=InnoDB;

-- ── LOGIN ATTEMPTS (brute force protection) ─────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
  email         VARCHAR(200) NOT NULL PRIMARY KEY,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
  attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── API TOKENS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS api_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token      VARCHAR(128) NOT NULL UNIQUE,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  SEED DATA — development / demo
-- ============================================================

-- Admin account (password: password)
INSERT INTO users (name, email, password, role) VALUES
  ('Dr. John Admin',    'admin@school.edu',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
  ('Prof. Ana Reyes',   'ana.reyes@school.edu','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
  ('Prof. Mark Torres', 'mark.t@school.edu',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
  ('Maria Santos',      'maria.s@school.edu',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
  ('Juan Dela Cruz',    'juan.dc@school.edu',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
  ('Ana Lim',           'ana.lim@school.edu',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

INSERT INTO teachers (user_id) VALUES (2), (3);

INSERT INTO students (user_id, student_number, course, year_level, section) VALUES
  (4, '2024-0001', 'BSCS', '2nd Year', 'A'),
  (5, '2024-0002', 'BSIT', '1st Year', 'B'),
  (6, '2024-0003', 'BSCS', '3rd Year', 'A');

INSERT INTO semesters (school_year, semester, is_active, grade_deadline) VALUES
  ('AY 2024-2025', '1st Semester', 1, '2024-12-20'),
  ('AY 2023-2024', '2nd Semester', 0, '2024-06-15');

INSERT INTO subjects (code, name, units, teacher_id) VALUES
  ('MATH101', 'Mathematics 101',   3, 1),
  ('PHY201',  'Physics 201',       4, 2),
  ('ENG101',  'English 101',       3, NULL),
  ('CS101',   'CS Fundamentals',   3, 1);

INSERT INTO enrollments (student_id, subject_id, semester_id) VALUES
  (1, 1, 1), (1, 4, 1),
  (2, 2, 1),
  (3, 3, 1);

INSERT INTO grades (enrollment_id, midterm, finals, final_grade, remarks) VALUES
  (1, 88, 92, 90.00, 'Passed'),
  (2, 72, 68, 70.00, 'Failed'),
  (3, 95, 97, 96.00, 'Passed');

-- Demo API token (token value: "demo-api-token-12345")
INSERT INTO api_tokens (user_id, token) VALUES (1, 'demo-api-token-12345');
