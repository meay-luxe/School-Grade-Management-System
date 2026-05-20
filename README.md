# 🎓 GradeMS — School Grade Management System

A full-stack PHP + MySQL grade management system with a Glassmorphism dark UI.
Built as a 6-person team academic project.

---

## 📁 Project Structure

```
gradeapp/
├── assets/
│   ├── css/
│   │   ├── global.css            ← Full design system (layout, cards, tables, badges)
│   │   └── auth.css              ← Login page styles
│   └── js/
│       ├── app.js                ← Main app JS (sidebar, modals, charts, toasts, tables)
│       └── auth.js               ← Login form helpers
├── auth/
│   ├── login.php                 ← Login page + CSRF + brute force protection
│   └── logout.php                ← Destroys session, redirects to login
├── admin/
│   ├── dashboard.php             ← Admin home: stats, at-risk students, recent activity
│   ├── user_management.php       ← Create / deactivate / reset password for all users
│   ├── manage_students.php       ← Student records + bulk CSV import
│   ├── manage_subjects.php       ← Subjects + teacher assignment per semester
│   ├── manage_semesters.php      ← School year / semester management
│   ├── manage_enrollment.php     ← Enroll students into subjects (single + bulk)
│   ├── view_grades.php           ← View, filter, and lock all grades
│   ├── reports.php               ← Analytics: charts, top students, subject comparison
│   └── audit_log.php             ← Full system activity trail with pagination
├── teacher/
│   ├── dashboard.php             ← Teacher home: stats, subject list, recent grading
│   ├── my_subjects.php           ← Subjects assigned to this teacher with progress bars
│   ├── class_list.php            ← Students enrolled in a specific subject
│   ├── manage_grades.php         ← Grade entry with live midterm/finals calculation
│   └── reports.php               ← Subject breakdown + top performing students
├── student/
│   ├── dashboard.php             ← Student home: GPA ring, all-time stats, subject table
│   └── grades.php                ← Full grade history grouped by semester
├── api/
│   └── grades.php                ← JSON REST API (Bearer token auth)
├── controllers/
│   └── ApiController.php         ← Full REST API router (GET/POST/PUT endpoints)
├── shared/
│   ├── header.php                ← HTML head, CSS links, opens #app and #main
│   ├── sidebar.php               ← Role-aware navigation (admin/teacher/student)
│   └── footer.php                ← JS includes, closes layout wrappers
├── config/
│   ├── App.php                   ← Base path config + App::url() / App::redirect()
│   └── DB.php                    ← PDO singleton + query helpers (fetchOne, fetchAll)
├── helpers/
│   ├── Auth.php                  ← Login, sessions, CSRF, role guards, audit logging
│   └── GradeCalculator.php       ← Grade formula, GPA scale, standing labels
├── middleware/
│   ├── AdminMiddleware.php        ← Guards all admin pages
│   ├── TeacherMiddleware.php      ← Guards all teacher pages
│   └── StudentMiddleware.php      ← Guards all student pages
├── models/
│   ├── Student.php               ← Student CRUD + enrollment + grade queries
│   ├── Teacher.php               ← Teacher CRUD + subject assignment queries
│   ├── Subject.php               ← Subject CRUD + teacher assignment
│   └── Grade.php                 ← Grade save/update/lock + summary/distribution
├── database/
│   ├── schema.sql                ← Full DB schema + seed data (fresh install)
│   └── migrate.sql               ← ALTER TABLE migrations (existing DB upgrade)
├── uploads/
│   └── avatars/                  ← Profile photos (auto-created on first upload)
├── index.php                     ← Root entry point — routes by role
└── profile.php                   ← Shared profile page (all 3 roles)
```

---

## ⚙️ Setup Instructions

### Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `fileinfo`
- MySQL 8.0+ or MariaDB 10.6+
- Apache (XAMPP recommended for local development)

### Step 1 — Place the project

Copy the project folder into XAMPP's htdocs and name it `gradeapp`:
```
C:\xampp\htdocs\gradeapp\
```

### Step 2 — Configure the base path

Open `config/App.php` and confirm this line matches your folder name:
```php
const BASE_PATH = '/gradeapp';
```

### Step 3 — Create the database

1. Start Apache and MySQL from the XAMPP Control Panel
2. Open http://localhost/phpmyadmin
3. Click **New** → name it `grade_management` → **Create**
4. Select the `grade_management` database in the left panel
5. Click **Import** → choose `database/schema.sql` → **Go**

### Step 4 — Configure database credentials

The `.env` file in the project root controls the connection:
```
DB_HOST=localhost
DB_NAME=grade_management
DB_USER=root
DB_PASSWORD=
```
Edit these if your MySQL setup uses a different username or password.

### Step 5 — Open the app

Visit: **http://localhost/gradeapp/**

You will be redirected to the login page automatically.

---

## 🔑 Login Credentials

All accounts use the password: **`password`**

