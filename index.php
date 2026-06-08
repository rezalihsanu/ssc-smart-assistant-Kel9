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

    case 'export_csv':
        require_once 'controllers/DocumentController.php';
        require_once 'config/database.php';
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['admin_id'])) {
            header("Location: index.php?route=login");
            exit;
        }

        // Ambil filter yang sama seperti dashboard
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';
        $status   = $_GET['status']    ?? '';
        $keyword  = $_GET['keyword']   ?? '';

        $where  = [];
        $params = [];

        if ($dateFrom) { $where[] = 'DATE(created_at) >= ?'; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = 'DATE(created_at) <= ?'; $params[] = $dateTo;   }
        if ($status && in_array($status, ['success','error','out_of_topic'])) {
            $where[] = 'status = ?'; $params[] = $status;
        }
        if ($keyword) {
            $where[] = '(user_query LIKE ? OR bot_response LIKE ?)';
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }

        $sql = "SELECT id, session_id, user_query, bot_response, status, response_time_ms, created_at
                FROM chat_logs"
             . ($where ? " WHERE " . implode(' AND ', $where) : "")
             . " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Output Excel
        require_once 'vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Log Chat');

        // ── Header kolom ──────────────────────────────────────────────────────
        $headers = ['ID', 'Session ID', 'Pertanyaan Mahasiswa', 'Jawaban Bot', 'Status', 'Response Time (ms)', 'Waktu'];
        foreach ($headers as $col => $label) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $label);
        }

        // Style header: background biru, teks putih, bold, center
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Isi data ──────────────────────────────────────────────────────────
        $row = 2;
        foreach ($logs as $log) {
            $sheet->setCellValue('A' . $row, $log['id']);
            $sheet->setCellValue('B' . $row, $log['session_id']);
            $sheet->setCellValue('C' . $row, $log['user_query']);
            $sheet->setCellValue('D' . $row, $log['bot_response']);
            $sheet->setCellValue('E' . $row, $log['status']);
            $sheet->setCellValue('F' . $row, $log['response_time_ms']);
            $sheet->setCellValue('G' . $row, $log['created_at']);

            // Warna baris selang-seling supaya mudah dibaca
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'EFF6FF'],
                    ],
                ]);
            }

            // Wrap text kolom Pertanyaan dan Jawaban
            $sheet->getStyle("C{$row}:D{$row}")->getAlignment()->setWrapText(true);

            $row++;
        }

        // ── Border seluruh tabel ──────────────────────────────────────────────
        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle("A1:G{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color'       => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ]);
        }

        // ── Auto-width tiap kolom ─────────────────────────────────────────────
        $widths = ['A' => 6, 'B' => 32, 'C' => 40, 'D' => 55, 'E' => 14, 'F' => 18, 'G' => 20];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ── Freeze baris header ───────────────────────────────────────────────
        $sheet->freezePane('A2');

        // ── Download ──────────────────────────────────────────────────────────
        $filename = 'log-chat-' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    // ── 404 ─────────────────────────────────────────────────────────────────
    default:
        http_response_code(404);
        echo "<h1>404 - Halaman Tidak Ditemukan</h1><a href='index.php'>Kembali ke Beranda</a>";
        break;
}
