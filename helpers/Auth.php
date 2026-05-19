<?php
/* ============================================================
   GradeMS — Auth Helper
   File: helpers/Auth.php
   ============================================================ */

require_once __DIR__ . '/../config/DB.php';
require_once __DIR__ . '/../config/App.php';

class Auth {

    // ── Start Session Safely ──────────────────────────────────
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    // ── Login ─────────────────────────────────────────────────
    public static function login(string $email, string $password): bool {
        self::startSession();

        $user = DB::fetchOne(
            'SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1',
            [strtolower(trim($email))]
        );

        if (!$user) {
            self::logFailedAttempt($email);
            return false;
        }

        if (self::isLockedOut($email)) {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            self::logFailedAttempt($email);
            return false;
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['csrf']    = self::generateCsrf();

        // Clear failed login attempts
        DB::execute(
            'DELETE FROM login_attempts WHERE email = ?',
            [strtolower($email)]
        );

        self::logAction('Login', 'User logged in');

        return true;
    }

    // ── Logout ────────────────────────────────────────────────
    public static function logout(): void {
        self::startSession();
        self::logAction('Logout', 'User logged out');
        $_SESSION = [];
        session_destroy();
        App::redirect('/auth/login.php');
    }

    // ── Check If Logged In ────────────────────────────────────
    public static function isLoggedIn(): bool {
        self::startSession();
        return !empty($_SESSION['user_id']);
    }

    // ── Get Role ──────────────────────────────────────────────
    public static function getRole(): string {
        self::startSession();
        return $_SESSION['role'] ?? '';
    }

    // ── Get User ID ───────────────────────────────────────────
    public static function getUserId(): int {
        self::startSession();
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    // ── Role Checks ───────────────────────────────────────────
    public static function isAdmin(): bool {
        return self::getRole() === 'admin';
    }

    public static function isTeacher(): bool {
        return self::getRole() === 'teacher';
    }

    public static function isStudent(): bool {
        return self::getRole() === 'student';
    }

    // ── Require Role ──────────────────────────────────────────
    public static function requireRole($roles): void {
        self::startSession();

        if (empty($_SESSION['user_id'])) {
            App::redirect('/auth/login.php');
        }

        $allowed = (array) $roles;
        if (!in_array($_SESSION['role'], $allowed, true)) {
            http_response_code(403);
            $dashboards = [
                'admin'   => '/admin/dashboard.php',
                'teacher' => '/teacher/dashboard.php',
                'student' => '/student/dashboard.php',
            ];
            App::redirect($dashboards[$_SESSION['role']] ?? '/auth/login.php');
        }
    }

    // ── Current User From DB ──────────────────────────────────
    public static function currentUser(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        return DB::fetchOne(
            'SELECT id, name, email, role FROM users WHERE id = ?',
            [$_SESSION['user_id']]
        );
    }

    // ── Hash Password ─────────────────────────────────────────
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    // ── Verify Password ───────────────────────────────────────
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    // ── Generate CSRF Token ───────────────────────────────────
    public static function generateCsrf(): string {
        self::startSession();
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf'] = $token;
        return $token;
    }

    // ── Validate CSRF Token ───────────────────────────────────
    public static function validateCsrf(string $token): bool {
        self::startSession();
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    // ── Output Hidden CSRF Field ──────────────────────────────
    public static function csrfField(): string {
        $token = $_SESSION['csrf'] ?? self::generateCsrf();
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    // ── Log Action ────────────────────────────────────────────
    public static function logAction(string $action, string $details = ''): void {
        self::startSession();
        if (empty($_SESSION['user_id'])) return;
        try {
            DB::execute(
                'INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
                 VALUES (?, ?, ?, ?, NOW())',
                [
                    $_SESSION['user_id'],
                    $action,
                    $details,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]
            );
        } catch (Exception $e) {
            error_log('[Auth::logAction] ' . $e->getMessage());
        }
    }

    // ── Private Helpers ───────────────────────────────────────

    private static function logFailedAttempt(string $email): void {
        try {
            DB::execute(
                'INSERT INTO login_attempts (email, attempt_count, attempted_at)
                 VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                   attempt_count = attempt_count + 1,
                   attempted_at  = NOW()',
                [strtolower($email)]
            );
        } catch (Exception $e) {
            error_log('[Auth::logFailedAttempt] ' . $e->getMessage());
        }
    }

    private static function isLockedOut(string $email): bool {
        try {
            $row = DB::fetchOne(
                'SELECT attempt_count FROM login_attempts
                 WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
                [strtolower($email)]
            );
            return $row && $row['attempt_count'] >= 5;
        } catch (Exception $e) {
            return false;
        }
    }
}
