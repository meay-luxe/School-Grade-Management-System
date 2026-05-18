<?php
// ============================================================
// INDEX.PHP — Root Entry Point
// School Grade Management System
// ============================================================

session_start();
require_once 'helpers/Auth.php';

// ── Check what methods your Auth.php actually has ─────────────
// Common variations — one of these will match yours:

// Option A: if Auth uses isLoggedIn()
if (!Auth::isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}
$role = $_SESSION['role'] ?? '';

// Option B: if Auth uses isAuthenticated()
// if (!Auth::isAuthenticated()) { ... }

// Option C: if Auth checks session directly
// if (!isset($_SESSION['user_id'])) { ... }

switch ($role) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'teacher':
        header('Location: teacher/dashboard.php');
        break;
    case 'student':
        header('Location: student/dashboard.php');
        break;
    default:
        header('Location: auth/login.php');
        break;
}
exit();