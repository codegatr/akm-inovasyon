<?php if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); } ?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ? e($page_title) . ' · ' : '' ?><?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= e(CURRENT_VERSION) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="robots" content="noindex,nofollow">
</head>
<body class="page-admin">
<aside class="sidebar" id="sidebar">
  <div class="sidebar-head">
    <a href="yonetim.php" class="sb-brand">
      <span class="sb-logo">AKM</span>
      <span class="sb-title">İnovasyon</span>
    </a>
    <button type="button" class="sb-toggle" id="sbToggle" aria-label="Menü">&times;</button>
  </div>

  <?php
    $nav = [
      ['grup' => 'Ana',      'items' => [
          ['ic' => '🏠', 'lbl' => 'Gösterge Paneli', 'href' => 'yonetim.php?sayfa=dashboard', 's' => 'dashboard'],
      ]],
      ['grup' => 'İş',       'items' => [
          ['ic' => '👥', 'lbl' => 'Cari Kartları',    'href' => 'yonetim.php?sayfa=cari',    's' => ['cari','cari_form']],
          ['ic' => '📋', 'lbl' => 'Proforma Teklifler','href' => 'yonetim.php?sayfa=teklif',  's' => ['teklif','teklif_form','teklif_goruntule','teklif_gonder']],
      ]],
    ];
    if (is_admin()) {
      $nav[] = ['grup' => 'Yönetim', 'items' => [
          ['ic' => '👤', 'lbl' => 'Kullanıcılar',   'href' => 'yonetim.php?sayfa=kullanici','s' => ['kullanici','kullanici_form']],
          ['ic' => '🏦', 'lbl' => 'Banka Hesapları','href' => 'yonetim.php?sayfa=banka',    's' => ['banka','banka_form']],
          ['ic' => '⚙️', 'lbl' => 'Ayarlar',        'href' => 'yonetim.php?sayfa=ayarlar',  's' => 'ayarlar'],
          ['ic' => '📜', 'lbl' => 'İşlem Günlüğü',  'href' => 'yonetim.php?sayfa=log',      's' => 'log'],
          ['ic' => '🔄', 'lbl' => 'Güncelleme',      'href' => 'yonetim.php?sayfa=guncelleme','s' => 'guncelleme'],
      ]];
    }

    $aktif = $sayfa;
    foreach ($nav as $g):
  ?>
    <div class="sb-group"><?= e($g['grup']) ?></div>
    <nav class="sb-nav">
    <?php foreach ($g['items'] as $it):
        $s = is_array($it['s']) ? $it['s'] : [$it['s']];
        $cls = in_array($aktif, $s, true) ? ' active' : '';
    ?>
      <a href="<?= e($it['href']) ?>" class="sb-link<?= $cls ?>">
        <span class="sb-ic"><?= $it['ic'] ?></span>
        <span class="sb-lb"><?= e($it['lbl']) ?></span>
      </a>
    <?php endforeach; ?>
    </nav>
  <?php endforeach; ?>

  <div class="sb-foot">
    <div class="sb-user">
      <div class="sb-avatar"><?= e(mb_substr(current_user()['ad_soyad'] ?? 'U', 0, 1, 'UTF-8')) ?></div>
      <div class="sb-user-info">
        <div class="sb-name"><?= e(current_user()['ad_soyad'] ?? '') ?></div>
        <div class="sb-role"><?= is_admin() ? 'Yönetici' : 'Kullanıcı' ?></div>
      </div>
    </div>
    <a href="logout.php" class="sb-logout" title="Çıkış">⎋</a>
  </div>
</aside>

<main class="main">
  <header class="topbar">
    <button type="button" class="burger" id="burger" aria-label="Menüyü Aç">
      <span></span><span></span><span></span>
    </button>
    <div class="topbar-title"><?= e($page_title ?: 'Gösterge Paneli') ?></div>
    <div class="topbar-right">
      <span class="tb-ver">v<?= e(CURRENT_VERSION) ?></span>
    </div>
  </header>

  <div class="content">
    <?= flash_render() ?>
