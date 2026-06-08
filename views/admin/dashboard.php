<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php?route=login");
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/DocumentController.php';

// ── Semua dokumen (aktif + nonaktif) ──────────────────────────────────────
$documents = DocumentController::getAll();

// ── Log aktivitas staf (20 terakhir) ──────────────────────────────────────
$staffLogs = DocumentController::getStaffLogs(20);

// ── Statistik chat 7 hari ─────────────────────────────────────────────────
$stmtStats = $pdo->query("
    SELECT
        COUNT(*) AS total_chats,
        SUM(CASE WHEN status='success'       THEN 1 ELSE 0 END) AS success_count,
        SUM(CASE WHEN status='out_of_topic'  THEN 1 ELSE 0 END) AS out_count,
        ROUND(AVG(response_time_ms))                            AS avg_ms
    FROM chat_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// ── Log chat dengan filter date ───────────────────────────────────────────
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$status   = $_GET['status']    ?? '';
$keyword  = $_GET['keyword']   ?? '';

$where    = [];
$params   = [];

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
     . " ORDER BY created_at DESC LIMIT 100";

$stmtLogs = $pdo->prepare($sql);
$stmtLogs->execute($params);
$chatLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// ── Top 10 pertanyaan terbanyak ──────────────────────────────────
$stmtTopQ = $pdo->query("
    SELECT user_query, COUNT(*) AS cnt
    FROM chat_logs
    GROUP BY user_query
    ORDER BY cnt DESC
    LIMIT 10
");
$topQueries = $stmtTopQ->fetchAll(PDO::FETCH_ASSOC);

// ── Chat per hari (7 hari terakhir) ──────────────────────────────
$stmtDaily = $pdo->query("
    SELECT DATE(created_at) AS tgl, COUNT(*) AS total
    FROM chat_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY tgl ASC
");
$dailyChats = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

$activeFilter = $dateFrom || $dateTo || $status || $keyword;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — SSC Smart Assistant</title>
<link rel="stylesheet" href="assets/css/styles.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ── Stats ─────────────────────────────────────────────── */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem}
    .stat-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1.25rem;text-align:center}
    .stat-value{font-size:2rem;font-weight:700;color:#2563eb}
    .stat-label{font-size:.78rem;color:#64748b;margin-top:.25rem}

    /* ── Badges ────────────────────────────────────────────── */
    .badge{display:inline-block;padding:.2rem .65rem;border-radius:999px;font-size:.73rem;font-weight:600}
    .badge-success{background:#dcfce7;color:#16a34a}
    .badge-error{background:#fee2e2;color:#dc2626}
    .badge-out_of_topic{background:#fef9c3;color:#ca8a04}
    .badge-active{background:#dbeafe;color:#1d4ed8}
    .badge-inactive{background:#f1f5f9;color:#64748b}
    .badge-upload{background:#f0fdf4;color:#15803d}
    .badge-delete{background:#fef2f2;color:#b91c1c}
    .badge-toggle_active{background:#ecfdf5;color:#059669}
    .badge-toggle_inactive{background:#fef3c7;color:#b45309}
    .badge-login{background:#eff6ff;color:#1d4ed8}
    .badge-login_failed{background:#fef2f2;color:#b91c1c}
    .badge-logout{background:#f8fafc;color:#475569}

    /* ── Alerts ─────────────────────────────────────────────── */
    .alert-success{background:#dcfce7;border:1px solid #86efac;color:#166534;padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem}
    .alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem}

    /* ── Section ─────────────────────────────────────────────── */
    .section-title{font-size:1.1rem;font-weight:600;margin-bottom:1rem;color:#1e293b;border-bottom:2px solid #e2e8f0;padding-bottom:.5rem}
    .no-data{text-align:center;color:#94a3b8;padding:2rem 0;font-style:italic}

    /* ── Upload form ─────────────────────────────────────────── */
    .upload-form{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap}
    .upload-form input[type=file]{flex:1}

    /* ── Log query cell ──────────────────────────────────────── */
    .log-query{max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

    /* ── Filter bar ──────────────────────────────────────────── */
    .filter-bar{display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:1rem;margin-bottom:1.25rem}
    .filter-bar label{font-size:.82rem;font-weight:600;color:#475569;display:block;margin-bottom:.3rem}
    .filter-bar input,.filter-bar select{border:1px solid #cbd5e1;border-radius:6px;padding:.45rem .75rem;font-size:.85rem;font-family:inherit;background:#fff}
    .btn-filter{background:#2563eb;color:#fff;border:none;border-radius:6px;padding:.5rem 1.1rem;font-size:.85rem;cursor:pointer;font-weight:600}
    .btn-filter:hover{background:#1d4ed8}
    .btn-reset{background:#64748b;color:#fff;border:none;border-radius:6px;padding:.5rem 1rem;font-size:.85rem;cursor:pointer;text-decoration:none;display:inline-block;font-weight:600}
    .btn-reset:hover{background:#475569}
    .filter-active-indicator{font-size:.78rem;color:#2563eb;font-weight:600;background:#dbeafe;padding:.25rem .65rem;border-radius:999px}

    /* ── Toggle button ───────────────────────────────────────── */
    .btn-toggle{font-size:.78rem;font-weight:600;padding:.3rem .7rem;border-radius:4px;border:none;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-block}
    .btn-toggle-on{background:#fef3c7;color:#b45309;border:1px solid #fde68a}
    .btn-toggle-on:hover{background:#f59e0b;color:#fff}
    .btn-toggle-off{background:#dcfce7;color:#15803d;border:1px solid #86efac}
    .btn-toggle-off:hover{background:#16a34a;color:#fff}

    /* ── Log chat response preview ───────────────────────────── */
    .response-preview{cursor:pointer;color:#2563eb;font-size:.75rem;text-decoration:underline}
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}
    .modal-overlay.open{display:flex}
    .modal-box{background:#fff;border-radius:12px;padding:2rem;max-width:620px;width:90%;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
    .modal-close{float:right;background:none;border:none;font-size:1.3rem;cursor:pointer;color:#64748b}
    .modal-close:hover{color:#1e293b}

    /* ── Analitik ─────────────────────────────────────────── */
    .analitik-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
    @media(max-width:768px){.analitik-grid{grid-template-columns:1fr}}
    .analitik-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:1.25rem}
    .analitik-box h3{font-size:.9rem;font-weight:600;color:#1e293b;margin:0 0 1rem}
    .bar-row{display:flex;align-items:center;gap:.75rem;margin-bottom:.6rem}
    .bar-label{font-size:.8rem;color:#475569;width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex-shrink:0}
    .bar-wrap{flex:1;background:#e2e8f0;border-radius:999px;height:10px;overflow:hidden}
    .bar-fill{height:100%;background:#2563eb;border-radius:999px;transition:width .4s}
    .bar-count{font-size:.78rem;font-weight:600;color:#2563eb;min-width:24px;text-align:right}
    .daily-row{display:flex;align-items:flex-end;gap:6px;height:80px;margin-bottom:.5rem}
    .daily-bar-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
    .daily-bar{width:100%;background:#2563eb;border-radius:4px 4px 0 0;min-height:4px}
    .daily-label{font-size:.68rem;color:#94a3b8;text-align:center}
    .daily-val{font-size:.7rem;font-weight:600;color:#2563eb}
</style>
</head>
<body>
<div class="dashboard-container">

<!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
<aside class="dashboard-sidebar">
    <div style="text-align:center;margin-bottom:2.5rem">
        <img src="assets/images/logo.png" alt="SSC Logo" style="height:60px;margin-bottom:10px">
        <h2 style="margin:0">SSC Admin</h2>
        <p style="font-size:.78rem;color:#fca5a5;margin:.25rem 0 0">
            <?= htmlspecialchars($_SESSION['nama'] ?? $_SESSION['email']) ?>
        </p>
    </div>
    <ul class="sidebar-menu">
        <li><a href="index.php?route=dashboard" class="active">📄 Manajemen Dokumen</a></li>
        <li><a href="index.php?route=home" target="_blank">🌐 Lihat Web Mahasiswa</a></li>
        <li><a href="index.php?route=logout">🚪 Logout</a></li>
    </ul>
</aside>

<!-- ── Main ────────────────────────────────────────────────────────────── -->
<main class="dashboard-main">
    <header class="dashboard-header">
        <h1>Dashboard SSC Smart Assistant</h1>
        <span style="font-size:.85rem;color:#94a3b8">Staf: <?= htmlspecialchars($_SESSION['email']) ?></span>
    </header>

    <!-- Alert -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success"><?php
            $msgs = [
                'uploaded'    => '✅ Dokumen berhasil di-upload dan diproses ke basis pengetahuan AI!',
                'deleted'     => '🗑️ Dokumen berhasil dihapus.',
                'activated'   => '✅ Dokumen diaktifkan — AI akan menggunakan dokumen ini kembali.',
                'deactivated' => '🔕 Dokumen dinonaktifkan — AI tidak akan menggunakan dokumen ini.',
            ];
            echo $msgs[$_GET['success']] ?? 'Operasi berhasil.';
        ?></div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="alert-error"><?php
            $errs = [
                'no_file'       => '⚠️ Tidak ada file yang dipilih.',
                'too_large'     => '⚠️ File terlalu besar (maks 10 MB).',
                'invalid_type'  => '⚠️ Tipe file tidak didukung. Gunakan PDF, DOCX, atau TXT.',
                'upload_failed' => '⚠️ Upload gagal. Coba lagi.',
                'move_failed'   => '⚠️ Gagal menyimpan file di server.',
                'invalid_id'    => '⚠️ ID dokumen tidak valid.',
            ];
            echo $errs[$_GET['error']] ?? 'Terjadi kesalahan.';
        ?></div>
    <?php endif; ?>

    <!-- ── Statistik ─────────────────────────────────────────────────────── -->
    <section class="card">
        <div class="section-title">📊 Statistik Chat (7 Hari Terakhir)</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_chats'] ?? 0) ?></div>
                <div class="stat-label">Total Pertanyaan</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#16a34a"><?= number_format($stats['success_count'] ?? 0) ?></div>
                <div class="stat-label">Terjawab Sukses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#ca8a04"><?= number_format($stats['out_count'] ?? 0) ?></div>
                <div class="stat-label">Di Luar Topik</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#7c3aed"><?= ($stats['avg_ms'] ?? 0) ?><small style="font-size:1rem">ms</small></div>
                <div class="stat-label">Rata-rata Respons</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count(array_filter($documents, fn($d) => $d['is_active'])) ?></div>
                <div class="stat-label">Dokumen Aktif</div>
            </div>
        </div>
    </section>

    <!-- ── Upload Dokumen ────────────────────────────────────────────────── -->
    <section class="card">
        <div class="section-title">📤 Upload Dokumen Baru ke Basis Pengetahuan AI</div>
        <p style="color:#64748b;font-size:.9rem;margin-bottom:1rem">
            Upload SK TAK, panduan, atau dokumen peraturan SSC. AI akan otomatis membaca isinya.
            Format didukung: <strong>PDF, DOCX, TXT</strong> (maks 10 MB)
        </p>
        <form action="index.php?route=upload_doc" method="POST" enctype="multipart/form-data" class="upload-form">
            <input type="file" name="tak_document" accept=".pdf,.docx,.txt" required>
            <button type="submit" class="btn-primary">Upload & Proses ke AI</button>
        </form>
    </section>

    <!-- ── Daftar Dokumen ─────────────────────────────────────────────────── -->
    <section class="card">
        <div class="section-title">
            📚 Daftar Dokumen
            <span style="font-size:.8rem;font-weight:400;color:#64748b;margin-left:.5rem">
                (Aktif: <?= count(array_filter($documents, fn($d)=>$d['is_active'])) ?> /
                 Nonaktif: <?= count(array_filter($documents, fn($d)=>!$d['is_active'])) ?>)
            </span>
        </div>
        <?php if (empty($documents)): ?>
            <p class="no-data">Belum ada dokumen. Upload dokumen pertama di atas ☝️</p>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama File</th>
                    <th>Tipe</th>
                    <th>Ukuran</th>
                    <th>Uploader</th>
                    <th>Tanggal Upload</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr style="<?= $doc['is_active'] ? '' : 'opacity:.55;' ?>">
                    <td><?= $doc['id'] ?></td>
                    <td>
                        <?= htmlspecialchars($doc['original_filename']) ?>
                        <?php if (!empty($doc['summary'])): ?>
                            <div style="font-size:.78rem;color:#64748b;margin-top:3px;font-style:italic;line-height:1.4">
                                <?= htmlspecialchars($doc['summary']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-success"><?= strtoupper($doc['file_type']) ?></span></td>
                    <td><?= $doc['file_size_kb'] ?> KB</td>
                    <td style="font-size:.8rem;color:#64748b"><?= htmlspecialchars($doc['uploader_email'] ?? '—') ?></td>
                    <td style="white-space:nowrap;font-size:.85rem"><?= $doc['uploaded_at'] ?></td>
                    <td>
                        <?php if ($doc['is_active']): ?>
                            <span class="badge badge-active">✅ Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">⏸ Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex;gap:.4rem;flex-wrap:wrap">
                        <!-- Toggle Aktif/Nonaktif -->
                        <?php if ($doc['is_active']): ?>
                            <a href="index.php?route=toggle_doc&id=<?= $doc['id'] ?>"
                               class="btn-toggle btn-toggle-on"
                               onclick="return confirm('Nonaktifkan dokumen ini? AI tidak akan menggunakannya sementara.')">
                               ⏸ Nonaktifkan
                            </a>
                        <?php else: ?>
                            <a href="index.php?route=toggle_doc&id=<?= $doc['id'] ?>"
                               class="btn-toggle btn-toggle-off">
                               ▶ Aktifkan
                            </a>
                        <?php endif; ?>
                        <!-- Hapus permanen -->
                        <a href="index.php?route=delete_doc&id=<?= $doc['id'] ?>"
                           class="btn-danger"
                           onclick="return confirm('Hapus PERMANEN dokumen ini dari server dan database?')">
                           🗑
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    <!-- ── Analitik Pertanyaan ───────────────────────────────────────── -->
    <section class="card">
        <div class="section-title">📈 Analitik Pertanyaan Mahasiswa</div>
        <div class="analitik-grid">

            <!-- Kolom kiri: Top pertanyaan -->
            <div class="analitik-box">
                <h3>🔝 Top 10 Pertanyaan Terbanyak</h3>
                <?php if (empty($topQueries)): ?>
                    <p class="no-data">Belum ada data percakapan.</p>
                <?php else:
                    $maxCnt = $topQueries[0]['cnt'];
                ?>
                <?php foreach ($topQueries as $q): ?>
                    <div class="bar-row">
                        <span class="bar-label" title="<?= htmlspecialchars($q['user_query']) ?>">
                            <?= htmlspecialchars($q['user_query']) ?>
                        </span>
                        <div class="bar-wrap">
                            <div class="bar-fill" style="width:<?= round($q['cnt'] / $maxCnt * 100) ?>%"></div>
                        </div>
                        <span class="bar-count"><?= $q['cnt'] ?>x</span>
                    </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Kolom kanan: Grafik per hari -->
            <div class="analitik-box">
                <h3>📅 Volume Chat 7 Hari Terakhir</h3>
                <?php if (empty($dailyChats)): ?>
                    <p class="no-data">Belum ada data percakapan.</p>
                <?php else:
                    $maxDaily = max(array_column($dailyChats, 'total'));
                ?>
                <div class="daily-row">
                <?php foreach ($dailyChats as $d):
                    $pct = $maxDaily > 0 ? round($d['total'] / $maxDaily * 100) : 0;
                    $tgl = date('d/m', strtotime($d['tgl']));
                ?>
                    <div class="daily-bar-wrap">
                        <span class="daily-val"><?= $d['total'] ?></span>
                        <div class="daily-bar" style="height:<?= max(4, $pct * 0.6) ?>px"></div>
                        <span class="daily-label"><?= $tgl ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
                <p style="font-size:.78rem;color:#94a3b8;margin:.5rem 0 0">
                    Total 7 hari: <strong><?= array_sum(array_column($dailyChats, 'total')) ?></strong> percakapan
                </p>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- ── Log Chat dengan Filter ─────────────────────────────────────────── -->
    <section class="card">
        <div class="section-title">
            💬 Log Percakapan Mahasiswa
            <?php if ($activeFilter): ?>
                <span class="filter-active-indicator">🔍 Filter aktif — <?= count($chatLogs) ?> hasil</span>
            <?php else: ?>
                <span style="font-size:.8rem;font-weight:400;color:#94a3b8">(100 terbaru)</span>
            <?php endif; ?>
        </div>

        <!-- Filter bar -->
        <form method="GET" action="index.php">
            <input type="hidden" name="route" value="dashboard">
            <div class="filter-bar">
                <div>
                    <label>📅 Dari Tanggal</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div>
                    <label>📅 Sampai Tanggal</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div>
                    <label>🏷 Status</label>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="success"      <?= $status==='success'       ? 'selected' : '' ?>>✅ Success</option>
                        <option value="out_of_topic" <?= $status==='out_of_topic'  ? 'selected' : '' ?>>⚠️ Out of Topic</option>
                        <option value="error"        <?= $status==='error'         ? 'selected' : '' ?>>❌ Error</option>
                    </select>
                </div>
                <div>
                    <label>🔍 Cari Kata Kunci</label>
                    <input type="text" name="keyword"
                           value="<?= htmlspecialchars($keyword) ?>"
                           placeholder="Ketik kata kunci..."
                           style="min-width:200px">
                </div>
                <div style="display:flex;gap:.5rem;align-items:flex-end">
                    <button type="submit" class="btn-filter">Terapkan Filter</button>
                    <?php if ($activeFilter): ?>
                        <a href="index.php?route=dashboard" class="btn-reset">Reset</a>
                    <?php endif; ?>
                    <a href="index.php?route=export_csv&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&status=<?= urlencode($status) ?>&keyword=<?= urlencode($keyword) ?>"
                       class="btn-reset"
                       style="background:#16a34a"
                       title="Download log chat sebagai file CSV">
                       ⬇ Export CSV
                    </a>
                </div>
            </div>
        </form>

        <?php if (empty($chatLogs)): ?>
            <p class="no-data">Tidak ada percakapan yang cocok dengan filter.</p>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Session ID</th>
                    <th>Pertanyaan Mahasiswa</th>
                    <th>Respons AI</th>
                    <th>Status</th>
                    <th>Waktu (ms)</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($chatLogs as $log): ?>
                <tr>
                    <td><?= $log['id'] ?></td>
                    <td style="font-size:.75rem;color:#94a3b8"><?= htmlspecialchars(substr($log['session_id'], 0, 12)) ?>…</td>
                    <td class="log-query" title="<?= htmlspecialchars($log['user_query']) ?>">
                        <?= htmlspecialchars($log['user_query']) ?>
                    </td>
                    <td>
                        <span class="response-preview"
                              onclick="showResponse(<?= $log['id'] ?>, `<?= htmlspecialchars(addslashes($log['user_query'])) ?>`, `<?= htmlspecialchars(addslashes($log['bot_response'])) ?>`)">
                            Lihat respons →
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $log['status'] ?>">
                            <?= $log['status']==='success' ? '✅ Sukses' : ($log['status']==='out_of_topic' ? '⚠️ OOT' : '❌ Error') ?>
                        </span>
                    </td>
                    <td><?= $log['response_time_ms'] ?> ms</td>
                    <td style="white-space:nowrap;font-size:.8rem"><?= $log['created_at'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- ── Log Aktivitas Staf ─────────────────────────────────────────────── -->
    <section class="card">
        <div class="section-title">🧑‍💼 Log Aktivitas Staf (20 Terbaru)</div>
        <?php if (empty($staffLogs)): ?>
            <p class="no-data">Belum ada aktivitas staf yang tercatat.</p>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Staf (Email)</th>
                    <th>Aksi</th>
                    <th>Target</th>
                    <th>IP Address</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($staffLogs as $log): ?>
                <tr>
                    <td><?= $log['id'] ?></td>
                    <td style="font-size:.85rem"><?= htmlspecialchars($log['staff_email']) ?></td>
                    <td>
                        <span class="badge badge-<?= $log['action'] ?>">
                            <?php
                            $actionLabel = [
                                'upload'         => '📤 Upload',
                                'delete'         => '🗑 Hapus',
                                'toggle_active'  => '✅ Aktifkan',
                                'toggle_inactive'=> '⏸ Nonaktifkan',
                                'login'          => '🔐 Login',
                                'login_failed'   => '❌ Login Gagal',
                                'logout'         => '🚪 Logout',
                            ];
                            echo $actionLabel[$log['action']] ?? $log['action'];
                            ?>
                        </span>
                    </td>
                    <td style="font-size:.82rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                        title="<?= htmlspecialchars($log['target_name']) ?>">
                        <?= htmlspecialchars($log['target_name'] ?? '—') ?>
                    </td>
                    <td style="font-size:.8rem;color:#64748b"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                    <td style="white-space:nowrap;font-size:.8rem"><?= $log['created_at'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

</main>
</div>

<!-- ── Modal Respons AI ──────────────────────────────────────────────────── -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('modalOverlay').classList.remove('open')">✕</button>
        <h3 style="margin-bottom:1rem;color:#1e293b">Detail Percakapan</h3>
        <p style="font-size:.8rem;color:#64748b;margin-bottom:.5rem">❓ Pertanyaan Mahasiswa</p>
        <div id="modalQuery" style="background:#f8fafc;border-radius:8px;padding:.85rem 1rem;margin-bottom:1rem;font-size:.9rem"></div>
        <p style="font-size:.8rem;color:#64748b;margin-bottom:.5rem">🤖 Respons AI</p>
        <div id="modalResponse" style="background:#eff6ff;border-radius:8px;padding:.85rem 1rem;font-size:.9rem;line-height:1.6;white-space:pre-wrap"></div>
    </div>
</div>

<script>
function showResponse(id, query, response) {
    document.getElementById('modalQuery').textContent    = query;
    document.getElementById('modalResponse').textContent = response;
    document.getElementById('modalOverlay').classList.add('open');
}
function closeModal(e) {
    if (e.target === document.getElementById('modalOverlay')) {
        document.getElementById('modalOverlay').classList.remove('open');
    }
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('modalOverlay').classList.remove('open');
});
</script>
</body>
</html>
