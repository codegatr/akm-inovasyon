<?php
/**
 * Teklif PDF / Yazdırma — Nazik Kurumsal Tasarım
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
$kdvBD = kdv_dagilimi($kalemler, (float)$t['iskonto_orani']);
$bankalar = banka_hesaplari($pb);
$logo = sirket_logo();
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Teklif <?= e($t['teklif_no']) ?></title>
<style>
/* === Sayfa === */
@page { size: A4; margin: 0; }

* { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
  font-family: 'Helvetica Neue', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
  font-size: 10pt;
  color: #2d3748;
  line-height: 1.55;
  background: #ececec;
  -webkit-print-color-adjust: exact; print-color-adjust: exact;
  font-feature-settings: "kern" 1, "liga" 1, "tnum" 1;
}

.page {
  width: 210mm; min-height: 297mm; margin: 24px auto;
  background: #fff; box-shadow: 0 2px 16px rgba(0,0,0,.06);
  padding: 22mm 20mm 18mm; position: relative;
  display: flex; flex-direction: column;
}

@media print {
  html, body { background: #fff; }
  .page { margin: 0; box-shadow: none; width: 100%; min-height: 100%; }
  .actions { display: none !important; }
}

/* === Üst İnce Çizgi === */
.top-line {
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: linear-gradient(to right, #1b3a6b 0%, #1b3a6b 75%, #2ca69a 75%, #2ca69a 100%);
}

/* === HEADER === */
.hdr {
  display: flex; justify-content: space-between; align-items: center;
  padding-bottom: 18px; margin-bottom: 28px;
  border-bottom: 1px solid #e5e7eb;
}
.hdr-brand { display: flex; align-items: center; gap: 16px; flex: 1; min-width: 0; }
.hdr-logo { flex-shrink: 0; }
.hdr-logo img { max-height: 68px; max-width: 150px; object-fit: contain; display: block; }
.hdr-logo.text-fallback {
  font-size: 22pt; font-weight: 700; color: #1b3a6b;
  letter-spacing: 2px; line-height: 1;
  padding: 4px 10px; border-left: 2px solid #2ca69a;
}

.hdr-firma { min-width: 0; }
.hdr-firma .ad {
  font-size: 11pt; font-weight: 600; color: #1b3a6b;
  line-height: 1.3; margin-bottom: 4px;
}
.hdr-firma .sub {
  font-size: 8.5pt; color: #6b7280; line-height: 1.5;
}

.hdr-doc { text-align: right; flex-shrink: 0; margin-left: 20px; }
.hdr-doc .kind {
  font-size: 8pt; color: #9ca3af;
  letter-spacing: 2.5px; text-transform: uppercase;
  font-weight: 500; margin-bottom: 4px;
}
.hdr-doc .num {
  font-size: 16pt; font-weight: 600; color: #1a1a2e;
  letter-spacing: .5px; line-height: 1.2;
  font-variant-numeric: tabular-nums;
}
.hdr-doc .dates {
  margin-top: 10px; font-size: 8.5pt; color: #6b7280;
  line-height: 1.6;
}
.hdr-doc .dates .lb { color: #9ca3af; margin-right: 4px; }
.hdr-doc .dates .vl { color: #2d3748; font-weight: 500; }

/* === Taraflar === */
.parties {
  display: grid; grid-template-columns: 1fr 1fr; gap: 32px;
  margin-bottom: 26px;
}
.party-card { font-size: 9.5pt; }
.party-card .hd {
  font-size: 7.5pt; font-weight: 600; color: #2ca69a;
  letter-spacing: 2px; text-transform: uppercase;
  margin-bottom: 8px; padding-bottom: 6px;
  border-bottom: 1px solid #e5e7eb;
}
.party-card .firma-ad {
  font-size: 11.5pt; font-weight: 600; color: #1a1a2e;
  margin-bottom: 6px; line-height: 1.35;
}
.party-card .line {
  font-size: 9pt; color: #4b5563; line-height: 1.6;
}
.party-card .line .lb {
  color: #9ca3af; display: inline-block; min-width: 52px;
  font-size: 8.5pt;
}

/* === Teklif Meta (ince satır) === */
.meta-strip {
  display: flex; gap: 28px; margin-bottom: 22px;
  padding: 10px 0; border-top: 1px solid #f3f4f6;
  border-bottom: 1px solid #f3f4f6;
  font-size: 9pt;
}
.meta-strip .item .lb {
  color: #9ca3af; font-size: 8pt;
  text-transform: uppercase; letter-spacing: 1px;
  margin-right: 6px;
}
.meta-strip .item .vl {
  color: #1a1a2e; font-weight: 600;
}

/* === Kalemler Tablosu === */
.items {
  width: 100%; border-collapse: collapse;
  margin-bottom: 14px; font-size: 9.5pt;
}
.items thead th {
  padding: 10px 10px 10px 0;
  text-align: left;
  font-size: 7.5pt; font-weight: 600;
  color: #9ca3af; letter-spacing: 1.5px;
  text-transform: uppercase;
  border-bottom: 1px solid #d1d5db;
  background: none;
}
.items thead th.num { text-align: right; padding-right: 0; padding-left: 10px; }
.items thead th:first-child { padding-left: 4px; }
.items thead th:last-child { padding-right: 4px; }

.items tbody td {
  padding: 11px 10px 11px 0; vertical-align: top;
  border-bottom: 1px solid #f3f4f6;
}
.items tbody td:first-child { padding-left: 4px; }
.items tbody td:last-child { padding-right: 4px; }
.items tbody tr:last-child td { border-bottom: 1px solid #e5e7eb; }

.items .sira {
  color: #cbd5e1; font-weight: 600; font-size: 9pt;
  font-variant-numeric: tabular-nums;
}
.items .urun {
  font-weight: 600; color: #1a1a2e; font-size: 10pt;
  line-height: 1.4;
}
.items .aciklama {
  font-size: 8.5pt; color: #6b7280;
  line-height: 1.5; margin-top: 2px;
}
.items td.num {
  text-align: right; white-space: nowrap;
  font-variant-numeric: tabular-nums;
  padding-left: 10px; padding-right: 0;
}
.items td.tutar { font-weight: 600; color: #1a1a2e; }

/* === Toplamlar === */
.sum-wrap {
  display: flex; justify-content: flex-end; margin: 8px 0 24px;
}
.sum {
  min-width: 320px; border-collapse: collapse;
}
.sum td {
  padding: 5px 0; font-size: 10pt; vertical-align: middle;
}
.sum td.lb { color: #6b7280; padding-right: 24px; }
.sum td.vl {
  text-align: right; color: #1a1a2e;
  font-variant-numeric: tabular-nums;
}

.sum tr.kdv-satir td {
  font-size: 9pt; color: #6b7280; padding: 3px 0;
}
.sum tr.kdv-satir td.lb { padding-left: 14px; }
.sum tr.kdv-matrah td {
  font-size: 7.5pt; color: #9ca3af;
  padding: 0 0 3px 14px; font-style: italic;
}

.sum tr.kdv-total td {
  font-size: 10pt; font-weight: 500; color: #4b5563;
  padding-top: 3px;
  border-top: 1px solid #f3f4f6;
}

.sum tr.spacer td { padding: 8px 0; }

.sum tr.grand td {
  font-size: 14pt; font-weight: 600;
  color: #1b3a6b; padding: 12px 0 4px;
  border-top: 2px solid #1b3a6b;
  border-bottom: 1px solid #1b3a6b;
  padding-bottom: 12px;
}
.sum tr.grand td.lb {
  letter-spacing: 1.5px; text-transform: uppercase;
  font-size: 9.5pt; color: #1b3a6b; font-weight: 600;
}
.sum tr.grand td.vl { font-size: 15pt; }

/* === Notlar / Şartlar === */
.note {
  margin-bottom: 16px; padding-left: 14px;
  border-left: 2px solid #e5e7eb;
  font-size: 9pt; line-height: 1.65; color: #4b5563;
}
.note .hd {
  font-size: 7.5pt; font-weight: 600; color: #2ca69a;
  letter-spacing: 2px; text-transform: uppercase;
  margin-bottom: 6px;
}
.note.terms { border-left-color: #cbd5e1; }
.note.terms .hd { color: #1b3a6b; }

/* === Banka === */
.bank-block {
  margin: 20px 0 8px; padding: 14px 16px;
  background: #f9fafb; border-radius: 3px;
  border: 1px solid #f3f4f6;
  font-size: 9pt;
}
.bank-block .hd {
  font-size: 7.5pt; font-weight: 600; color: #1b3a6b;
  letter-spacing: 2px; text-transform: uppercase;
  margin-bottom: 10px;
}
.bank-row { padding: 7px 0; line-height: 1.6; }
.bank-row + .bank-row {
  border-top: 1px dotted #e5e7eb; margin-top: 2px;
}
.bank-row .banka-ad { font-weight: 600; color: #1a1a2e; font-size: 10pt; }
.bank-row .sube { color: #6b7280; font-size: 8.5pt; font-weight: normal; }
.bank-row .lb { color: #9ca3af; font-size: 8.5pt; margin-right: 4px; }
.bank-row .iban, .bank-row .swift {
  font-family: 'SF Mono', 'Consolas', 'Menlo', monospace;
  font-weight: 600; color: #1b3a6b; letter-spacing: .5px;
}

/* === İmza === */
.signatures {
  margin-top: auto; padding-top: 38px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 50px;
}
.sig-col {
  text-align: center; padding-top: 8px;
  border-top: 1px solid #d1d5db;
}
.sig-col .role {
  font-size: 7.5pt; color: #9ca3af;
  letter-spacing: 2px; text-transform: uppercase;
  margin-bottom: 3px;
}
.sig-col .name {
  font-size: 9.5pt; font-weight: 500; color: #4b5563;
}

/* === Footer === */
.footer {
  margin-top: 22px; padding-top: 12px;
  border-top: 1px solid #f3f4f6;
  text-align: center; font-size: 7.5pt; color: #9ca3af;
  line-height: 1.6;
}

/* === Aksiyonlar === */
.actions {
  position: fixed; top: 16px; right: 16px; z-index: 100;
  display: flex; gap: 8px;
}
.actions button {
  padding: 9px 16px; font-size: 10.5pt; font-weight: 500;
  background: #1b3a6b; color: #fff; border: 0; border-radius: 5px;
  cursor: pointer; box-shadow: 0 2px 8px rgba(27,58,107,.2);
}
.actions button.ghost {
  background: #fff; color: #6b7280; border: 1px solid #d1d5db;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.actions button:hover { opacity: .92; }
</style>
</head>
<body>

<div class="actions">
  <button onclick="window.print()">PDF Olarak Kaydet</button>
  <button class="ghost" onclick="window.close()">Kapat</button>
</div>

<div class="page">
  <div class="top-line"></div>

  <!-- HEADER -->
  <div class="hdr">
    <div class="hdr-brand">
      <?php if ($logo): ?>
        <div class="hdr-logo"><img src="<?= e($logo['url']) ?>" alt="Logo"></div>
      <?php else: ?>
        <div class="hdr-logo text-fallback">AKM</div>
      <?php endif; ?>
      <div class="hdr-firma">
        <div class="ad"><?= e(ayar_get('sirket_adi', SITE_NAME)) ?></div>
        <div class="sub">
          <?php $bits = [];
            if (ayar_get('sirket_adres'))   $bits[] = ayar_get('sirket_adres');
            if (ayar_get('sirket_telefon')) $bits[] = 'T ' . ayar_get('sirket_telefon');
            if (ayar_get('sirket_email'))   $bits[] = ayar_get('sirket_email');
            echo e(implode(' · ', $bits));
          ?>
        </div>
      </div>
    </div>
    <div class="hdr-doc">
      <div class="kind">Proforma Teklif</div>
      <div class="num"><?= e($t['teklif_no']) ?></div>
      <div class="dates">
        <div><span class="lb">Tarih</span><span class="vl"><?= fmt_date($t['tarih']) ?></span></div>
        <?php if ($t['gecerlilik_tarihi']): ?>
          <div><span class="lb">Geçerli</span><span class="vl"><?= fmt_date($t['gecerlilik_tarihi']) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- TARAFLAR -->
  <div class="parties">
    <div class="party-card">
      <div class="hd">Satıcı</div>
      <div class="firma-ad"><?= e(ayar_get('sirket_adi', SITE_NAME)) ?></div>
      <?php if (ayar_get('sirket_adres')): ?>
        <div class="line"><?= nl2br(e(ayar_get('sirket_adres'))) ?></div>
      <?php endif; ?>
      <?php if (ayar_get('sirket_vergi_dairesi') || ayar_get('sirket_vkn')): ?>
        <div class="line">
          <?php if (ayar_get('sirket_vergi_dairesi')): ?><span class="lb">V.D.</span><?= e(ayar_get('sirket_vergi_dairesi')) ?><?php endif; ?>
          <?php if (ayar_get('sirket_vkn')): ?><br><span class="lb">VKN</span><?= e(ayar_get('sirket_vkn')) ?><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="party-card">
      <div class="hd">Müşteri</div>
      <div class="firma-ad"><?= e($t['firma_adi']) ?></div>
      <?php if ($t['yetkili_adi']): ?>
        <div class="line"><span class="lb">İlgili</span><?= e($t['yetkili_adi']) ?></div>
      <?php endif; ?>
      <?php if ($t['cari_adres']): ?>
        <div class="line"><?= nl2br(e($t['cari_adres'])) ?></div>
      <?php endif; ?>
      <?php $yer = trim(($t['cari_sehir'] ?? '') . ' ' . ($t['cari_ulke'] ?? '')); ?>
      <?php if ($yer): ?><div class="line"><?= e($yer) ?></div><?php endif; ?>
      <?php if ($t['cari_telefon']): ?>
        <div class="line"><span class="lb">Tel</span><?= e($t['cari_telefon']) ?></div>
      <?php endif; ?>
      <?php if ($t['cari_email']): ?>
        <div class="line"><span class="lb">E-posta</span><?= e($t['cari_email']) ?></div>
      <?php endif; ?>
      <?php if ($t['cari_vkn']): ?>
        <div class="line"><span class="lb">VKN</span><?= e($t['cari_vkn']) ?><?php if ($t['cari_vd']): ?> · <?= e($t['cari_vd']) ?><?php endif; ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- META ÇİZGİ -->
  <div class="meta-strip">
    <div class="item"><span class="lb">Para Birimi</span><span class="vl"><?= e($pb) ?></span></div>
    <?php if ($t['doviz_kuru'] > 1 && $pb !== 'TRY'): ?>
      <div class="item"><span class="lb">Kur</span><span class="vl">1 <?= e($pb) ?> = <?= e(number_format((float)$t['doviz_kuru'], 4, ',', '.')) ?> TRY</span></div>
    <?php endif; ?>
    <?php if ($t['odeme_sekli']): ?>
      <div class="item"><span class="lb">Ödeme</span><span class="vl"><?= e($t['odeme_sekli']) ?></span></div>
    <?php endif; ?>
    <?php if ($t['teslim_sekli']): ?>
      <div class="item"><span class="lb">Teslim</span><span class="vl"><?= e($t['teslim_sekli']) ?></span></div>
    <?php endif; ?>
  </div>

  <!-- KALEMLER -->
  <table class="items">
    <thead>
      <tr>
        <th style="width:24px">#</th>
        <th>Ürün / Hizmet</th>
        <th class="num" style="width:70px">Miktar</th>
        <th style="width:46px">Birim</th>
        <th class="num" style="width:86px">B. Fiyat</th>
        <?php if ($hasIsk): ?><th class="num" style="width:48px">İsk.</th><?php endif; ?>
        <th class="num" style="width:42px">KDV</th>
        <th class="num" style="width:110px">Tutar</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($kalemler as $i => $k): ?>
      <tr>
        <td class="sira"><?= $i + 1 ?></td>
        <td>
          <div class="urun"><?= e($k['urun_adi']) ?></div>
          <?php if (trim($k['aciklama'] ?? '') !== ''): ?>
            <div class="aciklama"><?= nl2br(e($k['aciklama'])) ?></div>
          <?php endif; ?>
        </td>
        <td class="num"><?= number_format((float)$k['miktar'], 2, ',', '.') ?></td>
        <td><?= e($k['birim']) ?></td>
        <td class="num"><?= number_format((float)$k['birim_fiyat'], 2, ',', '.') ?></td>
        <?php if ($hasIsk): ?>
          <td class="num"><?= (float)$k['iskonto_orani'] > 0 ? '%' . number_format((float)$k['iskonto_orani'], 0, ',', '.') : '—' ?></td>
        <?php endif; ?>
        <td class="num">%<?= number_format((float)$k['kdv_orani'], 0, ',', '.') ?></td>
        <td class="num tutar"><?= e(fmt_money((float)$k['satir_toplam'], $pb)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- TOPLAMLAR -->
  <div class="sum-wrap">
    <table class="sum">
      <tr>
        <td class="lb">Ara Toplam</td>
        <td class="vl"><?= e(fmt_money((float)$t['ara_toplam'], $pb)) ?></td>
      </tr>
      <?php if ((float)$t['iskonto_tutari'] > 0): ?>
        <tr>
          <td class="lb">İskonto (%<?= number_format((float)$t['iskonto_orani'], 2, ',', '.') ?>)</td>
          <td class="vl">− <?= e(fmt_money((float)$t['iskonto_tutari'], $pb)) ?></td>
        </tr>
      <?php endif; ?>

      <?php if (count($kdvBD) >= 2): ?>
        <?php foreach ($kdvBD as $bd):
          $rateLabel = '%' . number_format($bd['orani'], ($bd['orani'] == (int)$bd['orani'] ? 0 : 2), ',', '.');
        ?>
          <tr class="kdv-satir">
            <td class="lb">KDV <?= e($rateLabel) ?></td>
            <td class="vl"><?= e(fmt_money($bd['tutar'], $pb)) ?></td>
          </tr>
          <tr class="kdv-matrah">
            <td colspan="2" style="text-align:left">matrah <?= e(fmt_money($bd['matrah'], $pb)) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="kdv-total">
          <td class="lb">Toplam KDV</td>
          <td class="vl"><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></td>
        </tr>
      <?php elseif (count($kdvBD) === 1):
        $bd = $kdvBD[0];
        $rateLabel = '%' . number_format($bd['orani'], ($bd['orani'] == (int)$bd['orani'] ? 0 : 2), ',', '.');
      ?>
        <tr>
          <td class="lb">KDV <?= e($rateLabel) ?></td>
          <td class="vl"><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></td>
        </tr>
      <?php else: ?>
        <tr>
          <td class="lb">KDV</td>
          <td class="vl"><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></td>
        </tr>
      <?php endif; ?>

      <tr class="spacer"><td colspan="2"></td></tr>
      <tr class="grand">
        <td class="lb">Genel Toplam</td>
        <td class="vl"><?= e(fmt_money((float)$t['genel_toplam'], $pb)) ?></td>
      </tr>
    </table>
  </div>

  <!-- NOTLAR -->
  <?php if (trim($t['notlar'] ?? '') !== ''): ?>
    <div class="note">
      <div class="hd">Notlar</div>
      <div><?= nl2br(e($t['notlar'])) ?></div>
    </div>
  <?php endif; ?>

  <?php if (trim($t['sartlar'] ?? '') !== ''): ?>
    <div class="note terms">
      <div class="hd">Genel Şartlar</div>
      <div><?= nl2br(e($t['sartlar'])) ?></div>
    </div>
  <?php endif; ?>

  <!-- BANKA -->
  <?php if (!empty($bankalar)): ?>
    <div class="bank-block">
      <div class="hd">Banka Hesap Bilgileri · <?= e($pb) ?></div>
      <?php foreach ($bankalar as $b): ?>
        <div class="bank-row">
          <div>
            <span class="banka-ad"><?= e($b['banka_adi']) ?></span>
            <?php if ($b['sube_adi']): ?> <span class="sube">— <?= e($b['sube_adi']) ?><?php if ($b['sube_kodu']): ?>, Şube Kodu: <?= e($b['sube_kodu']) ?><?php endif; ?></span><?php endif; ?>
          </div>
          <div><span class="lb">Hesap Sahibi</span><?= e($b['hesap_sahibi']) ?><?php if ($b['hesap_no']): ?> &nbsp;<span class="lb">Hesap No</span><?= e($b['hesap_no']) ?><?php endif; ?></div>
          <?php if ($b['iban']): ?>
            <div><span class="lb">IBAN</span><span class="iban"><?= e(iban_format($b['iban'])) ?></span>
              <?php if ($b['swift']): ?> &nbsp;<span class="lb">SWIFT</span><span class="swift"><?= e($b['swift']) ?></span><?php endif; ?>
            </div>
          <?php elseif ($b['swift']): ?>
            <div><span class="lb">SWIFT</span><span class="swift"><?= e($b['swift']) ?></span></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- İMZA -->
  <div class="signatures">
    <div class="sig-col">
      <div class="role">Düzenleyen</div>
      <div class="name"><?= e(ayar_get('sirket_adi', SITE_NAME)) ?></div>
    </div>
    <div class="sig-col">
      <div class="role">Müşteri Kaşe / İmza</div>
      <div class="name">&nbsp;</div>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="footer">
    <?= e(ayar_get('sirket_adi', SITE_NAME)) ?>
    <?php if (ayar_get('sirket_web')): ?> · <?= e(ayar_get('sirket_web')) ?><?php endif; ?>
    <?php if (ayar_get('sirket_telefon')): ?> · <?= e(ayar_get('sirket_telefon')) ?><?php endif; ?>
    <?php if (ayar_get('sirket_email')): ?> · <?= e(ayar_get('sirket_email')) ?><?php endif; ?>
  </div>
</div>

<?php if ((int)get_param('auto', 0) === 1): ?>
<script>
  window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 300);
  });
</script>
<?php endif; ?>
</body>
</html>
