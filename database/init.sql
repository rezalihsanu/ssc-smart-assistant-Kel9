-- ============================================================
--  SSC Smart Assistant — Database Schema (Complete / Updated)
-- ============================================================
CREATE DATABASE IF NOT EXISTS ssc_smart_assistant;
USE ssc_smart_assistant;

-- ------------------------------------------------------------
-- 1. admin_users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nama          VARCHAR(100),
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Kredensial Default  (email: admin@ssc.com | password: password123)
INSERT INTO admin_users (nama, email, password_hash)
VALUES ('Admin SSC', 'admin@ssc.com',
        '$2y$10$670q8WlAItmD6T5iK4Q.5B4wAEM6r/eUa3j3c0jW35hPxL0T7sW');

-- ------------------------------------------------------------
-- 2. documents  (metadata lengkap sesuai PRD)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documents (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename   VARCHAR(255) NOT NULL,
    file_path         VARCHAR(255) NOT NULL,
    file_type         VARCHAR(10)  NOT NULL,
    file_size_kb      INT          DEFAULT 0,
    extracted_text    LONGTEXT,
    is_active         TINYINT(1)   DEFAULT 1       COMMENT '1=Aktif, 0=Nonaktif',
    uploaded_by       INT,
    uploaded_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- 3. chat_logs  (dengan kolom tambahan response_time & doc ref)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    session_id      VARCHAR(100),
    user_query      TEXT,
    bot_response    TEXT,
    source_doc_ids  JSON                                    COMMENT 'ID dokumen yang dipakai sebagai konteks RAG',
    status          ENUM('success','error','out_of_topic')  DEFAULT 'success',
    response_time_ms INT                                    DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 4. staff_activity_logs  (BARU — PRD: "log aktivitas staf")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff_activity_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    staff_id    INT,
    staff_email VARCHAR(100),
    action      ENUM('upload','delete','toggle_active','toggle_inactive',
                     'login','login_failed','logout')        NOT NULL,
    target_type VARCHAR(50)   COMMENT 'Jenis objek: document, system, session',
    target_id   INT           COMMENT 'ID objek yang terdampak',
    target_name VARCHAR(255)  COMMENT 'Nama/label objek',
    ip_address  VARCHAR(45),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- 5. system_settings  (BARU — PRD: "pengaturan sistem di DB")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100)  NOT NULL UNIQUE,
    setting_value TEXT,
    description   VARCHAR(255),
    updated_by    INT,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('groq_model',         'llama-3.3-70b-versatile', 'Model Groq AI yang digunakan'),
('max_context_chars',  '8000',                    'Maksimal karakter konteks RAG'),
('max_file_size_mb',   '10',                      'Batas ukuran file upload (MB)'),
('allowed_file_types', 'pdf,docx,txt',            'Tipe file yang diperbolehkan (koma)'),
('chatbot_name',       'SSC Smart Assistant',     'Nama yang ditampilkan chatbot');
