<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Starting Quiz Security Database Migration...\n";

    $sql = "
    CREATE TABLE IF NOT EXISTS quiz_security_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        quiz_id INT NOT NULL,
        warning_count INT DEFAULT 0,
        focus_loss_count INT DEFAULT 0,
        fullscreen_exit_count INT DEFAULT 0,
        auto_submitted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "Successfully created quiz_security_logs table.\n";
    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
