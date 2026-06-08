<?php
// database/reset_db.php
// Script to reset database and load schema from init.sql

require_once __DIR__ . '/../config/database.php';

try {
    echo "Resetting database...\n";
    
    // Disable foreign key checks to allow dropping tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Drop existing tables
    $tables = ['staff_activity_logs', 'system_settings', 'chat_logs', 'documents', 'admin_users'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
        echo "Dropped table: $table\n";
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    // Read init.sql
    $sqlFile = __DIR__ . '/init.sql';
    if (!file_exists($sqlFile)) {
        die("Error: init.sql not found at $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    // We clean comments and then split by semicolon
    $lines = explode("\n", $sql);
    $cleanSql = '';
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if ($line === '' || str_starts_with($line, '--') || str_starts_with($line, '#')) {
            continue;
        }
        $cleanSql .= $line . "\n";
    }
    
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
    
    echo "Importing schema...\n";
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    
    echo "Database schema successfully imported from init.sql!\n";
    
} catch (PDOException $e) {
    echo "Error resetting database: " . $e->getMessage() . "\n";
}