| Role    | Email                  |
|---------|------------------------|
| Admin   | admin@school.edu       |
| Teacher | ana.reyes@school.edu   |
| Teacher | mark.t@school.edu      |
| Student | maria.s@school.edu     |
| Student | juan.dc@school.edu     |
| Student | ana.lim@school.edu     |

> Change all passwords before any real deployment.

---

## �️ Database Schema Overview

| Table             | Purpose                                              |
|-------------------|------------------------------------------------------|
| `users`           | All accounts (admin, teacher, student) with bcrypt passwords |
| `students`        | Student profiles linked to users                     |
| `teachers`        | Teacher profiles linked to users                     |
| `subjects`        | Course subjects with units and description           |
| `semesters`       | School year / semester records (one active at a time)|
| `enrollments`     | Student ↔ Subject ↔ Semester relationships           |
| `grades`          | Prelim, midterm, prefinal, finals, final grade, GPA  |
| `teacher_subjects`| Teacher ↔ Subject ↔ Semester assignments             |
| `audit_logs`      | Every significant action logged with user + IP       |
| `login_attempts`  | Failed login tracking for brute force protection     |
| `api_tokens`      | Bearer tokens for REST API access                    |

---

## 📐 Grade Formula

```
Final Grade = (Midterm × 0.50) + (Finals × 0.50)

≥ 75           →  Passed
< 75           →  Failed
Finals missing →  Incomplete

GPA Scale (Philippine University):
95–100 → 1.00  |  90–94 → 1.25  |  85–89 → 1.50
80–84  → 1.75  |  75–79 → 2.00  |  70–74 → 2.50
65–69  → 3.00  |  below 65 → 5.00

Academic Standing:
GPA ≤ 1.75  →  Dean's List
GPA ≤ 2.50  →  Good Standing
GPA > 2.50  →  At Risk
```

---

## � Security Features

| Feature           | How it's implemented                                      |
|-------------------|-----------------------------------------------------------|
| SQL Injection     | PDO prepared statements on every single query             |
| XSS               | `htmlspecialchars()` on all user-generated output         |
| CSRF              | Random token in every form, validated on every POST       |
| Password storage  | `password_hash(BCRYPT)` — never stored as plain text      |
| Session fixation  | `session_regenerate_id(true)` called after every login    |
| Brute force       | 5 failed attempts triggers a 15-minute lockout            |
| Role enforcement  | Middleware on every page — wrong role = immediate redirect |
| File uploads      | MIME type validation, 2MB limit, random filename          |

---

## 🔗 REST API

**Base endpoint:** `GET /api/grades.php`

**Authentication:** Bearer token in the `Authorization` header

```bash
# Get grades for a student
curl -H "Authorization: Bearer demo-api-token-12345" \
  http://localhost/gradeapp/api/grades.php?student_id=1

# Get grades for a subject
curl -H "Authorization: Bearer demo-api-token-12345" \
  http://localhost/gradeapp/api/grades.php?subject_id=1
```

The full API with POST/PUT endpoints is handled by `controllers/ApiController.php`.

---

## 👥 Team Task Breakdown

| Member   | Files Owned                                                                                                          |
|----------|----------------------------------------------------------------------------------------------------------------------|
| **Mak**  | `auth/login.php`, `auth/logout.php`, `helpers/Auth.php`, `middleware/` (all 3), `index.php`, `config/App.php`       |
| **Meaz** | `database/schema.sql`, `database/migrate.sql`, `config/DB.php`, `models/` (all 4), `shared/` (header, sidebar, footer) |
| **Mahi** | `admin/dashboard.php`, `admin/manage_students.php`, `admin/user_management.php`, `admin/manage_subjects.php`, `assets/css/auth.css` |
| **Lina** | `admin/manage_enrollment.php`, `admin/manage_semesters.php`, `admin/view_grades.php`, `admin/audit_log.php`, `assets/css/global.css` |
| **Mercy**| `teacher/dashboard.php`, `teacher/manage_grades.php`, `teacher/my_subjects.php`, `teacher/class_list.php`, `teacher/reports.php`, `assets/js/app.js` |
| **Nardi**| `student/dashboard.php`, `student/grades.php`, `profile.php`, `helpers/GradeCalculator.php`, `api/grades.php`, `controllers/ApiController.php`, `assets/js/auth.js` |

---

## 🚀 Running for Presentation

1. Start XAMPP → Apache + MySQL
2. Open http://localhost/gradeapp/
3. Log in as admin with `admin@school.edu` / `password`
4. Demo flow: Admin creates a semester → enrolls students → teacher enters grades → student views grades

---

## 📝 Notes

- The `uploads/avatars/` folder is created automatically on first avatar upload
- The `.env` file is read by `config/DB.php` at runtime — no external library needed
- `database/migrate.sql` is only needed if upgrading an existing database — fresh installs use `schema.sql` only
