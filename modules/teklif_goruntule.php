<?php
/**
 * Teklif Görüntüleme (yönetim panelinden)
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$id = int_param(get_param('id', 0));
if ($id <= 0) { flash('error', 'Geçersiz teklif.'); redirect('yonetim.php?sayfa=teklif'); }

$st = db()->prepare('SELECT t.*, c.firma_adi, c.yetkili_adi, c.email AS cari_email, c.telefon AS cari_telefon,
                            c.adres AS cari_adres, c.ulke AS cari_ulke, c.sehir AS cari_sehir,
                            c.vkn_tckn AS cari_vkn, c.vergi_dairesi AS cari_vd, c.cari_kodu
                     FROM ' . tbl('teklif') . ' t
                     LEFT JOIN ' . tbl('cari') . ' c ON c.id = t.cari_id
                     WHERE t.id = ?');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) { flash('error', 'Teklif bulunamadı.'); redirect('yonetim.php?sayfa=teklif'); }

$st = db()->prepare('SELECT * FROM ' . tbl('teklif_kalem') . ' WHERE teklif_id = ? ORDER BY sira, id');
$st->execute([$id]);
$kalemler = $st->fetchAll();

$page_title = 'Teklif: ' . $t['teklif_no'];

// Gönderim geçmişi
$st = db()->prepare('SELECT * FROM ' . tbl('teklif_gonderim') . ' WHERE teklif_id = ? ORDER BY id DESC');
$st->execute([$id]);
$gonderimler = $st->fetchAll();

$pb = $t['para_birimi'];
$kdvToplamOran = count($kalemler) ? array_sum(array_column($kalemler, 'kdv_orani')) / count($kalemler) : 0;
?>
<div class="card no-print">
  <div class="card-head">
    <h2>Teklif: <?= e($t['teklif_no']) ?> <?= durum_rozet($t['durum']) ?></h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=teklif" class="btn btn-ghost btn-sm">← Listeye</a>
      <a href="yonetim.php?sayfa=teklif_form&id=<?= $id ?>" class="btn btn-ghost btn-sm">Düzenle</a>
      <a href="yonetim.php?sayfa=teklif_pdf&id=<?= $id ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ Yazdır / PDF</a>
      <a href="yonetim.php?sayfa=teklif_gonder&id=<?= $id ?>" class="btn btn-accent btn-sm">📧 Mail Gönder</a>
      <?php if ($t['view_token']): ?>
        <a href="teklif.php?tk=<?= e($t['view_token']) ?>" target="_blank" class="btn btn-ghost btn-sm">🔗 Herkese Açık Link</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($t['gonderim_tarihi']): ?>
    <div class="alert alert-info">
      <strong>Son gönderim:</strong> <?= fmt_datetime($t['gonderim_tarihi']) ?>
      <?php if ($t['gorulme_tarihi']): ?> · <strong>Görüldü:</strong> <?= fmt_datetime($t['gorulme_tarihi']) ?><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

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
          <?php if (ayar_get('sirket_vkn')):     ?> · VKN: <?= e(ayar_get('sirket_vkn')) ?><?php endif; ?>
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
        <?= e(trim(($t['cari_sehir'] ?? '') . ' ' . ($t['cari_ulke'] ?? ''))) ?><br>
        <?php if ($t['cari_telefon']): ?>Tel: <?= e($t['cari_telefon']) ?><br><?php endif; ?>
        <?php if ($t['cari_email']):   ?><?= e($t['cari_email']) ?><br><?php endif; ?>
        <?php if ($t['cari_vkn']):     ?>VKN: <?= e($t['cari_vkn']) ?>
          <?php if ($t['cari_vd']): ?> · <?= e($t['cari_vd']) ?><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="box">
      <h4>Teklif Bilgileri</h4>
      <div>
        Para Birimi: <strong><?= e($pb) ?></strong>
        <?php if ($t['doviz_kuru'] > 1 && $pb !== 'TRY'): ?>
          (Kur: 1 <?= e($pb) ?> = <?= e(number_format((float)$t['doviz_kuru'], 4, ',', '.')) ?> TRY)
        <?php endif; ?><br>
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
        <th class="num" style="width:80px">Miktar</th>
        <th style="width:60px">Birim</th>
        <th class="num" style="width:110px">Birim Fiyat</th>
        <?php
          $hasIsk = false;
          foreach ($kalemler as $kk) { if ((float)$kk['iskonto_orani'] > 0) { $hasIsk = true; break; } }
        ?>
        <?php if ($hasIsk): ?><th class="num" style="width:70px">İsk.%</th><?php endif; ?>
        <th class="num" style="width:60px">KDV%</th>
        <th class="num" style="width:120px">Tutar</th>
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
      <tr>
        <td>Ara Toplam:</td>
        <td class="num"><?= e(fmt_money((float)$t['ara_toplam'], $pb)) ?></td>
      </tr>
      <?php if ((float)$t['iskonto_tutari'] > 0): ?>
        <tr>
          <td>İskonto (%<?= number_format((float)$t['iskonto_orani'], 2, ',', '.') ?>):</td>
          <td class="num">- <?= e(fmt_money((float)$t['iskonto_tutari'], $pb)) ?></td>
        </tr>
      <?php endif; ?>
      <?php $kdvBD = kdv_dagilimi($kalemler, (float)$t['iskonto_orani']); ?>
      <?php if (count($kdvBD) > 1): ?>
        <?php foreach ($kdvBD as $bd): ?>
          <tr class="kdv-satir">
            <td>KDV %<?= number_format($bd['orani'], ($bd['orani'] == (int)$bd['orani'] ? 0 : 2), ',', '.') ?>
              <small style="color:var(--text-2)">(matrah <?= e(fmt_money($bd['matrah'], $pb)) ?>)</small>:</td>
            <td class="num"><?= e(fmt_money($bd['tutar'], $pb)) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td><strong>Toplam KDV:</strong></td>
          <td class="num"><strong><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></strong></td>
        </tr>
      <?php else: ?>
        <tr>
          <td>KDV<?php if (count($kdvBD) === 1): ?> %<?= number_format($kdvBD[0]['orani'], ($kdvBD[0]['orani'] == (int)$kdvBD[0]['orani'] ? 0 : 2), ',', '.') ?><?php endif; ?>:</td>
          <td class="num"><?= e(fmt_money((float)$t['kdv_toplam'], $pb)) ?></td>
        </tr>
      <?php endif; ?>
      <tr class="grand">
        <td>GENEL TOPLAM:</td>
        <td class="num"><?= e(fmt_money((float)$t['genel_toplam'], $pb)) ?></td>
      </tr>
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
          <div class="banka-satir"><span class="muted">Hesap Sahibi:</span> <?= e($b['hesap_sahibi']) ?>
            <?php if ($b['hesap_no']): ?> · <span class="muted">No:</span> <?= e($b['hesap_no']) ?><?php endif; ?>
          </div>
          <?php if ($b['iban']): ?>
            <div class="banka-satir"><span class="muted">IBAN:</span>
              <code><?= e(iban_format($b['iban'])) ?></code>
            </div>
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
  </div>
</div>

<?php if (!empty($gonderimler)): ?>
<div class="card no-print">
  <div class="card-head"><h2>Gönderim Geçmişi</h2></div>
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Tarih</th>
          <th>Alıcı</th>
          <th>Konu</th>
          <th>Durum</th>
          <th>Mesaj</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($gonderimler as $g): ?>
        <tr>
          <td><?= fmt_datetime($g['gonderim_tarihi']) ?></td>
          <td><?= e($g['alici_email']) ?><?php if ($g['cc']): ?><br><span class="muted">CC: <?= e($g['cc']) ?></span><?php endif; ?></td>
          <td><?= e($g['konu']) ?></td>
          <td>
            <?php if ($g['durum'] === 'basarili'): ?>
              <span class="badge badge-green">Başarılı</span>
            <?php else: ?>
              <span class="badge badge-red">Hata</span>
            <?php endif; ?>
          </td>
          <td><?= e($g['hata_mesaji'] ?: '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
