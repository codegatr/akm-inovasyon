<?php
/**
 * TCMB Döviz Kuru Entegrasyonu
 *
 * Kaynak: https://www.tcmb.gov.tr/kurlar/today.xml
 * Frekans: Her iş günü saat ~15:30'da güncellenir.
 * Hafta sonu / tatil: Son iş gününün kurları döner.
 *
 * Kullanılan alan: BanknoteSelling (Efektif Satış)
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

/**
 * TCMB XML'ini indirir ve USD/EUR banknote selling (efektif satış) kurlarını döner.
 *
 * @return array ['ok' => bool, 'msg' => string, 'tarih' => string, 'kurlar' => [USD, EUR]]
 */
function tcmb_kurlari_cek(): array {
    $url = 'https://www.tcmb.gov.tr/kurlar/today.xml';
    $xml = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'AKM-Inovasyon/1.0 (https://akm.tekcanmetal.com)',
        ]);
        $xml  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($xml === false || $code !== 200) {
            return ['ok' => false, 'msg' => 'TCMB HTTP ' . $code . ($err ? ' ' . $err : '')];
        }
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => 10, 'user_agent' => 'AKM-Inovasyon/1.0'],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $xml = @file_get_contents($url, false, $ctx);
        if ($xml === false) return ['ok' => false, 'msg' => 'TCMB bağlantısı kurulamadı.'];
    }

    libxml_use_internal_errors(true);
    $doc = @simplexml_load_string($xml);
    if (!$doc) {
        return ['ok' => false, 'msg' => 'TCMB yanıtı geçerli XML değil.'];
    }

    $tarih = (string)($doc['Tarih'] ?? '');
    $kurlar = [];
    foreach ($doc->Currency as $c) {
        $kod  = (string)($c['CurrencyCode'] ?? '');
        $unit = (float)($c->Unit ?? 1);
        if ($unit <= 0) $unit = 1;
        $banknoteSelling = (float)($c->BanknoteSelling ?? 0);
        // Bazı egzotik kurlarda BanknoteSelling 0 dönebilir, ForexSelling'e fallback
        if ($banknoteSelling <= 0) $banknoteSelling = (float)($c->ForexSelling ?? 0);

        if (in_array($kod, ['USD', 'EUR'], true) && $banknoteSelling > 0) {
            $kurlar[$kod] = round($banknoteSelling / $unit, 4);
        }
    }

    if (empty($kurlar)) {
        return ['ok' => false, 'msg' => 'TCMB yanıtında USD/EUR kuru bulunamadı.'];
    }

    return ['ok' => true, 'tarih' => $tarih, 'kurlar' => $kurlar];
}

/**
 * Kurları çekip ayarlar tablosuna kaydeder. Aynı gün içinde tekrar çekmez (cache).
 *
 * @param bool $force Cache'i atla, zorla çek
 * @return array sonuç bilgisi
 */
function tcmb_kur_guncelle(bool $force = false): array {
    $sonTarih = ayar_get('tcmb_son_tarih', '');
    $bugun    = date('d.m.Y');

    if (!$force && $sonTarih !== '') {
        $usd = (float)ayar_get('kur_usd_try', 0);
        $eur = (float)ayar_get('kur_eur_try', 0);
        // Aynı gün içinde zaten alınmışsa cache'den dön
        if ($usd > 0 && $eur > 0) {
            $lastFetch = ayar_get('tcmb_son_fetch', '');
            if ($lastFetch && substr($lastFetch, 0, 10) === date('Y-m-d')) {
                return [
                    'ok'       => true,
                    'cached'   => true,
                    'tarih'    => $sonTarih,
                    'kurlar'   => ['USD' => $usd, 'EUR' => $eur],
                    'msg'      => 'Cache: bugün zaten çekildi (' . $sonTarih . ')',
                ];
            }
        }
    }

    $res = tcmb_kurlari_cek();
    if (!$res['ok']) return $res;

    if (isset($res['kurlar']['USD'])) ayar_set('kur_usd_try', (string)$res['kurlar']['USD']);
    if (isset($res['kurlar']['EUR'])) ayar_set('kur_eur_try', (string)$res['kurlar']['EUR']);
    ayar_set('tcmb_son_tarih', $res['tarih']);
    ayar_set('tcmb_son_fetch', date('Y-m-d H:i:s'));
    ayar_set('kur_guncelleme', date('Y-m-d H:i:s') . ' (TCMB ' . $res['tarih'] . ')');
    ayar_set('kur_kaynak', 'TCMB Efektif Satış');

    $res['cached'] = false;
    $res['msg'] = 'Kurlar TCMB\'den güncellendi (Efektif Satış, ' . $res['tarih'] . ').';
    return $res;
}
