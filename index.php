<?php
session_start();
require_once 'helpers/Auth.php';

if (!Auth::isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

switch (Auth::getRole()) {
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