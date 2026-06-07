# Product Requirements Document (PRD): SSC Smart Assistant (TAK Edition)

## 1. Latar Belakang & Ringkasan Produk

### 1.1 Latar Belakang
Student Service Center (SSC) di Unit Akademik TUS saat ini menghadapi tantangan dalam melayani pertanyaan repetitif dari mahasiswa, terutama yang berkaitan dengan pedoman dan birokrasi **Transkrip Aktivitas Kemahasiswaan (TAK)**. Keterbatasan staf dan batasan jam operasional membuat respons tertunda. 

### 1.2 Ringkasan Produk
**SSC Smart Assistant** adalah platform asisten virtual berbasis AI. Memanfaatkan pendekatan **RAG (*Retrieval-Augmented Generation*)**, Chatbot AI akan mencari jawaban langsung dari dokumen resmi yang diunggah staf. Proyek ini dibangun sepenuhnya menggunakan **PHP Native, JavaScript (Vanilla), dan MySQL** untuk memastikan sistem terstruktur dengan fondasi kokoh tanpa ketergantungan *framework* pihak ketiga yang berat.

---

## 2. Arsitektur Sistem & Struktur Direktori (PHP Native)

Karena kita menggunakan PHP Native, proyek ini akan menerapkan desain pola **MVC (Model-View-Controller) Sederhana** untuk memisahkan antara logika database, pemrosesan bisnis, dan tampilan antarmuka (UI).

### 2.1 Struktur Folder Rekomendasi
\\\	ext
ssc-smart-assistant-Kel9/
�
+-- /assets                 # Asset statis publik
�   +-- /css                # File style (style.css, dsb.)
�   +-- /js                 # Logika interaksi frontend (script.js, chat.js)
�   +-- /images             # Logo & Ikon
�
+-- /config                 # Konfigurasi Fundamental
�   +-- database.php        # Skrip koneksi PDO ke MySQL
�   +-- env.php             # Variabel kredensial API (e.g. Gemini API Key)
�
+-- /controllers            # Logika Bisnis & Pengendali
�   +-- ChatController.php  # Menangani input user, request ke AI, RAG logic
�   +-- AuthController.php  # Validasi login staff, session
�   +-- DocController.php   # Menangani upload/hapus dokumen dari staff
�
+-- /models                 # Representasi Tabel & Skema MySQL
�   +-- UserModel.php       # Query untuk tabel admin_users
�   +-- DocumentModel.php   # Query untuk tabel documents
�   +-- LogModel.php        # Query menyisipkan chat_logs
�
+-- /views                  # Antarmuka Pengguna (HTML/PHP Tampilan)
�   +-- /admin              # Folder view khusus untuk staff (Dashboard, Login)
�   +-- /partials           # Potongan view berulang (header.php, footer.php, navbar.php)
�   +-- index.php           # Landing page utama
�
+-- /uploads                # Folder tersimpan file fisik (PDF/Docs)
�
+-- index.php               # Front Controller / Pintu Masuk Utama (Router sederhana)
+-- PRD.md                  # Dokumentasi Proyek
\\\

### 2.2 Alur Pemrosesan (Data Flow) Chat AI
1. Mahasiswa mengirim pesan dari UI (melalui Vanilla JS etch).
2. JS mengirim *POST request* ke index.php?route=chat.
3. ChatController.php menerima teks.
4. DocumentModel.php mengambil isi teks dari dokumen SK TAK terbaru yang tersimpan.
5. ChatController.php merangkai Prompt: *"Jawab pertanyaan ini [{INPUT_USER}] hanya berdasarkan konteks berikut: [{ISI_DOKUMEN_TAK}]"*.
6. Permintaan HTTP menggunakan cURL dikirim ke endpoint **Gemini API**.
7. Respons dari Gemini dikembalikan ke ChatController.php.
8. LogModel.php mencatat *User Query* dan *Bot Response* ke database MySQL.
9. JSON _response_ dikirim kembali ke _frontend_ dan ditampilkan di UI *chat widget*.

---

## 3. Desain Skema Database (MySQL)

Sistem akan menggunakan pendekatan relasi sederhana dan ringan. Wajib menggunakan **PDO (PHP Data Objects)** untuk koneksinya.

