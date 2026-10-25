<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Starting Quiz Security Exit Attempts Migration...\n";

    $check_col = $pdo->query("SHOW COLUMNS FROM quiz_security_logs LIKE 'exit_attempts_count'")->fetch();
    if (!$check_col) {
        $sql = "ALTER TABLE quiz_security_logs ADD COLUMN exit_attempts_count INT DEFAULT 0 AFTER fullscreen_exit_count";
        $pdo->exec($sql);
        echo "Successfully added exit_attempts_count to quiz_security_logs table.\n";
    } else {
        echo "Column exit_attempts_count already exists in quiz_security_logs table.\n";
    }

    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
