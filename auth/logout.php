<?php
/* ============================================================
   GradeMS — Logout
   File: auth/logout.php
   ============================================================ */

require_once __DIR__ . '/../helpers/Auth.php';
Auth::startSession();
Auth::logout(); // destroys session and redirects to login