### 3.1 Tabel \dmin_users\
Tabel untuk autentikasi staff SSC.
- \id\ (INT, Primary Key, Auto Increment)
- \email\ (VARCHAR 100, Unique)
- \password_hash\ (VARCHAR 255) -> *Harus dienkripsi menggunakan password_hash() di PHP.*
- \created_at\ (DATETIME, Default CURRENT_TIMESTAMP)

### 3.2 Tabel \documents\
Melacak file referensi pengetahuan yang diunggah staf.
- \id\ (INT, Primary Key, Auto Increment)
- \ilename\ (VARCHAR 150) -> *Nama asli file*
- \ile_path\ (VARCHAR 255) -> *Lokasi di folder /uploads/*
- \extracted_text\ (LONGTEXT) -> *Hasil parsing teks dokumen agar tidak perlu parse PDF berulang kali saat load.*
- \uploaded_by\ (INT) -> *Foreign Key ke dmin_users.id*
- \uploaded_at\ (DATETIME, Default CURRENT_TIMESTAMP)

### 3.3 Tabel \chat_logs\
Menyimpan riwayat tanya-jawab untuk analitik.
- \id\ (INT, Primary Key, Auto Increment)
- \session_id\ (VARCHAR 100) -> *ID anonim dari user session (browser)*
- \user_query\ (TEXT)
- \ot_response\ (TEXT)
- \status\ (ENUM: 'success', 'error', 'out_of_topic')
- \created_at\ (DATETIME, Default CURRENT_TIMESTAMP)

---

## 4. Kebutuhan Fungsional & Modul

### Modul Publik (PIC: Rezal)
- **Laman Statis:** Menampilkan FAQ & Pengantar (Routing PHP Sederhana).
- **Asisten Widget:** Komponen UI *floating* yang menerima input asinkron (AJAX) dari *user*.

### Modul Portal Staf (PIC: Yasmin & Cito)
- **Autentikasi:** Login Page yang menginisiasi $_SESSION['admin_id'].
- **Manajemen Dokumen (CRUD):** 
  - Validasi *MIME type* file yang diupload (Hanya izinkan .txt atau PDF jika digabung dengan pustaka smalot/pdfparser).
  - Hapus data dokumen dan file aslinya (fungsi unlink() di PHP).

### Modul AI Backend (PIC: Ardin & Andrew)
- **Integrasi API:** Menggunakan curl_init(), curl_setopt() untuk menyambung layanan Eksternal (Gemini RPC).
- **Context Injection (RAG Setup):** Menggabungkan \user_query\ dengan nilai \extracted_text\ dari tabel \documents\.

---

## 5. Standar Keamanan PHP (Security Checklist)

Sistem wajib menerapkan standar pengamanan dasar PHP:
1. **SQL Injection Prevention:** Seluruh query ke MySQL **wajib** menggunakan *Prepared Statements* (contoh: $stmt = ->prepare("SELECT * FROM admin_users WHERE email = ?")). Jangan gunakan interpolasi variabel string.
2. **Cross-Site Scripting (XSS) Mitigation:** Output teks yang dimasukkan user ke layar wajib dibungkus dengan htmlspecialchars(, ENT_QUOTES, 'UTF-8').
3. **Password Security:** Selalu gunakan password_hash() untuk menyimpan sandi dan password_verify() untuk memvalidasi login. Jangan gunakan MD5 atau SHA1.
4. **Session Hijacking:** Terapkan session_regenerate_id() pascalogin yang berhasil.
5. **Secure API Key:** Simpan kredensial Gemini API Key dan koneksi database di file config/env.php dan pastikan direktori config diproteksi .htaccess (misal: Deny from all) atau letakkan environment di luar root web jika memungkinkan.

---

## 6. Metrik Kesuksesan & KPI

1. **Akurasi Jawaban AI:** > 90% pertanyaan TAK terjawab kontekstual.
2. **Kecepatan Latensi API:** Backend script PHP merespons kurang dari 3-5 detik (*timeout threshold*).
3. **Cakupan Penggunaan Harian:** Mampu merekam aktivitas hingga 1.000 log chat tanpa penurunan performa MariaDB/MySQL.

