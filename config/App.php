r<?php
/* ============================================================
   GradeMS — Application Config
   File: config/App.php
   ============================================================ */

class App {

    /**
     * The subfolder your project lives in under htdocs.
     * Examples:
     *   ''          → http://localhost/          (project IS htdocs root)
     *   '/gradeapp' → http://localhost/gradeapp/
     *   '/GradeMS'  → http://localhost/GradeMS/
     *
     * No trailing slash.
     */
    const BASE_PATH = '/gradeapp';

    /**
     * Return a URL relative to the app base.
     * Usage: App::url('/admin/dashboard.php')
     *        App::url('/assets/css/global.css')
     */
    public static function url(string $path = ''): string {
        return self::BASE_PATH . '/' . ltrim($path, '/');
    }

    /**
     * Redirect to a path within the app and exit.
     * Usage: App::redirect('/auth/login.php');
     */
    public static function redirect(string $path): void {
        header('Location: ' . self::url($path));
        exit();
    }
}
