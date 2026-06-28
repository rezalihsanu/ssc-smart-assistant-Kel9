<?php
require_once __DIR__ . '/../vendor/autoload.php';
// config/database.php
// Konfigurasi koneksi PDO ke MySQL + Konstanta Aplikasi

// ── Database ──────────────────────────────────────────────────────────────────
$host     = getenv('DB_HOST')     ?: 'localhost';
$dbname   = getenv('DB_NAME')     ?: 'ssc_smart_assistant';
$username = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

// ── Groq API ──────────────────────────────────────────────────────────────────
// Dapetin API key GRATIS di: https://console.groq.com → API Keys → Create API Key
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: ''); 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Jangan expose error detail di production!
    error_log("DB Connection Error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Koneksi database gagal.']));
}
