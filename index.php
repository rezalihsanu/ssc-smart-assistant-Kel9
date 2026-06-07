<?php
// index.php — Front Controller / Router SSC Smart Assistant

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$route = $_GET['route'] ?? 'home';

switch ($route) {

    // ── Halaman Publik ──────────────────────────────────────────────────────
    case 'home':
        require_once 'views/index.php';
        break;

    case 'faq':
        require_once 'views/faq.php';
        break;

    // ── Auth ────────────────────────────────────────────────────────────────
    case 'login':
        require_once 'controllers/AuthController.php';
        (new AuthController())->showLogin();
        break;

    case 'login_process':
        require_once 'controllers/AuthController.php';
        (new AuthController())->processLogin();
        break;

    case 'logout':
        require_once 'controllers/AuthController.php';
        (new AuthController())->logout();
        break;

    // ── Dashboard Admin ─────────────────────────────────────────────────────
    case 'dashboard':
        require_once 'views/admin/dashboard.php';
        break;

    // ── Dokumen CRUD ────────────────────────────────────────────────────────
    case 'upload_doc':
        require_once 'controllers/DocumentController.php';
        (new DocumentController())->upload();
        break;

    case 'delete_doc':
        require_once 'controllers/DocumentController.php';
        (new DocumentController())->delete();
        break;

    // ── Toggle Status Dokumen (aktif / nonaktif) ────────────────────────────
    case 'toggle_doc':
        require_once 'controllers/DocumentController.php';
        (new DocumentController())->toggleStatus();
        break;

    // ── Chatbot API (JSON endpoint) ─────────────────────────────────────────
    case 'chat':
        require_once 'controllers/ChatController.php';
        (new ChatController())->handleChat();
        break;

    case 'chat_logs':
        require_once 'controllers/ChatController.php';
        (new ChatController())->getLogs();
        break;

    // ── 404 ─────────────────────────────────────────────────────────────────
    default:
        http_response_code(404);
        echo "<h1>404 - Halaman Tidak Ditemukan</h1><a href='index.php'>Kembali ke Beranda</a>";
        break;
}
