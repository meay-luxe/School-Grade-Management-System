# 🎓 GradeMS — Student Grade Management System

A full-stack PHP + MySQL grade management system with Glassmorphism UI.  
Built for a 6-person team academic project.

---

## 📁 Project Structure

```
gradeapp/
├── assets/
│   ├── css/
│   │   ├── global.css        ← Glassmorphism design system
│   │   └── auth.css          ← Login page styles
│   └── js/
│       ├── app.js            ← Main app JS (navigation, rendering, UI)
│       └── auth.js           ← Login/logout/role switching
├── auth/
│   ├── login.php             ← Login page
│   └── logout.php            ← Destroys session, redirects
├── admin/
│   ├── dashboard.php         ← Admin home with stats + charts
│   ├── user_management.php   ← Create/edit/deactivate users
│   ├── manage_students.php   ← Student records + CSV import
│   ├── manage_subjects.php   ← Subjects + teacher assignment
│   ├── manage_semesters.php  ← School year/semester management
│   ├── manage_enrollment.php ← Enroll students into subjects
│   ├── view_grades.php       ← View/lock all grades
│   ├── reports.php           ← Analytics + charts
│   └── audit_log.php         ← Full system audit trail
├── teacher/
│   ├── dashboard.php         ← Teacher home
│   ├── my_subjects.php       ← Subjects assigned to me
│   ├── class_list.php        ← Students in my subject
│   ├── manage_grades.php     ← Grade entry with live calculation
│   └── reports.php           ← My subject analytics
├── student/
│   ├── dashboard.php         ← Student home + GPA ring
│   └── grades.php            ← Full grade history by semester
├── api/
│   └── grades.php            ← JSON REST API (Bearer token auth)
├── shared/
│   ├── header.php            ← HTML head + app wrapper open
│   ├── sidebar.php           ← Role-aware navigation
│   └── footer.php            ← JS includes + wrapper close
├── config/
│   └── DB.php                ← PDO singleton database connection
├── helpers/
│   ├── Auth.php              ← Login, sessions, CSRF, role guards
│   └── GradeCalculator.php  ← Grade formula, GPA, standing
├── models/
│   ├── Student.php           ← Student OOP model
│   ├── Teacher.php           ← Teacher OOP model
│   ├── Subject.php           ← Subject OOP model
│   └── Grade.php             ← Grade OOP model
├── controllers/
│   └── ApiController.php     ← API routing logic
├── database/
│   └── schema.sql            ← Full DB schema + seed data
├── uploads/
│   └── avatars/              ← Profile photos (auto-created)
└── index.php                  root entry point
└── profile.php               ← Shared profile page (all roles)
```

---

## ⚙️ Setup Instructions

### 1. Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `gd`, `fileinfo`
- MySQL 8.0+ or MariaDB 10.6+
- Apache or Nginx (XAMPP/WAMP/LAMP all work)

### 2. Install XAMPP (Windows/Mac)

1. Download from https://www.apachefriends.org
2. Start **Apache** and **MySQL** from the XAMPP Control Panel
3. Copy this project folder into `C:/xampp/htdocs/gradeapp/`

### 3. Create the Database

1. Open http://localhost/phpmyadmin
2. Click **New** → name it `grade_management` → **Create**
3. Click the **SQL** tab → paste the contents of `database/schema.sql` → **Go**

### 4. Configure Database Credentials

Create a `.env` file in the project root (copy from `.env.example`):

```
DB_HOST=localhost
DB_NAME=grade_management
DB_USER=root
DB_PASSWORD=
```

Or edit `config/DB.php` directly (not recommended for production).

### 5. Set File Permissions

```bash
chmod 755 uploads/
chmod 755 uploads/avatars/
```

### 6. Open the App

Visit: http://localhost/gradeapp/auth/login.php

---

## 🔑 Default Login Credentials

| Role    | Email                | Password |
| ------- | -------------------- | -------- |
| Admin   | admin@school.edu     | password |
| Teacher | ana.reyes@school.edu | password |
| Student | maria.s@school.edu   | password |

> ⚠️ Change all passwords before going live.

---

## 🔗 JSON API

**Endpoint:** `GET /api/grades.php`

**Auth:** `Authorization: Bearer demo-api-token-12345`

**Examples:**

```
GET /api/grades.php?student_id=1
GET /api/grades.php?subject_id=1
```

Test with Postman or curl:

```bash
curl -H "Authorization: Bearer demo-api-token-12345" \
  http://localhost/gradeapp/api/grades.php?student_id=1
```

---

## 🔒 Security Features Implemented

| Feature          | Implementation                                       |
| ---------------- | ---------------------------------------------------- |
| SQL Injection    | PDO prepared statements on every query               |
| XSS              | `htmlspecialchars()` on all output                   |
| CSRF             | Token in every form, validated on every POST         |
| Passwords        | `password_hash(BCRYPT)` + `password_verify()`        |
| Session Fixation | `session_regenerate_id(true)` after login            |
| File Uploads     | MIME validation with `finfo`, GD resize, random name |
| Brute Force      | Login attempt counter, 15-min lockout after 5 fails  |

---

## 📐 Grade Formula

```
Final Grade = (Midterm × 0.50) + (Finals × 0.50)

≥ 75          →  Passed
< 75          →  Failed
Finals missing →  Incomplete

GPA Scale (Philippine):
95-100 → 1.00 | 90-94 → 1.25 | 85-89 → 1.50
80-84  → 1.75 | 75-79 → 2.00 | 70-74 → 2.50
65-69  → 3.00 | < 65  → 5.00

Standing:
GPA ≤ 1.75  →  Dean's List
GPA ≤ 2.50  →  Good Standing
GPA > 2.50  →  At Risk
```

---

## 👥 Team Task Breakdown

| Person | Owns                                                                                                                                               |
| ------ | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| **P1** | `database/schema.sql`, `config/DB.php`, `helpers/GradeCalculator.php`, all models, `shared/` partials, `assets/css/global.css`                     |
| **P2** | `auth/login.php`, `auth/logout.php`, `helpers/Auth.php`, `admin/user_management.php`                                                               |
| **P3** | `admin/manage_students.php` (+ CSV import), `admin/manage_subjects.php`, `admin/manage_enrollment.php`, `admin/manage_semesters.php`               |
| **P4** | `admin/dashboard.php`, `admin/view_grades.php`, `admin/audit_log.php`, `admin/reports.php`                                                         |
| **P5** | `teacher/dashboard.php`, `teacher/my_subjects.php`, `teacher/class_list.php`, `teacher/manage_grades.php`, `teacher/reports.php`, `api/grades.php` |
| **P6** | `student/dashboard.php`, `student/grades.php`, `profile.php`, photo upload handler, PDF export, `assets/js/app.js`, UI polish                      |

---

## 🚀 Deployment

**Option A — Free live host:**

1. Sign up at https://infinityfree.net
2. Upload files via their File Manager
3. Import `schema.sql` via their phpMyAdmin

**Option B — Local demo:**
Run on XAMPP and present from your laptop.
