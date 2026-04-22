<?php
/**
 * İlk Kurulum Sihirbazı
 * - Veritabanı bağlantısını test eder
 * - Tabloları oluşturur
 * - İlk yönetici kullanıcıyı ekler
 * - Kurulum tamamlandığında install.php'yi kapatmanızı önerir
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

auth_start_session();

// Kullanıcı zaten varsa ve giriş yapmışsa kurulumu engelle
try {
    $userCount = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('users'))->fetchColumn();
    if ($userCount > 0 && !is_logged_in()) {
        // Yine de erişilebilir, ama uyarı göster
    }
} catch (Throwable $e) {
    $userCount = -1; // tablolar yok
}

$mesaj = '';
$hata  = '';
$adim  = (int)($_GET['adim'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'kurulum') {
    // CSRF kontrolü
    if (!csrf_check()) { $hata = 'Oturum süresi doldu. Sayfayı yenileyin.'; }
    else {
        try {
            // 1) DB bağlantı
            db();

            // 2) SQL şeması
            $sql = file_get_contents(__DIR__ . '/sql/install.sql');
            if (!$sql) throw new RuntimeException('sql/install.sql okunamadı.');

            // çok satırlı comment'leri temizle
            $clean = preg_replace('/--[^\n]*\n/', "\n", $sql);
            $ifadeler = array_filter(array_map('trim', explode(';', $clean)));
            foreach ($ifadeler as $q) {
                if ($q === '' || str_starts_with($q, '/*')) continue;
                db()->exec($q);
            }

            // 3) İlk yönetici
            $username = trim((string)($_POST['username'] ?? ''));
            $adsoyad  = trim((string)($_POST['ad_soyad'] ?? ''));
            $email    = trim((string)($_POST['email'] ?? ''));
            $sifre    = (string)($_POST['sifre'] ?? '');
            $sifre2   = (string)($_POST['sifre2'] ?? '');

            $err = [];
            if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) $err[] = 'Kullanıcı adı 3-32 karakter olmalı.';
            if ($adsoyad === '') $err[] = 'Ad Soyad zorunludur.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Geçerli e-posta girin.';
            if (strlen($sifre) < 6) $err[] = 'Şifre en az 6 karakter olmalı.';
            if ($sifre !== $sifre2) $err[] = 'Şifreler uyuşmuyor.';

            if (!empty($err)) throw new RuntimeException(implode(' ', $err));

            // Mevcut admin var mı?
            $existing = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('users'))->fetchColumn();
            if ($existing === 0) {
                $stmt = db()->prepare('INSERT INTO ' . tbl('users') . '
                    (username, password_hash, ad_soyad, email, rol, aktif, olusturma_tarihi)
                    VALUES (?, ?, ?, ?, "admin", 1, NOW())');
                $stmt->execute([$username, password_hash($sifre, PASSWORD_DEFAULT), $adsoyad, $email]);
                $mesaj = 'Kurulum tamamlandı. Kullanıcı adınızla giriş yapabilirsiniz.';
                $adim = 99;
            } else {
                $mesaj = 'Tablolar oluşturuldu. Zaten mevcut kullanıcılarla giriş yapabilirsiniz.';
                $adim = 99;
            }
        } catch (Throwable $e) {
            $hata = $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AKM - İlk Kurulum</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-login">
<div class="login-wrap" style="max-width: 560px">
  <div class="login-card">
    <div class="login-brand">
      <div class="brand-mark">AKM</div>
      <div class="brand-sub">İlk Kurulum</div>
    </div>

    <?php if ($adim === 99): ?>
      <div class="alert alert-success"><strong>✓ Kurulum Tamamlandı!</strong><br><?= e($mesaj) ?></div>
      <div class="alert alert-warn">
        <strong>Güvenlik:</strong> Sunucudan <code>install.php</code> dosyasını silin veya yeniden adlandırın.
      </div>
      <a href="index.php" class="btn btn-primary btn-block">Giriş Sayfasına Git</a>
    <?php else: ?>

      <?php if ($hata): ?>
        <div class="alert alert-error"><strong>Hata:</strong> <?= e($hata) ?></div>
      <?php endif; ?>

      <h1>Sisteme Hoş Geldiniz</h1>
      <p class="muted">Veritabanı tablolarını oluşturacak ve ilk yönetici hesabınızı açacağız.</p>

      <div class="alert alert-info">
        <strong>config.php içinde ayarlamanız gerekenler:</strong><br>
        <small>DB_HOST · DB_NAME · DB_USER · DB_PASS · SITE_URL · APP_KEY</small>
      </div>

      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="islem" value="kurulum">

        <label><span>Kullanıcı Adı</span>
          <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? 'admin') ?>" pattern="[a-zA-Z0-9._\-]{3,32}"></label>

        <label><span>Ad Soyad</span>
          <input type="text" name="ad_soyad" required value="<?= e($_POST['ad_soyad'] ?? '') ?>"></label>

        <label><span>E-posta</span>
          <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>

        <label><span>Şifre (en az 6 karakter)</span>
          <input type="password" name="sifre" required minlength="6"></label>

        <label><span>Şifre (Tekrar)</span>
          <input type="password" name="sifre2" required minlength="6"></label>

        <button type="submit" class="btn btn-primary btn-block">Kurulumu Başlat</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
