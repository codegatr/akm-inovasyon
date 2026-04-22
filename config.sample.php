<?php
/**
 * AKM İnovasyon - Sistem Yapılandırması
 *
 * Bu dosya güncellemelerle değiştirilmez. İlk kurulumda
 * config.sample.php'yi kopyalayıp config.php olarak kaydedin
 * ve aşağıdaki değerleri kendi sunucunuza göre düzenleyin.
 */

// --- Veritabanı ---
define('DB_HOST',    'localhost');
define('DB_NAME',    'veritabani_adi');
define('DB_USER',    'kullanici_adi');
define('DB_PASS',    'sifre');
define('DB_CHARSET', 'utf8mb4');
define('DB_PREFIX',  'akm_');

// --- Site ---
define('SITE_URL',   'https://akm.tekcanmetal.com');
define('SITE_NAME',  'AKM İnovasyon Dış Ticaret');
define('TIMEZONE',   'Europe/Istanbul');

// --- Güvenlik ---
// 64 karakter rastgele üret: bin2hex(random_bytes(32))
define('APP_KEY',    'DEGISTIRIN_buraya_rastgele_64_karakter_koyun_mutlaka_guvenli_olsun');
define('CSRF_NAME',  'akm_csrf');

// --- Oturum ---
define('SESSION_LIFETIME', 7200); // saniye (2 saat)
define('SESSION_NAME',     'AKMSESSID');

// --- Güncelleme Sistemi ---
define('UPDATE_REPO',        'codegatr/akm-inovasyon');
define('UPDATE_CHECK_URL',   'https://api.github.com/repos/' . UPDATE_REPO . '/releases/latest');
define('UPDATE_TOKEN',       ''); // Private repo için GitHub Personal Access Token (boş bırakılabilir)
define('CURRENT_VERSION',    '1.0.7');

// --- Debug ---
define('DEBUG', false);

// --- Yol ---
define('ROOT_PATH', __DIR__);
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Hata gösterme
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

date_default_timezone_set(TIMEZONE);
