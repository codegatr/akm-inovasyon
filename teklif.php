<?php
/**
 * Müşteriler için Herkese Açık Teklif Görüntüleme
 * URL: /teklif.php?tk=<view_token>
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$tk = trim((string)($_GET['tk'] ?? ''));
if (!preg_match('/^[a-f0-9]{48}$/', $tk)) {
    http_response_code(404);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center"><h1>Geçersiz Bağlantı</h1><p>Bu teklif bağlantısı geçerli değil.</p></body></html>');
}

$st = db()->prepare('SELECT t.*, c.firma_adi, c.yetkili_adi, c.email AS cari_email, c.telefon AS cari_telefon,
                            c.adres AS cari_adres, c.ulke AS cari_ulke, c.sehir AS cari_sehir,
                            c.vkn_tckn AS cari_vkn, c.vergi_dairesi AS cari_vd
                     FROM ' . tbl('teklif') . ' t
                     LEFT JOIN ' . tbl('cari') . ' c ON c.id = t.cari_id
                     WHERE t.view_token = ? LIMIT 1');
$st->execute([$tk]);
$t = $st->fetch();

if (!$t) {
    http_response_code(404);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;text-align:center"><h1>Teklif Bulunamadı</h1></body></html>');
}

$st = db()->prepare('SELECT * FROM ' . tbl('teklif_kalem') . ' WHERE teklif_id = ? ORDER BY sira, id');
$st->execute([$t['id']]);
$kalemler = $st->fetchAll();

// İlk görüntülemeyi kaydet
if (!$t['gorulme_tarihi']) {
    db()->prepare('UPDATE ' . tbl('teklif') . ' SET gorulme_tarihi = NOW(), durum = IF(durum = "gonderildi", "goruldu", durum) WHERE id = ?')
        ->execute([$t['id']]);
    log_yaz('teklif_goruldu', 'teklif', (int)$t['id'], 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

$pb = $t['para_birimi'];
$hasIsk = false;
foreach ($kalemler as $kk) { if ((float)$kk['iskonto_orani'] > 0) { $hasIsk = true; break; } }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teklif <?= e($t['teklif_no']) ?> · <?= e(ayar_get('sirket_adi', SITE_NAME)) ?></title>
<link rel="stylesheet" href="assets/css/app.css?v=<?= e(CURRENT_VERSION) ?>">
<meta name="robots" content="noindex,nofollow">
</head>
<body style="background:#f3f5f9; padding: 20px 0">

<div class="teklif-doc">
  <div class="teklif-head">
    <div class="flex">
      <?php $logo = sirket_logo(); ?>
      <?php if ($logo): ?>
        <div class="logo-img"><img src="<?= e($logo['url']) ?>" alt="Logo"></div>
      <?php else: ?>
        <div class="logo">AKM</div>
      <?php endif; ?>
      <div class="firma">
        <div class="ad"><?= e(ayar_get('sirket_adi', SITE_NAME)) ?></div>
        <div class="detay">
          <?= e(ayar_get('sirket_adres', '')) ?><br>
          <?php if (ayar_get('sirket_telefon')): ?>Tel: <?= e(ayar_get('sirket_telefon')) ?> · <?php endif; ?>
          <?php if (ayar_get('sirket_email')):   ?><?= e(ayar_get('sirket_email')) ?><?php endif; ?><br>
          <?php if (ayar_get('sirket_web')):     ?><?= e(ayar_get('sirket_web')) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="teklif-no">
      <div class="lb">Proforma Teklif</div>
      <div class="no"><?= e($t['teklif_no']) ?></div>
      <div class="lb mt-1">Tarih: <?= fmt_date($t['tarih']) ?></div>
      <?php if ($t['gecerlilik_tarihi']): ?>
        <div class="lb">Geçerlilik: <?= fmt_date($t['gecerlilik_tarihi']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="teklif-meta">
    <div class="box">
      <h4>Sayın Müşterimiz</h4>
      <div>
        <strong><?= e($t['firma_adi']) ?></strong><br>
        <?php if ($t['yetkili_adi']): ?>İlgili: <?= e($t['yetkili_adi']) ?><br><?php endif; ?>
        <?php if ($t['cari_adres']):  ?><?= nl2br(e($t['cari_adres'])) ?><br><?php endif; ?>
        <?= e(trim(($t['cari_sehir'] ?? '') . ' ' . ($t['cari_ulke'] ?? ''))) ?>
      </div>
    </div>
    <div class="box">
      <h4>Teklif Bilgileri</h4>
      <div>
        Para Birimi: <strong><?= e($pb) ?></strong><br>
        Ödeme: <strong><?= e($t['odeme_sekli'] ?: '-') ?></strong><br>
        Teslim: <strong><?= e($t['teslim_sekli'] ?: '-') ?></strong>
      </div>
    </div>
  </div>

  <table class="teklif-kalemler">
    <thead>
      <tr>
        <th style="width:30px">#</th>
        <th>Ürün / Hizmet</th>
        <th class="num">Miktar</th>
        <th>Birim</th>
        <th class="num">Birim Fiyat</th>
        <?php if ($hasIsk): ?><th class="num">İsk.%</th><?php endif; ?>
        <th class="num">KDV%</th>
        <th class="num">Tutar</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($kalemler as $i => $k): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td>
          <strong><?= e($k['urun_adi']) ?></strong>
          <?php if (trim($k['aciklama'] ?? '') !== ''): ?>
            <br><span style="color:var(--text-2); font-size:12px"><?= nl2br(e($k['aciklama'])) ?></span>
          <?php endif; ?>
        </td>
        <td class="num"><?= number_format((float)$k['miktar'], 2, ',', '.') ?></td>
        <td><?= e($k['birim']) ?></td>
        <td class="num"><?= number_format((float)$k['birim_fiyat'], 2, ',', '.') ?></td>
        <?php if ($hasIsk): ?><td class="num">%<?= number_format((float)$k['iskonto_orani'], 2, ',', '.') ?></td><?php endif; ?>
        <td class="num">%<?= number_format((float)$k['kdv_orani'], 0, ',', '.') ?></td>
        <td class="num"><?= e(fmt_money((float)$k['satir_toplam'], $pb)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="teklif-sum">
    <table>
      <tr><td>Ara Toplam:</td><td class="num"><?= e(fmt_money((float)$t['ara_toplam'], $pb)) ?></td></tr>
      <?php if ((float)$t['iskonto_tutari'] > 0): ?>
        <tr><td>İskonto:</td><td class="num">- <?= e(fmt_money((float)$t['iskonto_tutari'], $pb)) ?></td></tr>
      <?php endif; ?>
      <?php $kdvBD = kdv_dagilimi($kalemler, (float)$t['iskonto_orani']); ?>
      <?php if (count($kdvBD) > 1): ?>
        <?php foreach ($kdvBD as $bd): ?>
          <tr class="kdv-satir">
            <td>KDV %<?= number_format($bd['orani'], ($bd['orani'] == (int)$bd['orani'] ? 0 : 2), ',', '.') ?>:</td>
            <td class="num"><?= e(fmt_money($bd['tutar'], $pb)) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr><td><strong>Toplam KDV:</strong></td>
            <td class="num"><strong><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></strong></td></tr>
      <?php else: ?>
        <tr>
          <td>KDV<?php if (count($kdvBD) === 1): ?> %<?= number_format($kdvBD[0]['orani'], ($kdvBD[0]['orani'] == (int)$kdvBD[0]['orani'] ? 0 : 2), ',', '.') ?><?php endif; ?>:</td>
          <td class="num"><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></td>
        </tr>
      <?php endif; ?>
      <tr class="grand"><td>GENEL TOPLAM:</td><td class="num"><?= e(fmt_money((float)$t['genel_toplam'], $pb)) ?></td></tr>
    </table>
  </div>

  <?php if (trim($t['notlar'] ?? '') !== ''): ?>
    <div class="teklif-notlar">
      <strong>Notlar:</strong><br>
      <?= nl2br(e($t['notlar'])) ?>
    </div>
  <?php endif; ?>

  <?php if (trim($t['sartlar'] ?? '') !== ''): ?>
    <div class="teklif-notlar" style="border-left-color: var(--akm-primary)">
      <strong>Genel Şartlar:</strong><br>
      <?= nl2br(e($t['sartlar'])) ?>
    </div>
  <?php endif; ?>

  <?php $bankalar = banka_hesaplari($pb); ?>
  <?php if (!empty($bankalar)): ?>
    <div class="teklif-banka">
      <h4>🏦 Banka Hesap Bilgileri (<?= e($pb) ?>)</h4>
      <?php foreach ($bankalar as $b): ?>
        <div class="banka-row">
          <div class="banka-satir">
            <strong><?= e($b['banka_adi']) ?></strong>
            <?php if ($b['sube_adi']): ?> · <?= e($b['sube_adi']) ?><?php endif; ?>
            <?php if ($b['sube_kodu']): ?> <span class="muted">(<?= e($b['sube_kodu']) ?>)</span><?php endif; ?>
          </div>
          <div class="banka-satir"><span class="muted">Hesap Sahibi:</span> <?= e($b['hesap_sahibi']) ?></div>
          <?php if ($b['iban']): ?>
            <div class="banka-satir"><span class="muted">IBAN:</span> <code><?= e(iban_format($b['iban'])) ?></code></div>
          <?php endif; ?>
          <?php if ($b['swift']): ?>
            <div class="banka-satir"><span class="muted">SWIFT:</span> <code><?= e($b['swift']) ?></code></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="teklif-foot">
    Bu teklif <?= e(ayar_get('sirket_adi', SITE_NAME)) ?> tarafından düzenlenmiştir.
    <?php if (ayar_get('sirket_web')): ?>· <?= e(ayar_get('sirket_web')) ?><?php endif; ?>
    <div style="margin-top:10px">
      <button onclick="window.print()" class="btn btn-primary btn-sm no-print">🖨️ Yazdır / PDF</button>
    </div>
  </div>
</div>
</body>
</html>
