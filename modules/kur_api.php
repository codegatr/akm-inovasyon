<?php
/**
 * Kur API
 * GET ?sayfa=kur_api[&pb=USD|EUR][&tcmb=1]
 * Dönüş: JSON { ok, kur, tarih, kaynak, guncelleme }
 *
 * tcmb=1 -> TCMB'den yeni çekme (gün içinde cache'li)
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once ROOT_PATH . '/includes/tcmb.php';

$pb   = strtoupper((string)get_param('pb', ''));
$tcmb = (int)get_param('tcmb', 0) === 1;

$response = ['ok' => false, 'msg' => 'Geçersiz istek.'];

try {
    if ($tcmb) {
        $res = tcmb_kur_guncelle(false);
        if (!$res['ok']) {
            $response = ['ok' => false, 'msg' => $res['msg']];
        } else {
            $response = [
                'ok'          => true,
                'cached'      => $res['cached'] ?? false,
                'tarih'       => $res['tarih'] ?? '',
                'kurlar'      => $res['kurlar'] ?? [],
                'kaynak'      => 'TCMB Efektif Satış',
                'guncelleme'  => ayar_get('kur_guncelleme', ''),
                'kur'         => ($pb && isset($res['kurlar'][$pb])) ? (float)$res['kurlar'][$pb] : null,
                'msg'         => $res['msg'] ?? '',
            ];
        }
    } else {
        // Sadece kayıtlı kuru döndür
        $usd = (float)ayar_get('kur_usd_try', 0);
        $eur = (float)ayar_get('kur_eur_try', 0);
        $response = [
            'ok'          => true,
            'kurlar'      => ['USD' => $usd, 'EUR' => $eur],
            'kur'         => $pb === 'USD' ? $usd : ($pb === 'EUR' ? $eur : null),
            'kaynak'      => ayar_get('kur_kaynak', ''),
            'tarih'       => ayar_get('tcmb_son_tarih', ''),
            'guncelleme'  => ayar_get('kur_guncelleme', ''),
        ];
    }
} catch (Throwable $e) {
    $response = ['ok' => false, 'msg' => 'Hata: ' . $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
