<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Running Daily Challenges Table Migration...\n";
    
    // 1. Modify target_type column from ENUM to VARCHAR(50) for flexibility
    $pdo->exec("ALTER TABLE daily_challenges MODIFY COLUMN target_type VARCHAR(50) NOT NULL");
    echo "[PASS] Altered daily_challenges.target_type to VARCHAR(50)\n";
    
    // 2. Ensure auto_generate_challenges setting exists in settings table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'auto_generate_challenges'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_generate_challenges', '1')");
        $ins->execute();
        echo "[PASS] Inserted auto_generate_challenges default setting\n";
    } else {
        echo "[PASS] auto_generate_challenges setting already exists\n";
    }
    
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
?>
