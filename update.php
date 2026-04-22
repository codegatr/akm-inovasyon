<?php
/**
 * AKM İnovasyon — Güncelleme Motoru
 *
 * Çalışma akışı (cminer.org / erp.codega.com.tr ile aynı desen):
 *  1. POST ile versiyon + zip_url alır
 *  2. ZIP'i tmp dizinine indirir
 *  3. Açar, root dizinini tespit eder (GitHub zipball kök klasörü)
 *  4. Korunan yollar dışındaki dosyaları kopyalar
 *  5. sql/install.sql varsa idempotent uygular
 *  6. config.php içindeki CURRENT_VERSION değerini günceller
 *  7. Log yazar
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

auth_start_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
require_csrf();

$versiyon = trim((string)post('versiyon', ''));
$zipUrl   = trim((string)post('zip_url', ''));

if ($versiyon === '' || $zipUrl === '') {
    die('<h1>Geçersiz parametreler</h1>');
}

@set_time_limit(300);
@ignore_user_abort(true);
header('Content-Type: text/html; charset=UTF-8');

// UI başlık
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Sistem Güncelleniyor...</title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= e(CURRENT_VERSION) ?>">
<style>
body { background: #f3f5f9; padding: 30px; font-family: -apple-system, Segoe UI, sans-serif; }
.upd { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 30px; }
.upd h1 { color: #1b3a6b; margin-top: 0; }
.log { background: #0f172a; color: #cbd5e1; padding: 16px; border-radius: 6px;
  font-family: 'SF Mono', Monaco, Consolas, monospace; font-size: 12.5px;
  line-height: 1.7; max-height: 500px; overflow-y: auto; }
.log .ok  { color: #86efac; }
.log .err { color: #fca5a5; }
.log .inf { color: #93c5fd; }
.log .wrn { color: #fcd34d; }
</style>
</head>
<body>
<div class="upd">
<h1>🔄 Sistem Güncelleniyor...</h1>
<p class="muted">v<?= e(CURRENT_VERSION) ?> → <strong><?= e($versiyon) ?></strong></p>
<div class="log" id="log"><?php
ob_implicit_flush(true);
if (ob_get_level() === 0) ob_start();

function step(string $type, string $msg): void {
    $map = ['ok' => '✓', 'err' => '✗', 'inf' => '→', 'wrn' => '⚠'];
    echo '<div class="' . $type . '">[' . date('H:i:s') . '] ' . ($map[$type] ?? '·') . ' ' . e($msg) . "</div>\n";
    @ob_flush(); @flush();
}

$hasError = false;
$tmpZip   = null;
$tmpDir   = null;

try {
    step('inf', 'Güncelleme başlatılıyor...');
    step('inf', 'Hedef sürüm: ' . $versiyon);

    // -----------------------------------------------------------------
    // 1) ZIP'i indir
    // -----------------------------------------------------------------
    $tmpZip = sys_get_temp_dir() . '/akm_update_' . uniqid() . '.zip';
    step('inf', 'İndiriliyor: ' . $zipUrl);

    $headers = [
        'Accept: application/octet-stream',
        'User-Agent: AKM-Inovasyon-Updater/1.0',
    ];
    if (defined('UPDATE_TOKEN') && UPDATE_TOKEN !== '') {
        $headers[] = 'Authorization: Bearer ' . UPDATE_TOKEN;
    }

    $fp = fopen($tmpZip, 'w+');
    if (!$fp) throw new RuntimeException('Geçici dosya oluşturulamadı.');

    $ch = curl_init($zipUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $success = curl_exec($ch);
    $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err     = curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$success || $code >= 400) {
        throw new RuntimeException('İndirme hatası (HTTP ' . $code . '): ' . $err);
    }
    $boyut = filesize($tmpZip);
    step('ok', 'İndirildi: ' . number_format($boyut) . ' bayt');

    // -----------------------------------------------------------------
    // 2) Aç
    // -----------------------------------------------------------------
    $tmpDir = sys_get_temp_dir() . '/akm_extract_' . uniqid();
    if (!mkdir($tmpDir, 0755, true)) throw new RuntimeException('Çıkarma dizini oluşturulamadı.');
    step('inf', 'ZIP açılıyor: ' . $tmpDir);

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive sınıfı bulunamadı. PHP zip eklentisi gerekli.');
    }
    $zip = new ZipArchive;
    if ($zip->open($tmpZip) !== true) throw new RuntimeException('ZIP açılamadı.');
    $zip->extractTo($tmpDir);
    $zip->close();
    step('ok', 'ZIP çıkarıldı.');

    // -----------------------------------------------------------------
    // 3) Kök dizini bul
    // -----------------------------------------------------------------
    $sourceDir = $tmpDir;
    $entries = array_values(array_filter(scandir($tmpDir), fn($x) => !in_array($x, ['.', '..'], true)));
    if (count($entries) === 1 && is_dir($tmpDir . '/' . $entries[0])) {
        $sourceDir = $tmpDir . '/' . $entries[0];
        step('inf', 'Kök dizin: ' . basename($sourceDir));
    }

    // -----------------------------------------------------------------
    // 4) Dosyaları kopyala
    // -----------------------------------------------------------------
    $protected = [
        'config.php',
        'uploads',
        'install.php',
        '.htaccess.local', // varsa özel .htaccess
    ];

    $kopyalanan = 0;
    $atlanan    = 0;

    $copy = function ($src, $dst) use (&$copy, &$kopyalanan, &$atlanan, $protected) {
        $d = opendir($src);
        while (($f = readdir($d)) !== false) {
            if ($f === '.' || $f === '..') continue;
            $s = $src . '/' . $f;
            $t = $dst . '/' . $f;
            // Root-level protected check
            $rel = ltrim(str_replace(ROOT_PATH, '', $t), '/');
            $topLevel = explode('/', $rel)[0];
            if (in_array($topLevel, $protected, true) && strpos($rel, '/') === false) {
                // root'taki korumalı dosya -> üstüne yazma (ama yoksa ekle)
                if (file_exists($t)) { $atlanan++; continue; }
            }
            if (in_array($topLevel, $protected, true) && strpos($rel, '/') !== false) {
                // korumalı klasör içindeki dosyalar -> dokunma
                $atlanan++; continue;
            }

            if (is_dir($s)) {
                if (!is_dir($t)) @mkdir($t, 0755, true);
                $copy($s, $t);
            } else {
                if (!is_dir(dirname($t))) @mkdir(dirname($t), 0755, true);
                if (@copy($s, $t)) {
                    $kopyalanan++;
                } else {
                    throw new RuntimeException('Kopyalama başarısız: ' . $rel);
                }
            }
        }
        closedir($d);
    };

    step('inf', 'Dosyalar kopyalanıyor...');
    $copy($sourceDir, ROOT_PATH);
    step('ok', $kopyalanan . ' dosya kopyalandı, ' . $atlanan . ' korunan dosya atlandı.');

    // -----------------------------------------------------------------
    // 5) SQL migration
    // -----------------------------------------------------------------
    $sqlFile = ROOT_PATH . '/sql/install.sql';
    if (is_file($sqlFile)) {
        step('inf', 'SQL şeması uygulanıyor (idempotent)...');
        $sql = file_get_contents($sqlFile);
        if ($sql) {
            // İfade başına çalıştır
            $clean = preg_replace('/--[^\n]*\n/', "\n", $sql);
            $ifadeler = array_filter(array_map('trim', explode(';', $clean)));
            $ok = 0;
            foreach ($ifadeler as $iq) {
                if ($iq === '' || str_starts_with($iq, '/*')) continue;
                try { db()->exec($iq); $ok++; }
                catch (Throwable $e) { step('wrn', 'SQL uyarı: ' . substr($e->getMessage(), 0, 200)); }
            }
            step('ok', $ok . ' SQL ifadesi uygulandı.');
        }
    }

    // -----------------------------------------------------------------
    // 6) config.php içinde CURRENT_VERSION güncelle
    // -----------------------------------------------------------------
    $configFile = ROOT_PATH . '/config.php';
    $yeniVer    = ltrim($versiyon, 'vV');
    if (is_file($configFile) && is_writable($configFile)) {
        $c = file_get_contents($configFile);
        $new = preg_replace(
            "/define\s*\(\s*'CURRENT_VERSION'\s*,\s*'[^']*'\s*\)/",
            "define('CURRENT_VERSION', '" . addslashes($yeniVer) . "')",
            $c, 1, $cnt
        );
        if ($cnt > 0 && $new !== null) {
            file_put_contents($configFile, $new);
            step('ok', 'config.php içindeki sürüm güncellendi: v' . $yeniVer);
        } else {
            step('wrn', 'config.php içinde CURRENT_VERSION satırı bulunamadı. Manuel güncelleyin.');
        }
    }

    // -----------------------------------------------------------------
    // 7) Temizlik
    // -----------------------------------------------------------------
    @unlink($tmpZip);
    // tmpDir'i recursive sil
    $rrmdir = function ($p) use (&$rrmdir) {
        if (!is_dir($p)) { @unlink($p); return; }
        foreach (scandir($p) as $f) {
            if ($f === '.' || $f === '..') continue;
            $rrmdir($p . '/' . $f);
        }
        @rmdir($p);
    };
    $rrmdir($tmpDir);
    step('ok', 'Geçici dosyalar temizlendi.');

    log_yaz('guncelleme', 'sistem', null, 'Güncellendi: v' . CURRENT_VERSION . ' → v' . $yeniVer);
    step('ok', '═══════════════════════════════════════════════════');
    step('ok', 'GÜNCELLEME TAMAMLANDI. Sürüm: v' . $yeniVer);
    step('ok', '═══════════════════════════════════════════════════');
} catch (Throwable $e) {
    $hasError = true;
    step('err', 'HATA: ' . $e->getMessage());
    if ($tmpZip && file_exists($tmpZip)) @unlink($tmpZip);
    if ($tmpDir && is_dir($tmpDir)) {
        $rrmdir = function ($p) use (&$rrmdir) {
            if (!is_dir($p)) { @unlink($p); return; }
            foreach (scandir($p) as $f) {
                if ($f === '.' || $f === '..') continue;
                $rrmdir($p . '/' . $f);
            }
            @rmdir($p);
        };
        $rrmdir($tmpDir);
    }
    log_yaz('guncelleme_hata', 'sistem', null, $e->getMessage());
}
?></div>

<div style="margin-top: 20px; text-align: center;">
<?php if ($hasError): ?>
  <a href="yonetim.php?sayfa=guncelleme" class="btn btn-ghost">Geri Dön</a>
<?php else: ?>
  <a href="yonetim.php" class="btn btn-primary">✓ Panele Git</a>
<?php endif; ?>
</div>

</div>
</body>
</html>
