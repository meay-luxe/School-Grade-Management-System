<?php
require_once __DIR__ . '/../helpers/Auth.php';

class AdminMiddleware {
    public static function handle(): void {
        if (!Auth::isLoggedIn()) {
            header('Location: ../auth/login.php');
            exit();
        }
        if (!Auth::isAdmin()) {
            http_response_code(403);
            include __DIR__ . '/../shared/header.php';
            echo '
            <div class="page-content">
              <div class="empty-state">
                <div class="empty-state-icon">🚫</div>
                <h3>Access Denied</h3>
                <p>You do not have permission to view this page.</p>
                <a href="../index.php" class="btn btn-primary mt-4">
                  Go Back
                </a>
              </div>
            </div>';
            include __DIR__ . '/../shared/footer.php';
            exit();
        }
    }
}