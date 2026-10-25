<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Starting Quiz Database Migration...\n";

    // 1. Alter questions table to expand question_type enum
    $sql1 = "ALTER TABLE questions MODIFY COLUMN question_type ENUM('mcq', 'tf', 'short_answer') DEFAULT 'mcq'";
    $pdo->exec($sql1);
    echo "Successfully updated questions.question_type ENUM.\n";

    // 2. Add short_answer_text column to quiz_answers
    // First check if it already exists to make the migration idempotent
    $check_col = $pdo->query("SHOW COLUMNS FROM quiz_answers LIKE 'short_answer_text'")->fetch();
    if (!$check_col) {
        $sql2 = "ALTER TABLE quiz_answers ADD COLUMN short_answer_text TEXT DEFAULT NULL";
        $pdo->exec($sql2);
        echo "Successfully added short_answer_text to quiz_answers table.\n";
    } else {
        echo "Column short_answer_text already exists in quiz_answers table.\n";
    }

    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
