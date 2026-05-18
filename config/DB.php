<?php
/* ============================================================
   GradeMS — Database Connection (PDO Singleton)
   File: config/DB.php
   ============================================================ */

class DB {
    private static ?PDO $instance = null;

    // Prevent direct instantiation
    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host   = $_ENV['DB_HOST']     ?? 'localhost';
            $dbname = $_ENV['DB_NAME']     ?? 'grade_management';
            $user   = $_ENV['DB_USER']     ?? 'root';
            $pass   = $_ENV['DB_PASSWORD'] ?? '';
            $charset= 'utf8mb4';

            
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Never expose raw DB errors in production
                error_log('Database connection failed: ' . $e->getMessage());
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed.']));
            }
        }
        return self::$instance;
    }

    /**
     * Shorthand: run a prepared query and return the statement.
     * Usage: DB::query('SELECT * FROM users WHERE id = ?', [$id])
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row.
     */
    public static function fetchOne(string $sql, array $params = []): ?array {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    /**
     * Fetch all rows.
     */
    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Run INSERT/UPDATE/DELETE and return affected row count.
     */
    public static function execute(string $sql, array $params = []): int {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Return last inserted ID.
     */
    public static function lastInsertId(): string {
        return self::getInstance()->lastInsertId();
    }
}
