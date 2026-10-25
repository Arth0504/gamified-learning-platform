<?php
require_once __DIR__ . '/../config/db.php';

try {
    $sql = "ALTER TABLE students 
            ADD COLUMN dob DATE DEFAULT NULL,
            ADD COLUMN gender VARCHAR(20) DEFAULT NULL,
            ADD COLUMN mobile_no VARCHAR(20) DEFAULT NULL,
            ADD COLUMN address TEXT DEFAULT NULL,
            ADD COLUMN stream VARCHAR(100) DEFAULT NULL,
            ADD COLUMN class VARCHAR(50) DEFAULT NULL,
            ADD COLUMN semester VARCHAR(20) DEFAULT NULL,
            ADD COLUMN enrollment_no VARCHAR(50) DEFAULT NULL,
            ADD COLUMN roll_no VARCHAR(20) DEFAULT NULL";
    
    $pdo->exec($sql);
    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Columns already exist. Migration skipped.\n";
    } else {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}
?>
