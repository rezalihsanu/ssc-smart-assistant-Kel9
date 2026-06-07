<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Staf — SSC Smart Assistant</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .error-msg  { background:#fff1f2; color:#9f1239; padding:.85rem 1rem; border-radius:6px; margin-bottom:1.5rem; border-left:4px solid #e11d48; display:flex; align-items:flex-start; gap:.6rem; font-size:.9rem; }
        .info-msg   { background:#f0fdf4; color:#14532d; padding:.85rem 1rem; border-radius:6px; margin-bottom:1.5rem; border-left:4px solid #16a34a; font-size:.9rem; }
        .error-icon { font-size:1.1rem; flex-shrink:0; margin-top:.05rem; }
        .error-detail { font-size:.8rem; color:#be123c; margin-top:.3rem; }
        .input-error { border-color:#e11d48 !important; background:#fff5f5; }
        .input-hint  { font-size:.75rem; color:#64748b; margin-top:.35rem; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="index.php?route=home" class="logo">
                <img src="assets/images/logo.png" alt="SSC Logo" style="height:40px;margin-right:10px;vertical-align:middle">
                SSC Smart Assistant
            </a>
            <div class="nav-links">
                <a href="index.php?route=home" class="btn-login">Beranda</a>
            </div>
        </div>
    </nav>

    <div class="login-page">
        <div class="login-card">
            <h2>Login Staf SSC</h2>
            <p>Portal manajemen dokumen dan monitoring chatbot TAK.</p>

            <?php
            $error = $_GET['error'] ?? '';
            $info  = $_GET['info']  ?? '';
            $savedEmail = htmlspecialchars($_GET['email'] ?? '');

            $emailError = false;
            $passError  = false;

            if ($info === 'logged_out'): ?>
                <div class="info-msg">✅ Anda berhasil logout. Sampai jumpa!</div>
            <?php elseif ($error === 'empty'): ?>
                <div class="error-msg">
                    <span class="error-icon">⚠️</span>
                    <div>
                        <strong>Kolom tidak boleh kosong</strong>
                        <div class="error-detail">Email dan password wajib diisi sebelum melanjutkan.</div>
                    </div>
                </div>
                <?php $emailError = true; $passError = true; ?>
            <?php elseif ($error === 'invalid_email'): ?>
                <div class="error-msg">
                    <span class="error-icon">📧</span>
                    <div>
                        <strong>Format email tidak valid</strong>
                        <div class="error-detail">Masukkan alamat email yang benar, misal: <em>nama@ssc.com</em></div>
                    </div>
                </div>
                <?php $emailError = true; ?>
            <?php elseif ($error === 'invalid'): ?>
                <div class="error-msg">
                    <span class="error-icon">🔒</span>
                    <div>
                        <strong>Email atau password salah</strong>
                        <div class="error-detail">
                            Akun dengan email <em><?= $savedEmail ?: '(tidak diketahui)' ?></em> tidak ditemukan
                            atau password yang dimasukkan tidak cocok.<br>
                            Hubungi administrator jika Anda lupa kredensial.
                        </div>
                    </div>
                </div>
                <?php $passError = true; ?>
            <?php elseif ($error): ?>
                <div class="error-msg">
                    <span class="error-icon">❌</span>
                    <div><strong>Terjadi kesalahan saat login. Coba lagi.</strong></div>
                </div>
            <?php endif; ?>

            <form action="index.php?route=login_process" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           placeholder="nama@ssc.com"
                           value="<?= $savedEmail ?>"
                           class="<?= $emailError ? 'input-error' : '' ?>"
                           required autocomplete="email">
                    <?php if ($emailError && $error !== 'invalid'): ?>
                        <div class="input-hint">📧 Contoh: admin@ssc.com</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="••••••••"
                           class="<?= $passError ? 'input-error' : '' ?>"
                           required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-primary-block">Masuk ke Dashboard</button>
            </form>
        </div>
    </div>
</body>
</html>
