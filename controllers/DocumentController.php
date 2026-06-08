<?php
// controllers/DocumentController.php
// Mengelola CRUD dokumen & ekstraksi teks untuk RAG
// Update: toggle is_active, log aktivitas staf

require_once __DIR__ . '/../config/database.php';

class DocumentController
{
    private $uploadDir;
    private $allowedTypes  = ['pdf', 'txt', 'docx'];
    private $maxSizeBytes  = 10 * 1024 * 1024; // 10 MB

    public function __construct()
    {
        $this->uploadDir = __DIR__ . '/../uploads/documents/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    // ─── Upload & Proses Dokumen ─────────────────────────────────────────────
    public function upload()
    {
        $this->requireLogin();
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['tak_document'])) {
            $this->redirect('dashboard', 'error=no_file');
        }

        $file = $_FILES['tak_document'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->redirect('dashboard', 'error=upload_failed');
        }
        if ($file['size'] > $this->maxSizeBytes) {
            $this->redirect('dashboard', 'error=too_large');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedTypes)) {
            $this->redirect('dashboard', 'error=invalid_type');
        }

        $storedName = uniqid('doc_', true) . '.' . $ext;
        $destPath   = $this->uploadDir . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->redirect('dashboard', 'error=move_failed');
        }

        $extractedText = $this->extractText($destPath, $ext);
        $summary = $this->generateSummary($extractedText);

        $stmt = $pdo->prepare(
            "INSERT INTO documents
                (original_filename, stored_filename, file_path, file_type, file_size_kb, extracted_text, summary, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $file['name'],
            $storedName,
            'uploads/documents/' . $storedName,
            $ext,
            round($file['size'] / 1024),
            $extractedText,
            $summary,
            $_SESSION['admin_id'],
        ]);

        $docId = (int) $pdo->lastInsertId();

        // ── Log aktivitas staf ──
        $this->logActivity('upload', 'document', $docId, $file['name']);

        $this->redirect('dashboard', 'success=uploaded');
    }

    // ─── Hapus Dokumen ───────────────────────────────────────────────────────
    public function delete()
    {
        $this->requireLogin();
        global $pdo;

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('dashboard', 'error=invalid_id');
        }

        $stmt = $pdo->prepare("SELECT stored_filename, original_filename FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($doc) {
            $filePath = $this->uploadDir . $doc['stored_filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);

            // ── Log aktivitas staf ──
            $this->logActivity('delete', 'document', $id, $doc['original_filename']);
        }

        $this->redirect('dashboard', 'success=deleted');
    }

    // ─── Toggle Aktif / Nonaktif Dokumen ─────────────────────────────────────
    public function toggleStatus()
    {
        $this->requireLogin();
        global $pdo;

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('dashboard', 'error=invalid_id');
        }

        $stmt = $pdo->prepare("SELECT is_active, original_filename FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            $this->redirect('dashboard', 'error=invalid_id');
        }

        $newStatus = $doc['is_active'] ? 0 : 1;
        $pdo->prepare("UPDATE documents SET is_active = ? WHERE id = ?")
            ->execute([$newStatus, $id]);

        // ── Log aktivitas staf ──
        $action = $newStatus ? 'toggle_active' : 'toggle_inactive';
        $this->logActivity($action, 'document', $id, $doc['original_filename']);

        $msg = $newStatus ? 'success=activated' : 'success=deactivated';
        $this->redirect('dashboard', $msg);
    }

    // ─── Ambil Semua Dokumen (aktif + nonaktif, untuk dashboard) ─────────────
    public static function getAll()
    {
        global $pdo;
        $stmt = $pdo->query(
            "SELECT d.id, d.original_filename, d.file_type, d.file_size_kb,
                    d.is_active, d.uploaded_at, d.summary,
                    u.email AS uploader_email
             FROM documents d
             LEFT JOIN admin_users u ON u.id = d.uploaded_by
             ORDER BY d.uploaded_at DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Ambil Teks Semua Dokumen Aktif (untuk RAG context) ──────────────────
    public static function getAllExtractedText()
    {
        global $pdo;
        $stmt = $pdo->query(
            "SELECT id, original_filename, extracted_text
             FROM documents
             WHERE is_active = 1 AND extracted_text IS NOT NULL
             ORDER BY uploaded_at DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Ambil Log Aktivitas Staf (untuk dashboard) ───────────────────────────
    public static function getStaffLogs(int $limit = 50): array
    {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT id, staff_email, action, target_type, target_name,
                    ip_address, created_at
             FROM staff_activity_logs
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Buat Ringkasan Otomatis via Groq ────────────────────────────────────
    private function generateSummary(string $text): string
    {
        if (empty(trim($text))) return '';

        // Potong teks maksimal 3000 karakter supaya tidak buang token
        $potongan = mb_substr($text, 0, 3000);

        $payload = json_encode([
            'model'       => 'llama-3.3-70b-versatile',
            'max_tokens'  => 150,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Kamu adalah asisten yang meringkas dokumen peraturan kampus. Buat ringkasan singkat 2-3 kalimat dalam Bahasa Indonesia. Langsung ke inti, tanpa basa-basi.'
                ],
                [
                    'role'    => 'user',
                    'content' => "Ringkas dokumen ini:\n\n$potongan"
                ]
            ]
        ]);

        $apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : getenv('GROQ_API_KEY');

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 20,
        ]);

        $response = curl_exec($ch);

        if (!$response) return '';

        $data = json_decode($response, true);
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    // ─── Ekstrak Teks dari File ───────────────────────────────────────────────
    private function extractText(string $path, string $ext): string
    {
        switch ($ext) {
            case 'txt':
                return file_get_contents($path) ?: '';

            case 'pdf':
                $output = [];
                exec("pdftotext " . escapeshellarg($path) . " - 2>/dev/null", $output, $code);
                if ($code === 0 && !empty($output)) {
                    return implode("\n", $output);
                }
                return $this->extractPdfFallback($path);

            case 'docx':
                return $this->extractDocx($path);

            default:
                return '';
        }
    }

    private function extractPdfFallback(string $path): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($path);
            $text   = $pdf->getText();
            return $text ?: '';
        } catch (\Exception $e) {
            error_log("PdfParser gagal: " . $e->getMessage());
            return '';
        }
    }

    private function extractDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return '';
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) return '';
        return strip_tags(str_replace('</w:p>', "\n", $xml));
    }

    // ─── Log Aktivitas Staf (helper internal) ────────────────────────────────
    private function logActivity(string $action, string $targetType, int $targetId, string $targetName): void
    {
        global $pdo;
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $pdo->prepare(
                "INSERT INTO staff_activity_logs
                    (staff_id, staff_email, action, target_type, target_id, target_name, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_SESSION['admin_id'] ?? null,
                $_SESSION['email']    ?? 'unknown',
                $action,
                $targetType,
                $targetId,
                $targetName,
                $ip,
            ]);
        } catch (PDOException $e) {
            error_log("Gagal menyimpan staff log: " . $e->getMessage());
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────
    private function requireLogin()
    {
        if (!isset($_SESSION['admin_id'])) {
            header('Location: index.php?route=login');
            exit;
        }
    }

    private function redirect(string $route, string $params = '')
    {
        $url = "index.php?route={$route}";
        if ($params) $url .= "&{$params}";
        header("Location: {$url}");
        exit;
    }
}
