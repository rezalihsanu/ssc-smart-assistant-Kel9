<?php
// controllers/ChatController.php
// Mengelola chatbot: RAG context building + Groq API + guardrails + logging

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/DocumentController.php';

class ChatController
{
    // ── Groq API — model OpenAI-compatible, super cepat ──────────────────────
    private string $groqApiKey;
    private string $groqModel = 'llama-3.3-70b-versatile'; // model terbaik di Groq, gratis

    public function __construct()
    {
        $this->groqApiKey = defined('GROQ_API_KEY')
            ? GROQ_API_KEY
            : (getenv('GROQ_API_KEY') ?: 'YOUR_GROQ_API_KEY_HERE');
    }

    // ─── Endpoint Utama: Handle POST dari chat widget ─────────────────────────
    public function handleChat()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        // Fallback ke $_POST, lalu $_GET (untuk testing via browser)
        if (empty($body)) $body = $_POST;
        if (empty($body)) $body = $_GET;

        $userQuery  = trim($body['message'] ?? '');
        $sessionId  = $body['session_id'] ?? session_id() ?: uniqid('sess_', true);

        if (empty($userQuery)) {
            echo json_encode(['error' => 'Pesan tidak boleh kosong.']);
            exit;
        }

        $startTime = microtime(true);

        // 1. Bangun RAG context dari dokumen yang di-upload staf
        [$context, $usedDocIds] = $this->buildRagContext($userQuery);

