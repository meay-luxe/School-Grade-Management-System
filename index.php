<?php
// ============================================================
// index.php — Root Entry Point
// ============================================================

require_once 'helpers/Auth.php';
require_once 'config/App.php';

Auth::startSession();

if (!Auth::isLoggedIn()) {
    App::redirect('/auth/login.php');
}

switch (Auth::getRole()) {
    case 'admin':
        App::redirect('/admin/dashboard.php');
    case 'teacher':
        App::redirect('/teacher/dashboard.php');
    case 'student':
        App::redirect('/student/dashboard.php');
    default:
        Auth::logout();
}
