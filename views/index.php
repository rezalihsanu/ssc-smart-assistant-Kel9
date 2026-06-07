<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSC Smart Assistant - TAK</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Chat Widget ─────────────────────────────────────────────────── */
        .chat-widget{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);max-width:700px;margin:0 auto}
        .chat-header{background:linear-gradient(135deg,#1e3a8a,#2563eb);color:#fff;padding:1.25rem 1.5rem;display:flex;align-items:center;gap:.75rem}
        .chat-header .avatar{width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem}
        .chat-header .info h3{margin:0;font-size:1rem}
        .chat-header .info p{margin:0;font-size:.78rem;opacity:.8}
        .online-dot{width:10px;height:10px;background:#22c55e;border-radius:50%;border:2px solid #fff;margin-left:auto}
        .chat-messages{height:380px;overflow-y:auto;padding:1.25rem;background:#f8fafc;display:flex;flex-direction:column;gap:.75rem;scroll-behavior:smooth}
        .msg{max-width:80%;padding:.75rem 1rem;border-radius:12px;font-size:.9rem;line-height:1.5}
        .msg.bot{background:#fff;border:1px solid #e2e8f0;border-bottom-left-radius:4px;align-self:flex-start}
        .msg.user{background:#2563eb;color:#fff;border-bottom-right-radius:4px;align-self:flex-end}
        .msg .timestamp{font-size:.7rem;opacity:.6;margin-top:.35rem}
        .msg.bot .timestamp{text-align:left}
        .msg.user .timestamp{text-align:right}
        .typing-indicator{display:flex;gap:4px;padding:.75rem 1rem;background:#fff;border:1px solid #e2e8f0;border-radius:12px;border-bottom-left-radius:4px;align-self:flex-start;align-items:center}
        .typing-indicator span{width:8px;height:8px;border-radius:50%;background:#94a3b8;animation:blink 1.2s infinite}
        .typing-indicator span:nth-child(2){animation-delay:.2s}
        .typing-indicator span:nth-child(3){animation-delay:.4s}
        @keyframes blink{0%,80%,100%{opacity:.3}40%{opacity:1}}
        .chat-input-area{display:flex;gap:.5rem;padding:1rem;border-top:1px solid #e2e8f0;background:#fff}
        .chat-input-area input{flex:1;border:1px solid #e2e8f0;border-radius:8px;padding:.65rem 1rem;font-family:inherit;font-size:.9rem;outline:none;transition:border .2s}
        .chat-input-area input:focus{border-color:#2563eb}
        .chat-input-area button{background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.65rem 1.1rem;cursor:pointer;font-size:1.1rem;transition:background .2s}
        .chat-input-area button:hover{background:#1d4ed8}
        .chat-input-area button:disabled{background:#94a3b8;cursor:not-allowed}
        .quick-questions{padding:.75rem 1.25rem;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;flex-wrap:wrap;gap:.5rem}
        .quick-btn{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;border-radius:20px;padding:.35rem .85rem;font-size:.78rem;cursor:pointer;transition:all .2s}
        .quick-btn:hover{background:#2563eb;color:#fff}

        /* ── FAQ Section ─────────────────────────────────────────────────── */
        .faq-section{background:#f8fafc;padding:3.5rem 0}
        .faq-section h2{font-size:2rem;margin-bottom:.5rem;color:var(--tu-red-dark);text-align:center}
        .faq-section .faq-subtitle{text-align:center;color:#64748b;margin-bottom:2.5rem;font-size:1rem}
        .faq-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.25rem;max-width:960px;margin:0 auto}
        .faq-item{background:#fff;border-radius:10px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:box-shadow .2s}
        .faq-item:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
        .faq-question{width:100%;text-align:left;background:none;border:none;padding:1.1rem 1.25rem;font-size:.95rem;font-weight:600;color:#1e293b;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:.75rem;font-family:inherit}
        .faq-question:hover{background:#f8fafc}
        .faq-question .faq-icon{font-size:1.1rem;flex-shrink:0;transition:transform .3s;color:#2563eb}
        .faq-answer{max-height:0;overflow:hidden;transition:max-height .35s ease,padding .3s}
        .faq-answer.open{max-height:400px}
        .faq-answer-inner{padding:0 1.25rem 1.1rem;color:#475569;font-size:.9rem;line-height:1.7;border-top:1px solid #f1f5f9}
        .faq-question.active .faq-icon{transform:rotate(45deg)}

        /* ── Navbar FAQ link ─────────────────────────────────────────────── */
        .nav-links a.faq-link{background:rgba(255,255,255,.15);border-radius:4px;padding:.35rem .9rem}
        .nav-links a.faq-link:hover{background:rgba(255,255,255,.3)}
    </style>
</head>
<body>
    <!-- ── Navbar ───────────────────────────────────────────────────────── -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php?route=home" class="logo">
                <img src="assets/images/logo.png" alt="SSC Logo" style="height:40px;margin-right:10px;vertical-align:middle">
                SSC Smart Assistant
            </a>
            <div class="nav-links">
                <a href="#tentang">Tentang TAK</a>
                <a href="#faq" class="faq-link">❓ FAQ</a>
                <a href="#chatbot">Tanya Asisten</a>
                <a href="index.php?route=login" class="btn-login">Login Staf</a>
            </div>
        </div>
    </nav>

    <!-- ── Hero ─────────────────────────────────────────────────────────── -->
    <header class="hero">
        <div class="container">
            <h1>Selamat Datang di Layanan Informasi TAK</h1>
            <p>Dapatkan jawaban cepat seputar Transkrip Aktivitas Kemahasiswaan (TAK) kapan saja, 24/7, tanpa antre.</p>
            <a href="#chatbot" class="btn-primary">Mulai Chat Sekarang ↓</a>
        </div>
    </header>

    <!-- ── Tentang TAK ───────────────────────────────────────────────────── -->
    <section id="tentang" class="tentang container">
        <h2>Apa itu TAK?</h2>
        <p>Transkrip Aktivitas Kemahasiswaan (TAK) adalah rekapitulasi poin kegiatan ekstrakurikuler mahasiswa selama berkuliah di Telkom University. Poin ini menjadi salah satu syarat pendaftaran yudisium/kelulusan. Asisten cerdas ini siap menjawab pertanyaan mengenai jenis, bobot, syarat upload, dan ketentuan TAK.</p>
    </section>

    <!-- ── FAQ ─────────────────────────────────────────────────────────── -->
    <section id="faq" class="faq-section">
        <div class="container">
            <h2>FAQ — Pertanyaan Umum TAK</h2>
            <p class="faq-subtitle">Pertanyaan yang sering ditanyakan mahasiswa seputar TAK & layanan SSC</p>
            <div class="faq-grid">

                <!-- Kolom kiri -->
                <div>
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Apa itu TAK dan kenapa penting?
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                TAK (Transkrip Aktivitas Kemahasiswaan) adalah rekap nilai poin dari kegiatan non-akademik (organisasi, kompetisi, seminar, dll). Poin TAK <strong>wajib dipenuhi</strong> sebagai syarat pendaftaran yudisium/wisuda di Telkom University.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Berapa poin TAK minimal untuk lulus / yudisium?
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Persyaratan poin TAK berbeda per program studi dan angkatan. Secara umum mahasiswa S1 diwajibkan minimal <strong>120–140 poin</strong>. Konfirmasi detail ke SSC atau lihat SK TAK terbaru yang tersedia di asisten AI ini.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Kegiatan apa saja yang bisa mendapat poin TAK?
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Secara umum poin TAK bisa diperoleh dari:
                                <ul style="margin:.5rem 0 0 1rem">
                                    <li>Kepanitiaan & organisasi kemahasiswaan</li>
                                    <li>Kompetisi (juara mendapat poin lebih)</li>
                                    <li>Seminar / webinar (peserta maupun pembicara)</li>
                                    <li>Pelatihan, sertifikasi, PKM</li>
                                    <li>Kegiatan pengabdian masyarakat</li>
                                </ul>
                                Bobot poin tiap kategori diatur dalam SK TAK aktif.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Bagaimana cara upload bukti kegiatan TAK?
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Upload bukti kegiatan melalui portal resmi SSC Telkom University. Siapkan dokumen pendukung seperti sertifikat, SK kepanitiaan, atau surat keterangan. Pengajuan akan diverifikasi oleh staf SSC sebelum poin dikreditkan.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom kanan -->
                <div>
                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Kapan batas akhir pengajuan TAK?
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Deadline pengajuan TAK biasanya mengikuti jadwal yudisium tiap semester. Pantau pengumuman resmi di portal mahasiswa atau media sosial SSC, dan <strong>jangan menunggu mepet deadline</strong> karena proses verifikasi membutuhkan waktu.
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Dokumen apa saja yang perlu disiapkan untuk TAK?
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Tergantung jenis kegiatan. Umumnya:
                                <ul style="margin:.5rem 0 0 1rem">
                                    <li><strong>Seminar/webinar:</strong> Sertifikat kehadiran</li>
                                    <li><strong>Kompetisi:</strong> Sertifikat + bukti prestasi</li>
                                    <li><strong>Kepanitiaan:</strong> SK kepanitiaan dari instansi</li>
                                    <li><strong>Organisasi:</strong> SK kepengurusan</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Berapa lama proses verifikasi TAK?
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Proses verifikasi biasanya memakan waktu <strong>3–7 hari kerja</strong> tergantung volume pengajuan. Jika sudah melewati batas tersebut, silakan hubungi SSC langsung di jam operasional (Senin–Jumat, 08.00–16.00 WIB).
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button class="faq-question" onclick="toggleFaq(this)">
                            Bagaimana cara menghubungi SSC jika ada pertanyaan lebih lanjut?
                            <span class="faq-icon">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">
                                Kamu bisa menghubungi SSC melalui:
                                <ul style="margin:.5rem 0 0 1rem">
                                    <li>📍 Datang langsung ke kantor SSC kampus terdekat</li>
                                    <li>💬 Chat asisten AI ini untuk pertanyaan cepat</li>
                                    <li>🕐 Jam operasional: Senin–Jumat 08.00–16.00 WIB</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /faq-grid -->
        </div>
    </section>

    <!-- ── Chatbot ─────────────────────────────────────────────────────── -->
    <section id="chatbot" class="chatbot container">
        <h2>Chatbot Asisten TAK</h2>
        <p style="color:#64748b;text-align:center;margin-bottom:1.5rem">Tanya apapun seputar TAK & layanan SSC. Asisten AI kami siap membantu 24/7.</p>

        <div class="chat-widget">
            <div class="chat-header">
                <div class="avatar">🤖</div>
                <div class="info">
                    <h3>SSC Smart Assistant</h3>
                    <p>Asisten AI TAK — Telkom University</p>
                </div>
                <div class="online-dot"></div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <div class="msg bot">
                    Halo! 👋 Saya adalah <strong>SSC Smart Assistant</strong>. Saya siap membantu kamu dengan pertanyaan seputar <strong>Transkrip Aktivitas Kemahasiswaan (TAK)</strong> dan layanan SSC. Atau cek dulu seksi <a href="#faq" style="color:#2563eb">FAQ di atas</a> untuk pertanyaan umum!
                    <div class="timestamp">Sekarang</div>
                </div>
            </div>

            <div class="quick-questions" id="quickQuestions">
                <button class="quick-btn" onclick="sendQuick('Apa itu TAK dan apa fungsinya?')">Apa itu TAK?</button>
                <button class="quick-btn" onclick="sendQuick('Berapa poin TAK minimal untuk yudisium?')">Poin minimal yudisium?</button>
                <button class="quick-btn" onclick="sendQuick('Bagaimana cara upload kegiatan ke TAK?')">Cara upload kegiatan</button>
                <button class="quick-btn" onclick="sendQuick('Kegiatan apa saja yang bisa dapat poin TAK?')">Jenis kegiatan TAK</button>
            </div>

            <div class="chat-input-area">
                <input type="text" id="chatInput" placeholder="Ketik pertanyaan tentang TAK..." maxlength="500">
                <button onclick="sendMessage()" id="sendBtn">➤</button>
            </div>
        </div>
    </section>

    <!-- ── Footer ────────────────────────────────────────────────────────── -->
    <footer class="footer">
        <div class="container">
            <div class="footer-left">
                <p>&copy; 2026 Student Service Center (SSC) — Telkom University</p>
                <p>Jam Operasional Kantor: Senin–Jumat (08:00–16:00 WIB)</p>
                <p>📍 Bandung | Jakarta | Surabaya | Purwokerto</p>
            </div>
        </div>
    </footer>

    <script>
        const SESSION_ID  = 'sess_' + Math.random().toString(36).substr(2,9) + '_' + Date.now();
        const messagesEl  = document.getElementById('chatMessages');
        const inputEl     = document.getElementById('chatInput');
        const sendBtn     = document.getElementById('sendBtn');

        function getTime() {
            return new Date().toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
        }

        function appendMessage(text, role) {
            const div = document.createElement('div');
            div.className = 'msg ' + role;
            div.innerHTML = text.replace(/\n/g,'<br>') + `<div class="timestamp">${getTime()}</div>`;
            messagesEl.appendChild(div);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return div;
        }

        function showTyping() {
            const el = document.createElement('div');
            el.className = 'typing-indicator'; el.id = 'typingIndicator';
            el.innerHTML = '<span></span><span></span><span></span>';
            messagesEl.appendChild(el);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function hideTyping() {
            const el = document.getElementById('typingIndicator');
            if (el) el.remove();
        }

        async function sendMessage() {
            const text = inputEl.value.trim();
            if (!text) return;
            document.getElementById('quickQuestions').style.display = 'none';
            appendMessage(text, 'user');
            inputEl.value = ''; sendBtn.disabled = true; showTyping();
            try {
                const res  = await fetch('index.php?route=chat', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ message: text, session_id: SESSION_ID }),
                });
                const data = await res.json();
                hideTyping();
                appendMessage(data.reply || data.error || 'Terjadi kesalahan.', 'bot');
            } catch (err) {
                hideTyping();
                appendMessage('⚠️ Gagal terhubung ke server. Pastikan koneksi internet aktif dan coba lagi.', 'bot');
            } finally {
                sendBtn.disabled = false; inputEl.focus();
            }
        }

        function sendQuick(text) { inputEl.value = text; sendMessage(); }

        inputEl.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });

        // ── FAQ accordion ──────────────────────────────────────────────────
        function toggleFaq(btn) {
            const answer = btn.nextElementSibling;
            const isOpen = answer.classList.contains('open');

            // Tutup semua FAQ lain di grid yang sama
            btn.closest('.faq-grid').querySelectorAll('.faq-answer.open').forEach(el => {
                el.classList.remove('open');
                el.previousElementSibling.classList.remove('active');
            });

            if (!isOpen) {
                answer.classList.add('open');
                btn.classList.add('active');
            }
        }

        // Smooth scroll to anchor FAQ dari navbar
        document.querySelectorAll('a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                const target = document.querySelector(a.getAttribute('href'));
                if (target) { e.preventDefault(); target.scrollIntoView({ behavior:'smooth' }); }
            });
        });
    </script>
</body>
</html>
