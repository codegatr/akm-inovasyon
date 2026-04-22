<?php
/**
 * Banka Hesapları - Liste
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$page_title = 'Banka Hesapları';

// Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('islem') === 'sil') {
    require_csrf();
    $id = int_param(post('id'));
    if ($id > 0) {
        db()->prepare('DELETE FROM ' . tbl('banka_hesap') . ' WHERE id = ?')->execute([$id]);
        log_yaz('sil', 'banka', $id, 'Banka hesabı silindi');
        flash('success', 'Banka hesabı silindi.');
    }
    redirect('yonetim.php?sayfa=banka');
}

// Durum değiştir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('islem') === 'durum') {
    require_csrf();
    $id = int_param(post('id'));
    if ($id > 0) {
        db()->prepare('UPDATE ' . tbl('banka_hesap') . ' SET aktif = 1 - aktif, guncelleme_tarihi = NOW() WHERE id = ?')
            ->execute([$id]);
        flash('success', 'Durum güncellendi.');
    }
    redirect('yonetim.php?sayfa=banka');
}

$hesaplar = db()->query('SELECT * FROM ' . tbl('banka_hesap') . ' ORDER BY sira, id')->fetchAll();
?>
<div class="card">
  <div class="card-head">
    <h2>🏦 Banka Hesapları <span class="muted" style="font-weight:400">(<?= count($hesaplar) ?>)</span></h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=banka_form" class="btn btn-primary btn-sm">+ Yeni Hesap</a>
    </div>
  </div>

  <div class="alert alert-info">
    Eklediğiniz aktif banka hesapları teklif PDF çıktılarında ve müşteri görüntüleme sayfalarında
    görünür. Tekliflerin para birimine göre uygun hesap(lar) otomatik listelenecektir.
  </div>

  <?php if (empty($hesaplar)): ?>
    <p class="muted">Henüz banka hesabı eklenmemiş. <a href="yonetim.php?sayfa=banka_form">İlk hesabı ekle →</a></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Banka</th>
            <th>Hesap Sahibi</th>
            <th>IBAN</th>
            <th>SWIFT</th>
            <th class="cen">Para Birimi</th>
            <th class="cen">Durum</th>
            <th class="cen">İşlem</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($hesaplar as $h): ?>
          <tr>
            <td>
              <strong><?= e($h['banka_adi']) ?></strong>
              <?php if ($h['sube_adi']): ?>
                <br><small class="muted"><?= e($h['sube_adi']) ?><?php if ($h['sube_kodu']): ?> (<?= e($h['sube_kodu']) ?>)<?php endif; ?></small>
              <?php endif; ?>
            </td>
            <td><?= e($h['hesap_sahibi']) ?>
              <?php if ($h['hesap_no']): ?><br><small class="muted">No: <?= e($h['hesap_no']) ?></small><?php endif; ?>
            </td>
            <td style="font-family: monospace; font-size: 12px"><?= e($h['iban'] ?: '-') ?></td>
            <td style="font-family: monospace"><?= e($h['swift'] ?: '-') ?></td>
            <td class="cen"><span class="badge badge-blue"><?= e($h['para_birimi']) ?></span></td>
            <td class="cen">
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="islem" value="durum">
                <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                <button type="submit" class="btn btn-xs <?= $h['aktif'] ? 'btn-success' : 'btn-ghost' ?>" title="Durumu değiştir">
                  <?= $h['aktif'] ? '✓ Aktif' : 'Pasif' ?>
                </button>
              </form>
            </td>
            <td class="cen" style="white-space:nowrap">
              <a class="btn btn-ghost btn-xs" href="yonetim.php?sayfa=banka_form&id=<?= (int)$h['id'] ?>">Düzenle</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="islem" value="sil">
                <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                <button type="submit" class="btn btn-danger btn-xs" data-confirm="<?= e($h['banka_adi']) ?> silinsin mi?">Sil</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
