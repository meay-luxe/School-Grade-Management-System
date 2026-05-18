<?php
// ============================================================
// INDEX.PHP — Root Entry Point
// School Grade Management System
// ============================================================

require_once 'helpers/Auth.php';

// If not logged in → go to login
if (!Auth::check()) {
    header('Location: auth/login.php');
    exit();
}

// Redirect based on role
$role = Auth::role();

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
        Auth::logout();
        header('Location: auth/login.php');
        break;
}
exit();