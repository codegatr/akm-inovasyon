<?php
/**
 * AKM İnovasyon - Giriş Ekranı
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth_start_session();

// İlk kurulum kontrolü
try {
    $userCount = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('users'))->fetchColumn();
} catch (Throwable $e) {
    // Tablolar yok -> install.php'ye yönlendir
    if (is_file(__DIR__ . '/install.php')) {
        redirect('install.php');
    }
    die('Kurulum tamamlanmamış. Lütfen yöneticinize başvurun.');
}
if ($userCount === 0 && is_file(__DIR__ . '/install.php')) {
    redirect('install.php');
}

if (is_logged_in()) {
    redirect('yonetim.php');
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $u = trim((string)post('username', ''));
    $p = (string)post('password', '');
    if ($u === '' || $p === '') {
        $err = 'Kullanıcı adı ve şifre zorunludur.';
    } else {
        $res = auth_login($u, $p);
        if ($res['ok']) {
            $redir = $_GET['r'] ?? 'yonetim.php';
            if (!preg_match('#^(/|yonetim\.php)#', $redir)) $redir = 'yonetim.php';
            redirect($redir);
        }
        $err = $res['msg'];
        log_yaz('giris_basarisiz', 'auth', null, 'Kullanıcı: ' . $u);
    }
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Giriş · <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= e(CURRENT_VERSION) ?>">
<meta name="robots" content="noindex,nofollow">
</head>
<body class="page-login">
<div class="login-wrap">
  <div class="login-card">
    <div class="login-brand">
      <div class="brand-mark">AKM</div>
      <div class="brand-sub">İnovasyon Dış Ticaret</div>
    </div>
    <h1>Yönetim Paneli</h1>
    <p class="muted">Devam etmek için giriş yapın.</p>

    <?php if ($err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <?= csrf_field() ?>
      <label>
        <span>Kullanıcı Adı veya E-posta</span>
        <input type="text" name="username" required autofocus value="<?= e(post('username', '')) ?>">
      </label>
      <label>
        <span>Şifre</span>
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="btn btn-primary btn-block">Giriş Yap</button>
    </form>

    <div class="login-foot">
      <small>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> · v<?= e(CURRENT_VERSION) ?></small>
    </div>
  </div>
</div>
</body>
</html>
