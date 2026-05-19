<?php
/* ============================================================
   GradeMS — Auth Helper
   File: helpers/Auth.php
   ============================================================ */

require_once __DIR__ . '/../config/DB.php';
require_once __DIR__ . '/../config/App.php';

class Auth {

    /**
     * Start secure session (call once at top of every page).
     */
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

    /**
     * Attempt login. Returns true on success, false on failure.
     */
    public static function login(string $email, string $password): bool {
        self::startSession();

        // SECURITY: prepared statement — no SQL injection possible
        $user = DB::fetchOne(
            'SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1',
            [strtolower(trim($email))]
        );

        if (!$user) {
            self::logFailedAttempt($email);
            return false;
        }

        // SECURITY: brute force — check failed attempt count
        if (self::isLockedOut($email)) {
            return false;
        }

        // SECURITY: bcrypt verify — never compare plain text
        if (!password_verify($password, $user['password'])) {
            self::logFailedAttempt($email);
            return false;
        }

        // Successful login
        // SECURITY: regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['csrf']    = self::generateCsrf();

        // Clear any failed login attempts
        DB::execute(
            'DELETE FROM login_attempts WHERE email = ?',
            [strtolower($email)]
        );

        // Log the action
        self::logAction('Login', 'User logged in');

        return true;
    }

    /**
     * Log out current user.
     */
    public static function logout(): void {
        self::startSession();
        self::logAction('Logout', 'User logged out');
        $_SESSION = [];
        session_destroy();
        App::redirect('/auth/login.php');
        exit;
    }

    /**
     * Require a specific role. Redirects if not authenticated or wrong role.
     * Usage: Auth::requireRole('admin');  or  Auth::requireRole(['admin','teacher']);
     *
     * @param string|array $roles
     */
    public static function requireRole($roles): void {
        self::startSession();

        if (empty($_SESSION['user_id'])) {
            App::redirect('/auth/login.php');
            exit;
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
            exit;
        }
    }

    /**
     * Check if a user is currently logged in (session exists).
     */
    public static function isLoggedIn(): bool {
        self::startSession();
        return !empty($_SESSION['user_id']);
    }

    /**
     * Return the current user's role from the session.
     */
    public static function getRole(): string {
        self::startSession();
        return $_SESSION['role'] ?? '';
    }

    /**
     * Return the current user's ID from the session.
     */
    public static function getUserId(): int {
        self::startSession();
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    /**
     * Return true if the current user is an admin.
     */
    public static function isAdmin(): bool {
        return self::getRole() === 'admin';
    }

    /**
     * Return true if the current user is a teacher.
     */
    public static function isTeacher(): bool {
        return self::getRole() === 'teacher';
    }

    /**
     * Return true if the current user is a student.
     */
    public static function isStudent(): bool {
        return self::getRole() === 'student';
    }

    /**
     * Return current logged-in user data from DB.
     */
    public static function currentUser(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        return DB::fetchOne(
            'SELECT id, name, email, role, photo_path FROM users WHERE id = ?',
            [$_SESSION['user_id']]
        );
    }

    /**
     * Hash a password (BCRYPT).
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify a plain password against a stored hash.
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Generate a CSRF token and store it in the session.
     */
    public static function generateCsrf(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf'] = $token;
        return $token;
    }

    /**
     * Validate a submitted CSRF token against the session token.
     * Call this at the top of every POST handler.
     */
    public static function validateCsrf(string $token): bool {
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /**
     * Output a hidden CSRF input field for use in HTML forms.
     */
    public static function csrfField(): string {
        $token = $_SESSION['csrf'] ?? self::generateCsrf();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /* ── private helpers ── */

    private static function logFailedAttempt(string $email): void {
        DB::execute(
            'INSERT INTO login_attempts (email, attempted_at) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, attempted_at = NOW()',
            [strtolower($email)]
        );
    }

    private static function isLockedOut(string $email): bool {
        $row = DB::fetchOne(
            'SELECT attempt_count FROM login_attempts
             WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            [strtolower($email)]
        );
        return $row && $row['attempt_count'] >= 5;
    }

    public static function logAction(string $action, string $details = ''): void {
        if (empty($_SESSION['user_id'])) return;
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
    }
}
