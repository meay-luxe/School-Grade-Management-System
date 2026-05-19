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
  first_name     VARCHAR(80)   NOT NULL DEFAULT '',
  last_name      VARCHAR(80)   NOT NULL DEFAULT '',
  middle_name    VARCHAR(80)   DEFAULT NULL,
  course         VARCHAR(50)   NOT NULL DEFAULT '',
  year_level     VARCHAR(20)   NOT NULL DEFAULT '',
  section        VARCHAR(10)   DEFAULT NULL,
  contact_no     VARCHAR(30)   DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── TEACHERS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS teachers (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED  NOT NULL UNIQUE,
  employee_id    VARCHAR(30)   DEFAULT NULL UNIQUE,
  first_name     VARCHAR(80)   NOT NULL DEFAULT '',
  last_name      VARCHAR(80)   NOT NULL DEFAULT '',
  middle_name    VARCHAR(80)   DEFAULT NULL,
  department     VARCHAR(100)  DEFAULT NULL,
  specialization VARCHAR(150)  DEFAULT NULL,
  contact_no     VARCHAR(30)   DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── SEMESTERS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS semesters (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(50)                           NOT NULL DEFAULT '',
  school_year    VARCHAR(20)                           NOT NULL,
  semester       ENUM('1st Semester','2nd Semester','Summer') NOT NULL DEFAULT '1st Semester',
  is_active      TINYINT(1)                            NOT NULL DEFAULT 0,
  start_date     DATE                                  DEFAULT NULL,
  end_date       DATE                                  DEFAULT NULL,
  grade_deadline DATE                                  DEFAULT NULL,
  created_at     DATETIME                              NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sem (school_year, semester)
) ENGINE=InnoDB;

-- ── SUBJECTS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subjects (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(20)   NOT NULL UNIQUE,
  name        VARCHAR(150)  NOT NULL,
  units       TINYINT       NOT NULL DEFAULT 3,
  description TEXT          DEFAULT NULL,
  teacher_id  INT UNSIGNED  DEFAULT NULL,
  is_active   TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
  INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB;

-- ── ENROLLMENTS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS enrollments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  status      VARCHAR(20)  NOT NULL DEFAULT 'enrolled',
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
  enrollment_id INT UNSIGNED                         NOT NULL UNIQUE,
  prelim        DECIMAL(5,2)                         DEFAULT NULL,
  midterm       DECIMAL(5,2)                         DEFAULT NULL,
  prefinal      DECIMAL(5,2)                         DEFAULT NULL,
  final_exam    DECIMAL(5,2)                         DEFAULT NULL,
  finals        DECIMAL(5,2)                         DEFAULT NULL,  -- alias kept for API compat
  final_grade   DECIMAL(5,2)                         DEFAULT NULL,
  gpa           DECIMAL(4,2)                         DEFAULT NULL,
  standing      VARCHAR(50)                          DEFAULT NULL,
  remarks       ENUM('Passed','Failed','Incomplete')  NOT NULL DEFAULT 'Incomplete',
  is_locked     TINYINT(1)                           NOT NULL DEFAULT 0,
  locked_at     DATETIME                             DEFAULT NULL,
  updated_at    DATETIME                             ON UPDATE CURRENT_TIMESTAMP,
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

-- ── TEACHER SUBJECTS (many-to-many: teacher ↔ subject per semester) ──
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

-- ── Users (password for all accounts: "password") ──────────
INSERT INTO users (name, email, password, role) VALUES
  ('Dr. John Admin',    'admin@school.edu',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
  ('Prof. Ana Reyes',   'ana.reyes@school.edu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
  ('Prof. Mark Torres', 'mark.t@school.edu',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
  ('Maria Santos',      'maria.s@school.edu',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
  ('Juan Dela Cruz',    'juan.dc@school.edu',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
  ('Ana Lim',           'ana.lim@school.edu',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

-- ── Teachers (now includes first_name, last_name, employee_id) ─
INSERT INTO teachers (user_id, employee_id, first_name, last_name, department, specialization) VALUES
  (2, 'EMP-001', 'Ana',  'Reyes',  'Computer Science', 'Programming'),
  (3, 'EMP-002', 'Mark', 'Torres', 'Physics',          'Applied Physics');

-- ── Students (now includes first_name, last_name) ──────────
INSERT INTO students (user_id, student_number, first_name, last_name, course, year_level, section) VALUES
  (4, '2024-0001', 'Maria', 'Santos',    'BSCS', '2nd Year', 'A'),
  (5, '2024-0002', 'Juan',  'Dela Cruz', 'BSIT', '1st Year', 'B'),
  (6, '2024-0003', 'Ana',   'Lim',       'BSCS', '3rd Year', 'A');

-- ── Semesters (now includes name column) ───────────────────
INSERT INTO semesters (name, school_year, semester, is_active, grade_deadline) VALUES
  ('1st Semester AY 2024-2025', 'AY 2024-2025', '1st Semester', 1, '2024-12-20'),
  ('2nd Semester AY 2023-2024', 'AY 2023-2024', '2nd Semester', 0, '2024-06-15');

-- ── Subjects (now includes description, is_active) ─────────
INSERT INTO subjects (code, name, units, description, teacher_id, is_active) VALUES
  ('MATH101', 'Mathematics 101',  3, 'Fundamental mathematics concepts.',    1, 1),
  ('PHY201',  'Physics 201',      4, 'Mechanics, thermodynamics and waves.', 2, 1),
  ('ENG101',  'English 101',      3, 'Academic writing and communication.',  NULL, 1),
  ('CS101',   'CS Fundamentals',  3, 'Introduction to computer science.',    1, 1);

-- ── Teacher–Subject assignments per semester ───────────────
INSERT INTO teacher_subjects (teacher_id, subject_id, semester_id) VALUES
  (1, 1, 1),  -- Ana Reyes  → MATH101, Sem 1
  (2, 2, 1),  -- Mark Torres → PHY201, Sem 1
  (1, 4, 1);  -- Ana Reyes  → CS101,   Sem 1

-- ── Enrollments ────────────────────────────────────────────
INSERT INTO enrollments (student_id, subject_id, semester_id, status) VALUES
  (1, 1, 1, 'enrolled'),
  (1, 4, 1, 'enrolled'),
  (2, 2, 1, 'enrolled'),
  (3, 3, 1, 'enrolled');

-- ── Grades (now includes prelim, prefinal, final_exam, gpa, standing) ─
INSERT INTO grades
  (enrollment_id, prelim, midterm, prefinal, final_exam, finals, final_grade, gpa, standing, remarks)
VALUES
  (1, 85.00, 88.00, 90.00, 92.00, 92.00, 90.00, 1.25, 'Passed', 'Passed'),
  (2, 70.00, 72.00, 68.00, 68.00, 68.00, 70.00, 2.50, 'Failed', 'Failed'),
  (3, 94.00, 95.00, 96.00, 97.00, 97.00, 96.00, 1.00, 'Passed', 'Passed');

-- ── Demo API token (token value: "demo-api-token-12345") ───
INSERT INTO api_tokens (user_id, token) VALUES (1, 'demo-api-token-12345');
