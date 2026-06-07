<?php
// controllers/AuthController.php
// Update: log aktivitas login/logout + pesan error yang lebih informatif

require_once 'config/database.php';

class AuthController
{
    // ── Tampilkan halaman login ──────────────────────────────────────────────
    public function showLogin()
    {
        require 'views/admin/login.php';
    }

    // ── Proses data login ────────────────────────────────────────────────────
    public function processLogin()
    {
        global $pdo;

        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // Validasi field kosong
        if (empty($email) || empty($password)) {
            header("Location: index.php?route=login&error=empty");
            exit;
        }

        // Validasi format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: index.php?route=login&error=invalid_email");
            exit;
        }

        // Cek user di database
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login berhasil
            session_start();
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['email']    = $user['email'];
            $_SESSION['nama']     = $user['nama'] ?? 'Staf SSC';

            // ── Log aktivitas login ──
            $this->logStaffActivity($pdo, $user['id'], $user['email'], 'login');

            header("Location: index.php?route=dashboard");
            exit;
        } else {
            // Login gagal — log percobaan gagal (tanpa ID staf karena belum auth)
            $this->logStaffActivity($pdo, null, $email, 'login_failed');

            header("Location: index.php?route=login&error=invalid&email=" . urlencode($email));
            exit;
        }
    }

    // ── Proses logout ────────────────────────────────────────────────────────
    public function logout()
    {
        session_start();

        if (isset($_SESSION['admin_id'])) {
            global $pdo;
            $this->logStaffActivity($pdo, $_SESSION['admin_id'], $_SESSION['email'], 'logout');
        }

        session_destroy();
        header("Location: index.php?route=login&info=logged_out");
        exit;
    }

    // ── Helper: log ke staff_activity_logs ───────────────────────────────────
    private function logStaffActivity(
        PDO $pdo,
        ?int $staffId,
        string $email,
        string $action
    ): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $pdo->prepare(
                "INSERT INTO staff_activity_logs
                    (staff_id, staff_email, action, target_type, target_name, ip_address)
                 VALUES (?, ?, ?, 'session', ?, ?)"
            );
            $stmt->execute([$staffId, $email, $action, 'Auth: ' . $action, $ip]);
        } catch (PDOException $e) {
            error_log("Gagal menyimpan auth log: " . $e->getMessage());
        }
    }
}
