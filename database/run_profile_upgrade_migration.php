<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Starting Academic Profile Upgrade Migration...\n";

    // Add new profile fields to students table
    $sql = "ALTER TABLE students 
            ADD COLUMN school_name VARCHAR(150) DEFAULT NULL,
            ADD COLUMN university_board VARCHAR(150) DEFAULT NULL,
            ADD COLUMN academic_year VARCHAR(20) DEFAULT NULL,
            ADD COLUMN education_level VARCHAR(50) DEFAULT NULL,
            ADD COLUMN preferred_language VARCHAR(50) DEFAULT NULL,
            ADD COLUMN learning_goal VARCHAR(100) DEFAULT NULL,
            ADD COLUMN skill_level VARCHAR(50) DEFAULT NULL,
            ADD COLUMN interests VARCHAR(255) DEFAULT NULL";
    
    $pdo->exec($sql);
    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Columns already exist. Migration skipped.\n";
    } else {
        die("Migration failed: " . $e->getMessage() . "\n");
    }
}
?>
