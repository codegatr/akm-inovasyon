<?php
/**
 * Teklif Görüntüleme — PDF Önizlemesi (iframe ile)
 * Admin görüntüleme yazdırma çıktısının bire bir aynısıdır.
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$id = int_param(get_param('id', 0));
if ($id <= 0) { flash('error', 'Geçersiz teklif.'); redirect('yonetim.php?sayfa=teklif'); }

$st = db()->prepare('SELECT t.*, c.firma_adi, c.cari_kodu
                     FROM ' . tbl('teklif') . ' t
                     LEFT JOIN ' . tbl('cari') . ' c ON c.id = t.cari_id
                     WHERE t.id = ?');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) { flash('error', 'Teklif bulunamadı.'); redirect('yonetim.php?sayfa=teklif'); }

$page_title = 'Teklif: ' . $t['teklif_no'];

// Gönderim geçmişi
$st = db()->prepare('SELECT * FROM ' . tbl('teklif_gonderim') . ' WHERE teklif_id = ? ORDER BY id DESC');
$st->execute([$id]);
$gonderimler = $st->fetchAll();
?>
<div class="card no-print">
  <div class="card-head">
    <h2>Teklif: <?= e($t['teklif_no']) ?> <?= durum_rozet($t['durum']) ?>
      <span class="muted" style="font-weight:400; font-size:14px">· <?= e($t['firma_adi'] ?? '-') ?></span>
    </h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=teklif" class="btn btn-ghost btn-sm">← Listeye</a>
      <a href="yonetim.php?sayfa=teklif_form&id=<?= $id ?>" class="btn btn-ghost btn-sm">Düzenle</a>
      <a href="yonetim.php?sayfa=teklif_pdf&id=<?= $id ?>&auto=1" target="_blank" class="btn btn-primary btn-sm">PDF / Yazdır</a>
      <a href="yonetim.php?sayfa=teklif_gonder&id=<?= $id ?>" class="btn btn-accent btn-sm">Mail Gönder</a>
      <?php if ($t['view_token']): ?>
        <a href="teklif.php?tk=<?= e($t['view_token']) ?>" target="_blank" class="btn btn-ghost btn-sm">Müşteri Linki</a>
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

<div class="teklif-preview-wrap">
  <iframe id="teklifPreview"
          src="yonetim.php?sayfa=teklif_pdf&id=<?= $id ?>&embed=1"
          class="teklif-preview-iframe"
          title="Teklif Önizleme"></iframe>
</div>

<script>
  // Iframe yüklendiğinde içeriğe göre yüksekliği ayarla
  (function () {
    const ifr = document.getElementById('teklifPreview');
    if (!ifr) return;
    function resize() {
      try {
        const h = ifr.contentWindow.document.documentElement.scrollHeight;
        if (h > 100) ifr.style.height = (h + 40) + 'px';
      } catch (e) { /* cross-origin korumasından güvenli çıkış */ }
    }
    ifr.addEventListener('load', function () {
      resize();
      // Fontların yüklenmesi için küçük gecikme
      setTimeout(resize, 500);
    });
  })();
</script>

<?php if (!empty($gonderimler)): ?>
<div class="card no-print" style="margin-top: 16px">
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
