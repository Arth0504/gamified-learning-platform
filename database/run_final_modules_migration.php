<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Starting Final Modules Database Migration...\n";

    // 1. Add explanation column to questions table if it doesn't exist
    $check_explain = $pdo->query("SHOW COLUMNS FROM questions LIKE 'explanation'")->fetch();
    if (!$check_explain) {
        $pdo->exec("ALTER TABLE questions ADD COLUMN explanation TEXT DEFAULT NULL");
        echo "Successfully added explanation to questions table.\n";
    } else {
        echo "Column explanation already exists in questions table.\n";
    }

    // 2. Create doubts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS doubts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            lesson_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            question TEXT NOT NULL,
            screenshot_path VARCHAR(255) DEFAULT NULL,
            status ENUM('Pending', 'Answered', 'Resolved') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Successfully verified doubts table.\n";

    // 3. Create doubt_replies table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS doubt_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doubt_id INT NOT NULL,
            user_id INT NOT NULL,
            reply TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doubt_id) REFERENCES doubts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Successfully verified doubt_replies table.\n";

    // 4. Create course_feedback table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            rating INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student_course_feedback (student_id, course_id),
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Successfully verified course_feedback table.\n";

    echo "Final Modules Migration completed successfully!\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
