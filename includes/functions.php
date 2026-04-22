<?php
/**
 * Ortak yardımcı fonksiyonlar
 */

if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

// ---------------------------------------------------------------------
// Güvenlik
// ---------------------------------------------------------------------
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION[CSRF_NAME])) {
        $_SESSION[CSRF_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_NAME];
}

function csrf_field(): string {
    return '<input type="hidden" name="' . CSRF_NAME . '" value="' . csrf_token() . '">';
}

function csrf_check(): bool {
    $token = $_POST[CSRF_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($_SESSION[CSRF_NAME]) && hash_equals($_SESSION[CSRF_NAME], (string)$token);
}

function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
        http_response_code(419);
        die('Oturum süresi doldu veya geçersiz istek. Sayfayı yenileyin.');
    }
}

// ---------------------------------------------------------------------
// Girdi işleme
// ---------------------------------------------------------------------
function post(string $key, $default = null) {
    return $_POST[$key] ?? $default;
}

function get_param(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

function int_param($v, int $default = 0): int {
    return is_numeric($v) ? (int)$v : $default;
}

function dec_param($v, float $default = 0.0): float {
    if (is_string($v)) {
        $v = str_replace(',', '.', trim($v));
    }
    return is_numeric($v) ? (float)$v : $default;
}

// ---------------------------------------------------------------------
// Yönlendirme / Mesaj
// ---------------------------------------------------------------------
function redirect(string $url): void {
    if (!preg_match('#^https?://#i', $url)) {
        $url = SITE_URL . '/' . ltrim($url, '/');
    }
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_render(): string {
    if (empty($_SESSION['flash'])) return '';
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = match ($f['type']) {
            'success' => 'alert-success',
            'error'   => 'alert-error',
            'warn'    => 'alert-warn',
            default   => 'alert-info',
        };
        $out .= '<div class="alert ' . $cls . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

// ---------------------------------------------------------------------
// Biçimlendirme
// ---------------------------------------------------------------------
function fmt_money(float $n, string $pb = 'TRY'): string {
    $sym = match ($pb) {
        'USD' => '$',
        'EUR' => '€',
        default => '₺',
    };
    return $sym . number_format($n, 2, ',', '.');
}

function fmt_date(?string $d): string {
    if (!$d || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '-';
    $t = strtotime($d);
    if (!$t) return '-';
    return date('d.m.Y', $t);
}

function fmt_datetime(?string $d): string {
    if (!$d) return '-';
    $t = strtotime($d);
    if (!$t) return '-';
    return date('d.m.Y H:i', $t);
}

function tr_durum(string $d): string {
    return match ($d) {
        'taslak'     => 'Taslak',
        'gonderildi' => 'Gönderildi',
        'goruldu'    => 'Görüldü',
        'onaylandi'  => 'Onaylandı',
        'reddedildi' => 'Reddedildi',
        'iptal'      => 'İptal',
        default      => $d,
    };
}

function durum_rozet(string $d): string {
    $map = [
        'taslak'     => ['Taslak', 'badge-gray'],
        'gonderildi' => ['Gönderildi', 'badge-blue'],
        'goruldu'    => ['Görüldü', 'badge-teal'],
        'onaylandi'  => ['Onaylandı', 'badge-green'],
        'reddedildi' => ['Reddedildi', 'badge-red'],
        'iptal'      => ['İptal', 'badge-gray'],
    ];
    [$t, $c] = $map[$d] ?? [$d, 'badge-gray'];
    return '<span class="badge ' . $c . '">' . e($t) . '</span>';
}

// ---------------------------------------------------------------------
// Teklif no üretimi  (AKM-2026-0001)
// ---------------------------------------------------------------------
function generate_teklif_no(): string {
    $p   = ayar_get('teklif_prefix', 'AKM');
    $yil = ayar_get('teklif_onek_yil', '1') === '1' ? date('Y') : '';
    $sayac = (int)ayar_get('teklif_sayac', '1');

    // Kullanılan numaraları atla
    while (true) {
        $no = $p . '-' . ($yil ? $yil . '-' : '') . str_pad((string)$sayac, 4, '0', STR_PAD_LEFT);
        $st = db()->prepare('SELECT 1 FROM ' . tbl('teklif') . ' WHERE teklif_no = ?');
        $st->execute([$no]);
        if (!$st->fetchColumn()) break;
        $sayac++;
    }
    ayar_set('teklif_sayac', (string)($sayac + 1));
    return $no;
}

// ---------------------------------------------------------------------
// Ayarlar anahtar-değer
// ---------------------------------------------------------------------
function ayar_get(string $key, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT anahtar, deger FROM ' . tbl('ayarlar'))->fetchAll();
            foreach ($rows as $r) $cache[$r['anahtar']] = $r['deger'];
        } catch (Throwable $e) {}
    }
    return $cache[$key] ?? $default;
}

function ayar_set(string $key, string $val): void {
    $st = db()->prepare('INSERT INTO ' . tbl('ayarlar') . ' (anahtar, deger, guncelleme_tarihi) VALUES (?, ?, NOW())
                         ON DUPLICATE KEY UPDATE deger = VALUES(deger), guncelleme_tarihi = NOW()');
    $st->execute([$key, $val]);
}

// ---------------------------------------------------------------------
// Log
// ---------------------------------------------------------------------
function log_yaz(string $islem, string $modul = '', ?int $kayit_id = null, string $aciklama = ''): void {
    try {
        $u = $_SESSION['user'] ?? null;
        $st = db()->prepare('INSERT INTO ' . tbl('log') . '
            (kullanici_id, kullanici_adi, islem, modul, kayit_id, aciklama, ip, user_agent, tarih)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $st->execute([
            $u['id'] ?? null,
            $u['username'] ?? null,
            $islem,
            $modul,
            $kayit_id,
            $aciklama,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        // Log hatası ana akışı kesmesin
    }
}

// ---------------------------------------------------------------------
// Cari kodu otomatik
// ---------------------------------------------------------------------
function generate_cari_kodu(): string {
    $prefix = 'C';
    $max = db()->query("SELECT MAX(CAST(SUBSTRING(cari_kodu, 2) AS UNSIGNED)) FROM " . tbl('cari') . " WHERE cari_kodu LIKE 'C%'")->fetchColumn();
    $n = ((int)$max) + 1;
    return $prefix . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

// ---------------------------------------------------------------------
// Teklif hesaplama
// ---------------------------------------------------------------------
function hesapla_teklif(array $kalemler, float $iskonto_orani = 0.0, bool $kdv_dahil = false): array {
    $ara = 0.0;
    $kdvT = 0.0;
    $satirlar = [];

    foreach ($kalemler as $k) {
        $miktar      = dec_param($k['miktar'] ?? 0);
        $birim_fiyat = dec_param($k['birim_fiyat'] ?? 0);
        $iskonto_k   = dec_param($k['iskonto_orani'] ?? 0);
        $kdv_orani   = dec_param($k['kdv_orani'] ?? 0);

        $brut = $miktar * $birim_fiyat;
        $isk  = $brut * $iskonto_k / 100;
        $net  = $brut - $isk;
        $kdv  = $net * $kdv_orani / 100;

        $ara  += $net;
        $kdvT += $kdv;

        $satirlar[] = array_merge($k, [
            'satir_toplam' => round($net, 2),
            '_kdv_tutar'   => round($kdv, 2),
        ]);
    }

    $iskontoT = $ara * $iskonto_orani / 100;
    $ara_sonrasi = $ara - $iskontoT;
    // Kalem iskonto orani ile genel iskonto arasinda dogru oran icin KDV'yi de iskonto et
    if ($iskonto_orani > 0) {
        $kdvT = $kdvT * (1 - $iskonto_orani / 100);
    }

    $genel = round($ara_sonrasi + $kdvT, 2);

    return [
        'ara_toplam'     => round($ara, 2),
        'iskonto_tutari' => round($iskontoT, 2),
        'kdv_toplam'    => round($kdvT, 2),
        'genel_toplam'   => $genel,
        'satirlar'       => $satirlar,
    ];
}

// ---------------------------------------------------------------------
// KDV Oranlarına Göre Dağılım
// Kalemlerdeki KDV'yi oran bazında gruplar.
// Genel iskonto uygulanmışsa matrah ve KDV orantılı düşer.
// ---------------------------------------------------------------------
function kdv_dagilimi(array $kalemler, float $genelIskontoOrani = 0): array {
    $bd = [];
    foreach ($kalemler as $k) {
        $rate      = (float)($k['kdv_orani'] ?? 0);
        $miktar    = (float)($k['miktar'] ?? 0);
        $fiyat     = (float)($k['birim_fiyat'] ?? 0);
        $kalemIsk  = (float)($k['iskonto_orani'] ?? 0);

        $netKalem  = $miktar * $fiyat * (1 - $kalemIsk / 100);
        $netKalem  = $netKalem * (1 - $genelIskontoOrani / 100);
        $kdvTutari = $netKalem * ($rate / 100);

        // Aynı oranda olanları birleştir
        $key = number_format($rate, 2, '.', '');
        if (!isset($bd[$key])) {
            $bd[$key] = ['orani' => $rate, 'matrah' => 0.0, 'tutar' => 0.0];
        }
        $bd[$key]['matrah'] += $netKalem;
        $bd[$key]['tutar']  += $kdvTutari;
    }
    // Sıfır KDV'yi at, oranına göre sırala
    $bd = array_filter($bd, fn($r) => $r['orani'] > 0);
    ksort($bd);
    // Yuvarla
    foreach ($bd as &$r) {
        $r['matrah'] = round($r['matrah'], 2);
        $r['tutar']  = round($r['tutar'], 2);
    }
    return array_values($bd);
}

// ---------------------------------------------------------------------
// Logo ve Banka Hesapları Yardımcıları
// ---------------------------------------------------------------------

/**
 * Yüklü logonun tam yolu ve URL'i. Yoksa null.
 * @return array{path:string,url:string}|null
 */
function sirket_logo(): ?array {
    $rel = ayar_get('sirket_logo', '');
    if (!$rel) return null;
    $full = ROOT_PATH . '/' . $rel;
    if (!is_file($full)) return null;
    return ['path' => $full, 'url' => $rel];
}

/**
 * Aktif banka hesaplarını para birimine göre döner.
 * @param string|null $paraBirimi TRY/USD/EUR veya null = hepsi
 * @return array
 */
function banka_hesaplari(?string $paraBirimi = null): array {
    try {
        if ($paraBirimi) {
            $st = db()->prepare('SELECT * FROM ' . tbl('banka_hesap') . '
                WHERE aktif = 1 AND para_birimi = ?
                ORDER BY sira, id');
            $st->execute([$paraBirimi]);
        } else {
            $st = db()->query('SELECT * FROM ' . tbl('banka_hesap') . '
                WHERE aktif = 1
                ORDER BY para_birimi, sira, id');
        }
        return $st->fetchAll();
    } catch (Throwable $e) {
        // Tablo henüz yoksa boş dön (v1.0.2 -> v1.0.3 geçişinde)
        return [];
    }
}

/**
 * IBAN'ı 4'erli gruplar halinde biçimler: TR00 0000 0000 0000 0000 0000 00
 */
function iban_format(string $iban): string {
    $iban = strtoupper(preg_replace('/\s+/', '', $iban));
    if ($iban === '') return '';
    return trim(chunk_split($iban, 4, ' '));
}

// ---------------------------------------------------------------------
// Sayfalama yardımcısı
// ---------------------------------------------------------------------
function pagination(int $total, int $page, int $per_page, string $base_url): string {
    $pages = max(1, (int)ceil($total / $per_page));
    if ($pages <= 1) return '';
    $out = '<nav class="pagination">';
    $start = max(1, $page - 2);
    $end   = min($pages, $page + 2);
    if ($page > 1) {
        $out .= '<a href="' . e($base_url) . '&p=' . ($page - 1) . '">&lsaquo;</a>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $cls = $i === $page ? ' active' : '';
        $out .= '<a class="' . $cls . '" href="' . e($base_url) . '&p=' . $i . '">' . $i . '</a>';
    }
    if ($page < $pages) {
        $out .= '<a href="' . e($base_url) . '&p=' . ($page + 1) . '">&rsaquo;</a>';
    }
    $out .= '</nav>';
    return $out;
}
