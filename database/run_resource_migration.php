<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Starting Rewards Resource Columns Database Migration...\n";

    // Add resource columns if they do not exist
    $sql = "
    ALTER TABLE rewards 
    ADD COLUMN file_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN file_size INT DEFAULT NULL,
    ADD COLUMN resource_type VARCHAR(50) DEFAULT 'pdf';
    ";

    $pdo->exec($sql);
    echo "Successfully updated rewards table columns.\n";
    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    // If columns already exist, this is fine
    echo "Migration note or failure: " . $e->getMessage() . "\n";
}
?>
