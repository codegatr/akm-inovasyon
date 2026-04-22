<?php
/**
 * Teklif PDF / Yazdırma Sayfası — Kurumsal Tasarım
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
/* === Sayfa Ayarları === */
@page { size: A4; margin: 0; }

/* === Temel === */
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
  font-family: 'Helvetica Neue', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
  font-size: 10.5pt; color: #1a1f2e; line-height: 1.5;
  background: #e5e7eb; -webkit-print-color-adjust: exact; print-color-adjust: exact;
}

/* === A4 Sayfa === */
.page {
  width: 210mm; min-height: 297mm; margin: 20px auto;
  background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.08);
  padding: 18mm 18mm 15mm; position: relative;
  display: flex; flex-direction: column;
}

/* === Yazdırma === */
@media print {
  html, body { background: #fff; }
  .page { margin: 0; box-shadow: none; width: 100%; min-height: 100%; }
  .actions { display: none !important; }
}

/* === Üst Bant (Header) === */
.hdr {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding-bottom: 14px; margin-bottom: 16px;
  border-bottom: 3px solid #1b3a6b;
  position: relative;
}
.hdr::after {
  content: ''; position: absolute; bottom: -3px; left: 0; width: 120px; height: 3px;
  background: #2ca69a;
}
.hdr-brand { display: flex; align-items: center; gap: 14px; flex: 1; }
.hdr-logo {
  max-height: 72px; max-width: 180px;
}
.hdr-logo img { max-height: 72px; max-width: 180px; object-fit: contain; display: block; }
.hdr-logo.text-fallback {
  font-size: 26pt; font-weight: 800; color: #1b3a6b;
  letter-spacing: 3px; line-height: 1;
  padding: 6px 12px; border: 2px solid #1b3a6b; border-radius: 4px;
}
.hdr-firma { display: flex; flex-direction: column; justify-content: center; }
.hdr-firma .ad {
  font-size: 14pt; font-weight: 700; color: #1b3a6b;
  letter-spacing: .3px; line-height: 1.25; margin-bottom: 3px;
}
.hdr-firma .sub {
  font-size: 8.5pt; color: #64748b; line-height: 1.45;
}

.hdr-doc { text-align: right; flex-shrink: 0; min-width: 180px; }
.hdr-doc .kind {
  font-size: 9pt; color: #64748b; letter-spacing: 2px; text-transform: uppercase;
  font-weight: 600; margin-bottom: 2px;
}
.hdr-doc .num {
  font-size: 18pt; font-weight: 700; color: #1b3a6b;
  letter-spacing: .5px; font-variant-numeric: tabular-nums; line-height: 1.2;
}
.hdr-doc .dates {
  margin-top: 8px; font-size: 9pt; line-height: 1.55;
}
.hdr-doc .dates .lb { color: #64748b; display: inline-block; width: 64px; }
.hdr-doc .dates .vl { font-weight: 600; color: #1a1f2e; }

/* === Taraflar Kartları === */
.parties {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px;
}
.party-card {
  padding: 11px 14px; background: #f8fafc;
  border-left: 3px solid #2ca69a; border-radius: 2px;
}
.party-card .hd {
  font-size: 8pt; font-weight: 700; color: #1b3a6b;
  letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 6px;
  padding-bottom: 4px; border-bottom: 1px solid #e2e8f0;
}
.party-card .firma-ad {
  font-size: 11pt; font-weight: 700; color: #1a1f2e; margin-bottom: 4px;
}
.party-card .line { font-size: 9pt; line-height: 1.5; color: #334155; }
.party-card .line .lb { color: #64748b; display: inline-block; min-width: 52px; }
.party-card.party-us { border-left-color: #1b3a6b; }

/* === Kalemler Tablosu === */
.items {
  width: 100%; border-collapse: collapse; margin-bottom: 10px;
  font-size: 9.5pt;
}
.items thead th {
  background: #1b3a6b; color: #fff; font-weight: 600;
  padding: 8px 8px; text-align: left; font-size: 8.5pt;
  letter-spacing: .5px; text-transform: uppercase;
  border: 0;
}
.items thead th.num { text-align: right; }
.items thead th:first-child { border-top-left-radius: 3px; padding-left: 10px; }
.items thead th:last-child  { border-top-right-radius: 3px; padding-right: 10px; }
.items tbody td {
  padding: 9px 8px; vertical-align: top;
  border-bottom: 1px solid #e2e8f0;
}
.items tbody td:first-child { padding-left: 10px; }
.items tbody td:last-child  { padding-right: 10px; }
.items tbody tr:nth-child(even) td { background: #fafbfd; }
.items tbody .sira { color: #94a3b8; font-weight: 600; font-size: 9pt; }
.items tbody .urun { font-weight: 600; color: #1a1f2e; }
.items tbody .aciklama { font-size: 8.5pt; color: #64748b; line-height: 1.45; margin-top: 2px; }
.items tbody td.num {
  text-align: right; font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

/* === Toplamlar === */
.sum-wrap {
  display: flex; justify-content: flex-end; margin-top: 6px; margin-bottom: 18px;
}
.sum {
  min-width: 320px; border-collapse: collapse;
}
.sum td { padding: 5px 10px; font-size: 10pt; vertical-align: middle; }
.sum td.lb { color: #475569; }
.sum td.vl { text-align: right; font-variant-numeric: tabular-nums; font-weight: 500; }
.sum tr.sep td { border-top: 1px dashed #cbd5e1; padding-top: 7px; }
.sum tr.kdv-satir td { font-size: 9pt; color: #64748b; padding: 2px 10px; }
.sum tr.kdv-satir td.lb { padding-left: 18px; }
.sum tr.kdv-matrah td { font-size: 8pt; color: #94a3b8; padding: 0 10px 3px 18px; font-style: italic; }
.sum tr.grand td {
  background: #1b3a6b; color: #fff;
  font-size: 12.5pt; font-weight: 700; letter-spacing: .5px;
  padding: 10px 14px; border-radius: 2px;
}
.sum tr.grand td.vl { text-align: right; }
.sum tr.spacer td { padding: 4px 0; }

/* === Notlar & Şartlar === */
.notes-block {
  margin-bottom: 14px; padding: 10px 14px;
  background: #f8fafc; border-left: 3px solid #2ca69a;
  border-radius: 2px; font-size: 9.5pt; line-height: 1.5;
}
.notes-block .hd {
  font-size: 8.5pt; font-weight: 700; color: #1b3a6b;
  letter-spacing: 1px; text-transform: uppercase; margin-bottom: 5px;
}
.terms-block { border-left-color: #1b3a6b; }

/* === Banka Bilgileri === */
.bank-block {
  margin-bottom: 16px; padding: 12px 14px;
  background: linear-gradient(to right, rgba(27,58,107,.03), rgba(44,166,154,.03));
  border: 1px solid #e2e8f0; border-left: 3px solid #1b3a6b;
  border-radius: 2px; font-size: 9pt;
}
.bank-block .hd {
  font-size: 8.5pt; font-weight: 700; color: #1b3a6b;
  letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 8px;
}
.bank-row { padding: 6px 0; border-bottom: 1px dashed rgba(27,58,107,.1); line-height: 1.6; }
.bank-row:last-child { border-bottom: 0; padding-bottom: 0; }
.bank-row .banka-ad { font-weight: 700; color: #1a1f2e; }
.bank-row .sube { color: #64748b; font-size: 8.5pt; }
.bank-row .lb { color: #64748b; min-width: 80px; display: inline-block; font-size: 8.5pt; }
.bank-row .iban, .bank-row .swift {
  font-family: 'SF Mono', 'Consolas', 'Courier New', monospace;
  font-weight: 600; color: #1b3a6b; letter-spacing: .5px;
}

/* === İmza Bloğu === */
.signatures {
  margin-top: auto; padding-top: 18px;
  display: grid; grid-template-columns: 1fr 1fr; gap: 40px;
}
.sig-col {
  text-align: center; border-top: 1px solid #cbd5e1;
  padding-top: 8px;
}
.sig-col .role {
  font-size: 8.5pt; color: #64748b; letter-spacing: 1px;
  text-transform: uppercase; margin-bottom: 2px;
}
.sig-col .name { font-size: 10pt; font-weight: 600; color: #1a1f2e; }

/* === Alt Bilgi === */
.footer {
  margin-top: 14px; padding-top: 8px;
  border-top: 1px solid #e2e8f0;
  text-align: center; font-size: 7.5pt; color: #94a3b8;
  line-height: 1.55;
}
.footer strong { color: #64748b; }

/* === Üst Çubuk Renkli Şerit === */
.top-ribbon {
  position: absolute; top: 0; left: 0; right: 0; height: 6px;
  background: linear-gradient(to right, #1b3a6b 0%, #1b3a6b 70%, #2ca69a 70%, #2ca69a 100%);
}

/* === Yazdırma Aksiyon Butonları === */
.actions {
  position: fixed; top: 12px; right: 12px; z-index: 100;
  display: flex; gap: 8px;
}
.actions button {
  padding: 10px 18px; font-size: 11pt; font-weight: 600;
  background: #1b3a6b; color: #fff; border: 0; border-radius: 5px;
  cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,.2);
}
.actions button.ghost { background: #475569; }
.actions button:hover { opacity: .92; }
</style>
</head>
<body>

<div class="actions">
  <button onclick="window.print()">PDF Olarak Kaydet / Yazdır</button>
  <button class="ghost" onclick="window.close()">Kapat</button>
</div>

<div class="page">
  <div class="top-ribbon"></div>

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
          <?= e(ayar_get('sirket_adres', '')) ?><br>
          <?php $bits = [];
            if (ayar_get('sirket_telefon')) $bits[] = 'T: ' . ayar_get('sirket_telefon');
            if (ayar_get('sirket_email'))   $bits[] = ayar_get('sirket_email');
            if (ayar_get('sirket_web'))     $bits[] = ayar_get('sirket_web');
            echo e(implode(' · ', $bits));
          ?>
        </div>
      </div>
    </div>
    <div class="hdr-doc">
      <div class="kind">Proforma Teklif</div>
      <div class="num"><?= e($t['teklif_no']) ?></div>
      <div class="dates">
        <div><span class="lb">Tarih:</span> <span class="vl"><?= fmt_date($t['tarih']) ?></span></div>
        <?php if ($t['gecerlilik_tarihi']): ?>
          <div><span class="lb">Geçerlilik:</span> <span class="vl"><?= fmt_date($t['gecerlilik_tarihi']) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- TARAFLAR -->
  <div class="parties">
    <div class="party-card party-us">
      <div class="hd">Satıcı / Düzenleyen</div>
      <div class="firma-ad"><?= e(ayar_get('sirket_adi', SITE_NAME)) ?></div>
      <?php if (ayar_get('sirket_adres')): ?><div class="line"><?= nl2br(e(ayar_get('sirket_adres'))) ?></div><?php endif; ?>
      <?php if (ayar_get('sirket_vergi_dairesi') || ayar_get('sirket_vkn')): ?>
        <div class="line">
          <?php if (ayar_get('sirket_vergi_dairesi')): ?><span class="lb">V.D.:</span> <?= e(ayar_get('sirket_vergi_dairesi')) ?><?php endif; ?>
          <?php if (ayar_get('sirket_vkn')): ?><br><span class="lb">VKN:</span> <?= e(ayar_get('sirket_vkn')) ?><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="party-card">
      <div class="hd">Sayın Müşterimiz</div>
      <div class="firma-ad"><?= e($t['firma_adi']) ?></div>
      <?php if ($t['yetkili_adi']): ?><div class="line"><span class="lb">İlgili:</span> <?= e($t['yetkili_adi']) ?></div><?php endif; ?>
      <?php if ($t['cari_adres']): ?><div class="line"><?= nl2br(e($t['cari_adres'])) ?></div><?php endif; ?>
      <?php $yer = trim(($t['cari_sehir'] ?? '') . ' ' . ($t['cari_ulke'] ?? '')); ?>
      <?php if ($yer): ?><div class="line"><?= e($yer) ?></div><?php endif; ?>
      <?php if ($t['cari_telefon'] || $t['cari_email']): ?>
        <div class="line">
          <?php if ($t['cari_telefon']): ?><span class="lb">Tel:</span> <?= e($t['cari_telefon']) ?><?php endif; ?>
          <?php if ($t['cari_email']): ?><br><span class="lb">E-posta:</span> <?= e($t['cari_email']) ?><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($t['cari_vkn']): ?>
        <div class="line">
          <span class="lb">VKN:</span> <?= e($t['cari_vkn']) ?>
          <?php if ($t['cari_vd']): ?> · <?= e($t['cari_vd']) ?><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TEKLİF PARAMETRELERİ (küçük meta satır) -->
  <div style="display:flex; gap:14px; margin-bottom: 14px; padding: 9px 14px;
              background:#f1f5f9; border-radius:2px; font-size:9pt;">
    <div><span style="color:#64748b">Para Birimi:</span> <strong><?= e($pb) ?></strong></div>
    <?php if ($t['doviz_kuru'] > 1 && $pb !== 'TRY'): ?>
      <div><span style="color:#64748b">Kur:</span> <strong>1 <?= e($pb) ?> = <?= e(number_format((float)$t['doviz_kuru'], 4, ',', '.')) ?> TRY</strong></div>
    <?php endif; ?>
    <?php if ($t['odeme_sekli']): ?>
      <div><span style="color:#64748b">Ödeme:</span> <strong><?= e($t['odeme_sekli']) ?></strong></div>
    <?php endif; ?>
    <?php if ($t['teslim_sekli']): ?>
      <div><span style="color:#64748b">Teslim:</span> <strong><?= e($t['teslim_sekli']) ?></strong></div>
    <?php endif; ?>
  </div>

  <!-- KALEMLER -->
  <table class="items">
    <thead>
      <tr>
        <th style="width:26px">#</th>
        <th>Ürün / Hizmet</th>
        <th class="num" style="width:70px">Miktar</th>
        <th style="width:48px">Birim</th>
        <th class="num" style="width:88px">B. Fiyat</th>
        <?php if ($hasIsk): ?><th class="num" style="width:50px">İsk.</th><?php endif; ?>
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
          <td class="num"><?= (float)$k['iskonto_orani'] > 0 ? '%' . number_format((float)$k['iskonto_orani'], 0, ',', '.') : '-' ?></td>
        <?php endif; ?>
        <td class="num">%<?= number_format((float)$k['kdv_orani'], 0, ',', '.') ?></td>
        <td class="num"><strong><?= e(fmt_money((float)$k['satir_toplam'], $pb)) ?></strong></td>
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
          <td class="vl">- <?= e(fmt_money((float)$t['iskonto_tutari'], $pb)) ?></td>
        </tr>
      <?php endif; ?>

      <?php if (count($kdvBD) >= 2): ?>
        <tr class="sep"><td colspan="2" style="padding:0; border-top:1px dashed #cbd5e1;"></td></tr>
        <?php foreach ($kdvBD as $bd):
          $rateLabel = '%' . number_format($bd['orani'], ($bd['orani'] == (int)$bd['orani'] ? 0 : 2), ',', '.');
        ?>
          <tr class="kdv-satir">
            <td class="lb">KDV <?= e($rateLabel) ?></td>
            <td class="vl"><?= e(fmt_money($bd['tutar'], $pb)) ?></td>
          </tr>
          <tr class="kdv-matrah">
            <td colspan="2" style="text-align:left">Matrah: <?= e(fmt_money($bd['matrah'], $pb)) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td class="lb" style="font-weight:600; color:#1b3a6b;">Toplam KDV</td>
          <td class="vl" style="font-weight:600; color:#1b3a6b;"><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></td>
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
        <td class="lb">GENEL TOPLAM</td>
        <td class="vl"><?= e(fmt_money((float)$t['genel_toplam'], $pb)) ?></td>
      </tr>
    </table>
  </div>

  <!-- NOTLAR -->
  <?php if (trim($t['notlar'] ?? '') !== ''): ?>
    <div class="notes-block">
      <div class="hd">Notlar</div>
      <div><?= nl2br(e($t['notlar'])) ?></div>
    </div>
  <?php endif; ?>

  <!-- ŞARTLAR -->
  <?php if (trim($t['sartlar'] ?? '') !== ''): ?>
    <div class="notes-block terms-block">
      <div class="hd">Genel Şartlar</div>
      <div><?= nl2br(e($t['sartlar'])) ?></div>
    </div>
  <?php endif; ?>

  <!-- BANKA BİLGİLERİ -->
  <?php if (!empty($bankalar)): ?>
    <div class="bank-block">
      <div class="hd">Banka Hesap Bilgileri — <?= e($pb) ?></div>
      <?php foreach ($bankalar as $b): ?>
        <div class="bank-row">
          <div>
            <span class="banka-ad"><?= e($b['banka_adi']) ?></span>
            <?php if ($b['sube_adi']): ?> <span class="sube">· <?= e($b['sube_adi']) ?><?php if ($b['sube_kodu']): ?> (Şube Kodu: <?= e($b['sube_kodu']) ?>)<?php endif; ?></span><?php endif; ?>
          </div>
          <div><span class="lb">Hesap Sahibi:</span> <?= e($b['hesap_sahibi']) ?><?php if ($b['hesap_no']): ?> · <span class="lb">Hesap No:</span> <?= e($b['hesap_no']) ?><?php endif; ?></div>
          <?php if ($b['iban']): ?>
            <div><span class="lb">IBAN:</span> <span class="iban"><?= e(iban_format($b['iban'])) ?></span>
              <?php if ($b['swift']): ?> &nbsp; <span class="lb">SWIFT:</span> <span class="swift"><?= e($b['swift']) ?></span><?php endif; ?>
            </div>
          <?php elseif ($b['swift']): ?>
            <div><span class="lb">SWIFT:</span> <span class="swift"><?= e($b['swift']) ?></span></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- İMZA BLOĞU -->
  <div class="signatures">
    <div class="sig-col">
      <div class="role">Düzenleyen</div>
      <div class="name"><?= e(ayar_get('sirket_adi', SITE_NAME)) ?></div>
    </div>
    <div class="sig-col">
      <div class="role">Müşteri Kaşe / İmza</div>
      <div class="name" style="color:#94a3b8">&nbsp;</div>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="footer">
    Bu belge <strong><?= e(ayar_get('sirket_adi', SITE_NAME)) ?></strong> tarafından düzenlenmiş proforma bir tekliftir.
    <?php if (ayar_get('sirket_web')): ?><br><?= e(ayar_get('sirket_web')) ?><?php endif; ?>
    <?php if (ayar_get('sirket_telefon')): ?> · Tel: <?= e(ayar_get('sirket_telefon')) ?><?php endif; ?>
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