        // 2. Kirim ke Groq dengan system prompt + guardrails
        [$response, $status] = $this->callGroq($userQuery, $context);

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);

        // 3. Simpan ke chat_logs
        $this->saveLog($sessionId, $userQuery, $response, $usedDocIds, $status, $elapsed);

        echo json_encode([
            'reply'      => $response,
            'status'     => $status,
            'session_id' => $sessionId,
        ]);
    }

    // ─── RAG: Ambil teks relevan dari dokumen ────────────────────────────────
    private function buildRagContext(string $query): array
    {
        $docs = DocumentController::getAllExtractedText();

        if (empty($docs)) {
            return ['', []];
        }

        $contextParts = [];
        $usedIds      = [];
        $queryLower   = mb_strtolower($query);

        foreach ($docs as $doc) {
            if (empty($doc['extracted_text'])) continue;

            // Cari chunk yang relevan (simple keyword matching)
            $chunks = $this->getRelevantChunks($doc['extracted_text'], $queryLower);
            if (!empty($chunks)) {
                $contextParts[] = "=== Sumber: {$doc['original_filename']} ===\n" . implode("\n...\n", $chunks);
                $usedIds[]      = $doc['id'];
            }
        }

        // Gabungkan semua konteks, batasi ~8000 karakter agar tidak overflow token
        $fullContext = implode("\n\n", $contextParts);
        if (mb_strlen($fullContext) > 8000) {
            $fullContext = mb_substr($fullContext, 0, 8000) . "\n[... konteks dipotong ...]";
        }

        return [$fullContext, $usedIds];
    }

    // Split teks jadi chunks, ambil yang ada keyword query
    private function getRelevantChunks(string $text, string $queryLower): array
    {
        // Pecah per paragraf
        $paragraphs = preg_split('/\n{2,}/', $text);
        $relevant   = [];

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (strlen($para) < 30) continue;

            // Skor relevansi: hitung kata-kata query yang muncul
            $words = preg_split('/\s+/', $queryLower);
            $hits  = 0;
            foreach ($words as $word) {
                if (strlen($word) > 2 && mb_stripos($para, $word) !== false) {
                    $hits++;
                }
            }

            if ($hits > 0) {
                $relevant[] = ['text' => $para, 'score' => $hits];
            }
        }

        // Urutkan berdasarkan skor, ambil top 5
        usort($relevant, fn($a, $b) => $b['score'] - $a['score']);
        $top = array_slice($relevant, 0, 5);

        return array_column($top, 'text');
    }

    // ─── Kirim ke Groq API ───────────────────────────────────────────────────
    // Groq pakai format OpenAI-compatible, jadi strukturnya simpel banget
    private function callGroq(string $query, string $context): array
    {
        $hasContext = !empty($context);

        $systemPrompt = <<<PROMPT
Kamu adalah SSC Smart Assistant, asisten AI resmi Student Service Center (SSC) Universitas Telkom untuk membantu mahasiswa memahami informasi tentang TAK (Transkrip Aktivitas Kemahasiswaan).

ATURAN WAJIB (Guardrails):
1. HANYA jawab pertanyaan yang berkaitan dengan TAK, SSC, kegiatan kemahasiswaan, atau layanan SSC.
2. Jika pertanyaan di luar topik tersebut, tolak dengan sopan dan arahkan ke topik TAK/SSC.
3. Jawab HANYA berdasarkan dokumen/konteks yang diberikan. Jika informasi tidak ada di konteks, katakan "Informasi ini belum tersedia di basis pengetahuan kami. Silakan hubungi SSC langsung."
4. Jangan mengarang informasi. Jangan berspekulasi.
5. Gunakan bahasa Indonesia yang ramah, jelas, dan profesional.
6. Jika pertanyaan ambigu, minta klarifikasi.

KONTEKS DOKUMEN:
$context
PROMPT;

        if (!$hasContext) {
            $systemPrompt .= "\n\nCATATAN: Belum ada dokumen yang di-upload staf. Jawab secara umum tentang TAK saja, dan sarankan mahasiswa menghubungi SSC langsung.";
        }

        // Format OpenAI-compatible (sama persis seperti yang dipakai di praktikum)
        $payload = [
            'model'       => $this->groqModel,
            'temperature' => 0.2,
            'max_tokens'  => 800,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $query],
            ],
        ];

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->groqApiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT    => 30,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            error_log("Groq API error (HTTP $httpCode): $raw");
            return ["Maaf, terjadi kesalahan saat menghubungi AI. Silakan coba lagi.", 'error'];
        }

        $data = json_decode($raw, true);
        $text = $data['choices'][0]['message']['content'] ?? null;

        if (!$text) {
            return ["Maaf, AI tidak memberikan respons. Silakan coba lagi.", 'error'];
        }

        // Deteksi apakah AI menolak (out of topic)
        $lowerText = mb_strtolower($text);
        $outOfTopicSignals = ['di luar topik', 'tidak dapat membantu', 'bukan bidang saya', 'tidak berkaitan'];
        $status = 'success';
        foreach ($outOfTopicSignals as $signal) {
            if (mb_stripos($lowerText, $signal) !== false) {
                $status = 'out_of_topic';
                break;
            }
        }

        return [$text, $status];
    }

    // ─── Simpan Log Chat ke DB ───────────────────────────────────────────────
    private function saveLog(
        string $sessionId,
        string $query,
        string $response,
        array $docIds,
        string $status,
        int $elapsedMs
    ): void {
        global $pdo;
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO chat_logs (session_id, user_query, bot_response, source_doc_ids, status, response_time_ms)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $sessionId,
                $query,
                $response,
                json_encode($docIds),
                $status,
                $elapsedMs,
            ]);
        } catch (PDOException $e) {
            error_log("Gagal menyimpan chat log: " . $e->getMessage());
        }
    }

    // ─── Endpoint: Ambil Log Chat untuk Dashboard Admin ───────────────────────
    public function getLogs()
    {
        session_start();
        if (!isset($_SESSION['admin_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        header('Content-Type: application/json');
        global $pdo;

        $limit = (int) ($_GET['limit'] ?? 50);
        $stmt  = $pdo->prepare(
            "SELECT id, session_id, user_query, bot_response, status, response_time_ms, created_at
             FROM chat_logs ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}