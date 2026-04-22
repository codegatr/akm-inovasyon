<?php
/**
 * AKM İnovasyon - Yönetim Paneli Dispatcher
 * Modüller /modules/ altında; ?sayfa=XXX ile yönlendirilir.
 * Kural: sayfa adı sadece a-z 0-9 _ karakterleri içerebilir.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/migration.php';

auth_start_session();
require_login();

// Şema otomatik migration (admin her girişte bir kez kontrol eder)
if (is_admin()) {
    auto_migrate();
}

$allowed_pages = [
    'dashboard',
    'cari', 'cari_form',
    'teklif', 'teklif_form', 'teklif_goruntule', 'teklif_pdf', 'teklif_gonder',
    'kullanici', 'kullanici_form',
    'banka', 'banka_form',
    'ayarlar', 'log', 'guncelleme',
    'kur_api',
];

$sayfa = (string)get_param('sayfa', 'dashboard');
if (!preg_match('/^[a-z0-9_]+$/', $sayfa) || !in_array($sayfa, $allowed_pages, true)) {
    $sayfa = 'dashboard';
}

$module_file = __DIR__ . '/modules/' . $sayfa . '.php';
if (!is_file($module_file)) {
    $sayfa = 'dashboard';
    $module_file = __DIR__ . '/modules/dashboard.php';
}

// Bazı modüller admin yetkisi ister
$admin_only = ['kullanici', 'kullanici_form', 'banka', 'banka_form', 'ayarlar', 'log', 'guncelleme'];
if (in_array($sayfa, $admin_only, true)) {
    require_admin();
}

// Bazı modüller kendi başına HTML üretir (PDF, mail gönderim AJAX vs)
$raw_pages = ['teklif_pdf', 'kur_api'];
if (in_array($sayfa, $raw_pages, true)) {
    require $module_file;
    exit;
}

// --- Layout ---
$page_title = '';
ob_start();
require $module_file;
$page_content = ob_get_clean();

require __DIR__ . '/includes/header.php';
echo $page_content;
require __DIR__ . '/includes/footer.php';
