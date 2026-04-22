<?php
/**
 * Teklif PDF / Yazdırma Sayfası
 * Standalone HTML - browser print-to-PDF için optimize
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$id = int_param(get_param('id', 0));
if ($id <= 0) { http_response_code(404); die('Teklif bulunamadı.'); }

$st = db()->prepare('SELECT t.*, c.firma_adi, c.yetkili_adi, c.email AS cari_email, c.telefon AS cari_telefon,
                            c.adres AS cari_adres, c.ulke AS cari_ulke, c.sehir AS cari_sehir,
                            c.vkn_tckn AS cari_vkn, c.vergi_dairesi AS cari_vd, c.cari_kodu
                     FROM ' . tbl('teklif') . ' t
                     LEFT JOIN ' . tbl('cari') . ' c ON c.id = t.cari_id
                     WHERE t.id = ?');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) { http_response_code(404); die('Teklif bulunamadı.'); }

$st = db()->prepare('SELECT * FROM ' . tbl('teklif_kalem') . ' WHERE teklif_id = ? ORDER BY sira, id');
$st->execute([$id]);
$kalemler = $st->fetchAll();

$pb = $t['para_birimi'];
$hasIsk = false;
foreach ($kalemler as $kk) { if ((float)$kk['iskonto_orani'] > 0) { $hasIsk = true; break; } }
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Teklif <?= e($t['teklif_no']) ?></title>
<style>
@page { size: A4; margin: 14mm 12mm; }
* { box-sizing: border-box; }
body {
  margin: 0; padding: 20px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
  font-size: 11.5px; color: #1a202c; line-height: 1.5; background: #fff;
}
.wrap { max-width: 800px; margin: 0 auto; }
.head {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding-bottom: 14px; margin-bottom: 16px;
  border-bottom: 3px solid #1b3a6b;
}
.head .left { display: flex; align-items: center; }
.logo {
  font-size: 24px; font-weight: 800; letter-spacing: 3px; color: #1b3a6b;
  border-right: 3px solid #2ca69a; padding-right: 10px; margin-right: 12px;
}
.logo-img {
  display: flex; align-items: center; justify-content: center;
  max-height: 62px; max-width: 160px; margin-right: 14px;
  padding-right: 10px; border-right: 2px solid #2ca69a;
}
.logo-img img { max-height: 60px; max-width: 150px; object-fit: contain; }
.banka-blok {
  margin-top: 14px; padding: 10px 12px; background: #f8fafc;
  border-left: 3px solid #1b3a6b; border-radius: 4px; font-size: 10.5px;
}
.banka-blok h4 { margin: 0 0 6px; font-size: 10.5px; color: #1b3a6b;
  text-transform: uppercase; letter-spacing: .04em; }
.banka-blok .row { padding: 2px 0; line-height: 1.4; }
.banka-blok code { font-family: 'SF Mono', Consolas, monospace; color: #1b3a6b; }
.firma .ad { font-weight: 700; font-size: 14px; }
.firma .detay { font-size: 10.5px; color: #4a5568; line-height: 1.55; }
.head .right { text-align: right; }
.head .right .lb { color: #8895a7; font-size: 10px; }
.head .right .no { font-size: 18px; font-weight: 700; color: #1b3a6b; }
.meta {
  display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px;
}
.meta .box {
  background: #f8fafc; padding: 10px 12px; border-radius: 6px;
  border-left: 3px solid #2ca69a; font-size: 11px;
}
.meta .box h4 { margin: 0 0 4px; font-size: 10px; color: #4a5568;
  text-transform: uppercase; letter-spacing: .05em; }
table.kk {
  width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 11px;
}
table.kk th, table.kk td {
  padding: 6px 8px; border-bottom: 1px solid #eef0f4; vertical-align: top;
}
table.kk thead th {
  background: #1b3a6b; color: #fff; font-size: 10px;
  text-transform: uppercase; letter-spacing: .04em; font-weight: 600;
  text-align: left; border: 0;
}
table.kk thead th.num { text-align: right; }
table.kk td.num { text-align: right; font-variant-numeric: tabular-nums; }
table.kk tbody tr:nth-child(even) { background: #fafbfd; }
.sum { display: flex; justify-content: flex-end; margin-top: 6px; }
.sum table { border-collapse: collapse; min-width: 280px; }
.sum td { padding: 4px 10px; font-size: 11.5px; }
.sum tr.grand td {
  font-size: 14px; font-weight: 700; color: #1b3a6b;
  border-top: 2px solid #1b3a6b; padding-top: 7px;
}
.notlar {
  margin-top: 16px; padding: 10px 12px; background: #f8fafc;
  border-left: 3px solid #2ca69a; border-radius: 4px; font-size: 11px;
}
.foot {
  margin-top: 24px; padding-top: 10px; border-top: 1px solid #e4e7ec;
  text-align: center; font-size: 10px; color: #8895a7;
}
.actions { text-align: center; margin: 14px 0; }
.actions button {
  padding: 8px 18px; background: #2d5596; color: #fff; border: 0;
  border-radius: 5px; cursor: pointer; font-size: 13px; margin: 0 4px;
}
@media print { .actions { display: none; } body { padding: 0; } }
</style>
</head>
<body>
<div class="actions">
  <button onclick="window.print()">🖨️ Yazdır / PDF Olarak Kaydet</button>
  <button onclick="window.close()">Kapat</button>
</div>
<div class="wrap">
  <div class="head">
    <div class="left">
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
          <?php if (ayar_get('sirket_vkn')):     ?> · VKN: <?= e(ayar_get('sirket_vkn')) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="right">
      <div class="lb">PROFORMA TEKLİF</div>
      <div class="no"><?= e($t['teklif_no']) ?></div>
      <div class="lb" style="margin-top:6px">Tarih: <?= fmt_date($t['tarih']) ?></div>
      <?php if ($t['gecerlilik_tarihi']): ?>
        <div class="lb">Geçerlilik: <?= fmt_date($t['gecerlilik_tarihi']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="meta">
    <div class="box">
      <h4>Sayın Müşterimiz</h4>
      <strong><?= e($t['firma_adi']) ?></strong><br>
      <?php if ($t['yetkili_adi']): ?>İlgili: <?= e($t['yetkili_adi']) ?><br><?php endif; ?>
      <?php if ($t['cari_adres']):  ?><?= nl2br(e($t['cari_adres'])) ?><br><?php endif; ?>
      <?= e(trim(($t['cari_sehir'] ?? '') . ' ' . ($t['cari_ulke'] ?? ''))) ?><br>
      <?php if ($t['cari_telefon']): ?>Tel: <?= e($t['cari_telefon']) ?><br><?php endif; ?>
      <?php if ($t['cari_email']):   ?><?= e($t['cari_email']) ?><br><?php endif; ?>
      <?php if ($t['cari_vkn']):     ?>VKN: <?= e($t['cari_vkn']) ?><?php if ($t['cari_vd']): ?> · <?= e($t['cari_vd']) ?><?php endif; endif; ?>
    </div>
    <div class="box">
      <h4>Teklif Bilgileri</h4>
      Para Birimi: <strong><?= e($pb) ?></strong><br>
      <?php if ($t['doviz_kuru'] > 1 && $pb !== 'TRY'): ?>
        Kur: 1 <?= e($pb) ?> = <?= e(number_format((float)$t['doviz_kuru'], 4, ',', '.')) ?> TRY<br>
      <?php endif; ?>
      Ödeme: <strong><?= e($t['odeme_sekli'] ?: '-') ?></strong><br>
      Teslim: <strong><?= e($t['teslim_sekli'] ?: '-') ?></strong>
    </div>
  </div>

  <table class="kk">
    <thead>
      <tr>
        <th style="width:26px">#</th>
        <th>Ürün / Hizmet</th>
        <th class="num" style="width:70px">Miktar</th>
        <th style="width:52px">Birim</th>
        <th class="num" style="width:90px">B. Fiyat</th>
        <?php if ($hasIsk): ?><th class="num" style="width:58px">İsk.%</th><?php endif; ?>
        <th class="num" style="width:50px">KDV%</th>
        <th class="num" style="width:110px">Tutar</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($kalemler as $i => $k): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td>
          <strong><?= e($k['urun_adi']) ?></strong>
          <?php if (trim($k['aciklama'] ?? '') !== ''): ?>
            <br><span style="color:#4a5568; font-size:10.5px"><?= nl2br(e($k['aciklama'])) ?></span>
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

  <div class="sum">
    <table>
      <tr><td>Ara Toplam:</td><td class="num"><?= e(fmt_money((float)$t['ara_toplam'], $pb)) ?></td></tr>
      <?php if ((float)$t['iskonto_tutari'] > 0): ?>
        <tr>
          <td>İskonto (%<?= number_format((float)$t['iskonto_orani'], 2, ',', '.') ?>):</td>
          <td class="num">- <?= e(fmt_money((float)$t['iskonto_tutari'], $pb)) ?></td>
        </tr>
      <?php endif; ?>
      <?php $kdvBD = kdv_dagilimi($kalemler, (float)$t['iskonto_orani']); ?>
      <?php if (count($kdvBD) > 1): ?>
        <?php foreach ($kdvBD as $bd): ?>
          <tr>
            <td style="font-size:10.5px; color:#4a5568">&nbsp;&nbsp;KDV %<?= number_format($bd['orani'], ($bd['orani'] == (int)$bd['orani'] ? 0 : 2), ',', '.') ?>
              <span style="color:#8895a7">(<?= e(fmt_money($bd['matrah'], $pb)) ?>)</span>:</td>
            <td class="num" style="font-size:10.5px; color:#4a5568"><?= e(fmt_money($bd['tutar'], $pb)) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr><td style="border-top:1px dashed #cbd5e1">Toplam KDV:</td>
            <td class="num" style="border-top:1px dashed #cbd5e1"><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></td></tr>
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
    <div class="notlar">
      <strong>Notlar:</strong><br>
      <?= nl2br(e($t['notlar'])) ?>
    </div>
  <?php endif; ?>

  <?php if (trim($t['sartlar'] ?? '') !== ''): ?>
    <div class="notlar" style="border-left-color:#1b3a6b">
      <strong>Genel Şartlar:</strong><br>
      <?= nl2br(e($t['sartlar'])) ?>
    </div>
  <?php endif; ?>

  <?php $bankalar = banka_hesaplari($pb); ?>
  <?php if (!empty($bankalar)): ?>
    <div class="banka-blok">
      <h4>Banka Hesap Bilgileri (<?= e($pb) ?>)</h4>
      <?php foreach ($bankalar as $b): ?>
        <div class="row">
          <strong><?= e($b['banka_adi']) ?></strong>
          <?php if ($b['sube_adi']): ?> · <?= e($b['sube_adi']) ?><?php endif; ?>
          <?php if ($b['sube_kodu']): ?> (<?= e($b['sube_kodu']) ?>)<?php endif; ?>
          &nbsp;&nbsp;· Hesap Sahibi: <?= e($b['hesap_sahibi']) ?>
          <?php if ($b['hesap_no']): ?> · No: <?= e($b['hesap_no']) ?><?php endif; ?>
        </div>
        <?php if ($b['iban']): ?>
          <div class="row">IBAN: <code><?= e(iban_format($b['iban'])) ?></code>
            <?php if ($b['swift']): ?> &nbsp;·&nbsp; SWIFT: <code><?= e($b['swift']) ?></code><?php endif; ?>
          </div>
        <?php elseif ($b['swift']): ?>
          <div class="row">SWIFT: <code><?= e($b['swift']) ?></code></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="foot">
    <?= e(ayar_get('sirket_adi', SITE_NAME)) ?>
    <?php if (ayar_get('sirket_web')): ?> · <?= e(ayar_get('sirket_web')) ?><?php endif; ?>
  </div>
</div>
<?php if ((int)get_param('auto', 0) === 1): ?>
<script>
  // ?auto=1 ile açıldıysa yazdırma diyaloğunu otomatik aç
  window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 300);
  });
</script>
<?php endif; ?>
</body>
</html>
