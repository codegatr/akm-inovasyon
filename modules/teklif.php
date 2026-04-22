<?php
/**
 * Proforma Teklifler - Liste
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$page_title = 'Proforma Teklifler';

// Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('islem') === 'sil') {
    require_csrf();
    $id = int_param(post('id'));
    if ($id > 0) {
        try {
            db()->beginTransaction();
            db()->prepare('DELETE FROM ' . tbl('teklif_kalem') . ' WHERE teklif_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM ' . tbl('teklif_gonderim') . ' WHERE teklif_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM ' . tbl('teklif') . ' WHERE id = ?')->execute([$id]);
            db()->commit();
            log_yaz('sil', 'teklif', $id, 'Teklif silindi');
            flash('success', 'Teklif silindi.');
        } catch (Throwable $e) {
            db()->rollBack();
            flash('error', 'Silme hatası: ' . $e->getMessage());
        }
    }
    redirect('yonetim.php?sayfa=teklif' . (get_param('arsiv') ? '&arsiv=1' : ''));
}

// Durum değiştir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('islem') === 'durum') {
    require_csrf();
    $id = int_param(post('id'));
    $d  = (string)post('durum');
    if ($id > 0 && in_array($d, ['taslak','gonderildi','goruldu','onaylandi','reddedildi','iptal'], true)) {
        db()->prepare('UPDATE ' . tbl('teklif') . ' SET durum=?, guncelleme_tarihi=NOW() WHERE id=?')->execute([$d, $id]);
        log_yaz('durum_degistir', 'teklif', $id, 'Durum: ' . $d);
        flash('success', 'Durum güncellendi.');
    }
    redirect('yonetim.php?sayfa=teklif' . (get_param('arsiv') ? '&arsiv=1' : ''));
}

// Arşive kaldır / Arşivden çıkar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(post('islem'), ['arsivle','arsivden_cikar'], true)) {
    require_csrf();
    $id = int_param(post('id'));
    if ($id > 0) {
        if (post('islem') === 'arsivle') {
            db()->prepare('UPDATE ' . tbl('teklif') . ' SET arsivlendi_tarihi = NOW(), guncelleme_tarihi = NOW() WHERE id = ?')->execute([$id]);
            log_yaz('arsivle', 'teklif', $id, 'Arşive kaldırıldı');
            flash('success', 'Teklif arşive kaldırıldı.');
        } else {
            db()->prepare('UPDATE ' . tbl('teklif') . ' SET arsivlendi_tarihi = NULL, guncelleme_tarihi = NOW() WHERE id = ?')->execute([$id]);
            log_yaz('arsivden_cikar', 'teklif', $id, 'Arşivden çıkarıldı');
            flash('success', 'Teklif arşivden çıkarıldı.');
        }
    }
    redirect('yonetim.php?sayfa=teklif' . (post('islem') === 'arsivle' ? '' : '&arsiv=1'));
}

// Filtre
$arsiv  = (int)get_param('arsiv', 0) === 1;
$q      = trim((string)get_param('q', ''));
$durum  = (string)get_param('durum', '');
$pb     = (string)get_param('pb', '');
$cari_f = int_param(get_param('cari', 0));
$p      = max(1, int_param(get_param('p', 1), 1));
$per    = 20;

$where = [];
$where[] = $arsiv ? 't.arsivlendi_tarihi IS NOT NULL' : 't.arsivlendi_tarihi IS NULL';
$par = [];
if ($q !== '') {
    $where[] = '(t.teklif_no LIKE ? OR c.firma_adi LIKE ?)';
    $par[] = "%$q%"; $par[] = "%$q%";
}
if ($durum !== '' && in_array($durum, ['taslak','gonderildi','goruldu','onaylandi','reddedildi','iptal'], true)) {
    $where[] = 't.durum = ?';
    $par[] = $durum;
}
if ($pb !== '' && in_array($pb, ['TRY','USD','EUR'], true)) {
    $where[] = 't.para_birimi = ?';
    $par[] = $pb;
}
if ($cari_f > 0) {
    $where[] = 't.cari_id = ?';
    $par[] = $cari_f;
}
$wsql = implode(' AND ', $where);

// Sekme sayımları
$aktifSayi = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('teklif') . ' WHERE arsivlendi_tarihi IS NULL')->fetchColumn();
$arsivSayi = (int)db()->query('SELECT COUNT(*) FROM ' . tbl('teklif') . ' WHERE arsivlendi_tarihi IS NOT NULL')->fetchColumn();

$stc = db()->prepare('SELECT COUNT(*) FROM ' . tbl('teklif') . ' t LEFT JOIN ' . tbl('cari') . ' c ON c.id=t.cari_id WHERE ' . $wsql);
$stc->execute($par);
$total = (int)$stc->fetchColumn();

$offset = ($p - 1) * $per;
$sql = 'SELECT t.*, c.firma_adi, c.cari_kodu
        FROM ' . tbl('teklif') . ' t
        LEFT JOIN ' . tbl('cari') . ' c ON c.id = t.cari_id
        WHERE ' . $wsql . '
        ORDER BY t.id DESC
        LIMIT ' . (int)$per . ' OFFSET ' . (int)$offset;
$st = db()->prepare($sql);
$st->execute($par);
$teklifler = $st->fetchAll();

$cariler = db()->query('SELECT id, firma_adi FROM ' . tbl('cari') . ' WHERE aktif=1 ORDER BY firma_adi')->fetchAll();
?>
<div class="card">
  <div class="card-head">
    <h2>
      <?= $arsiv ? '📦 Arşivlenen Teklifler' : 'Proforma Teklifler' ?>
      <span class="muted" style="font-weight:400">(<?= number_format($total, 0, ',', '.') ?>)</span>
    </h2>
    <div class="page-actions">
      <?php if (!$arsiv): ?>
        <a href="yonetim.php?sayfa=teklif_form" class="btn btn-primary btn-sm">+ Yeni Teklif</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="tabs-wrap">
    <a href="yonetim.php?sayfa=teklif" class="tab<?= !$arsiv ? ' active' : '' ?>">
      📋 Aktif <span class="tab-count"><?= $aktifSayi ?></span>
    </a>
    <a href="yonetim.php?sayfa=teklif&arsiv=1" class="tab<?= $arsiv ? ' active' : '' ?>">
      📦 Arşiv <span class="tab-count"><?= $arsivSayi ?></span>
    </a>
  </div>

  <form method="get" class="filter-bar">
    <input type="hidden" name="sayfa" value="teklif">
    <?php if ($arsiv): ?><input type="hidden" name="arsiv" value="1"><?php endif; ?>
    <div class="field">
      <label>Arama</label>
      <input type="text" name="q" placeholder="Teklif no, firma..." value="<?= e($q) ?>">
    </div>
    <div class="field">
      <label>Durum</label>
      <select name="durum">
        <option value="">Tümü</option>
        <?php foreach (['taslak','gonderildi','goruldu','onaylandi','reddedildi','iptal'] as $d): ?>
          <option value="<?= e($d) ?>" <?= $durum === $d ? 'selected':'' ?>><?= e(tr_durum($d)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Para Birimi</label>
      <select name="pb">
        <option value="">Tümü</option>
        <option value="TRY" <?= $pb==='TRY'?'selected':'' ?>>TRY</option>
        <option value="USD" <?= $pb==='USD'?'selected':'' ?>>USD</option>
        <option value="EUR" <?= $pb==='EUR'?'selected':'' ?>>EUR</option>
      </select>
    </div>
    <div class="field">
      <label>Cari</label>
      <select name="cari">
        <option value="0">Tümü</option>
        <?php foreach ($cariler as $ci): ?>
          <option value="<?= (int)$ci['id'] ?>" <?= $cari_f === (int)$ci['id'] ? 'selected':'' ?>><?= e($ci['firma_adi']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
    <?php if ($q || $durum || $pb || $cari_f): ?>
      <a class="btn btn-ghost btn-sm" href="yonetim.php?sayfa=teklif<?= $arsiv ? '&arsiv=1' : '' ?>">Temizle</a>
    <?php endif; ?>
  </form>

  <?php if (empty($teklifler)): ?>
    <p class="muted" style="padding:30px 0; text-align:center">
      <?= $arsiv ? 'Arşivde teklif yok.' : 'Kayıt bulunamadı.' ?>
    </p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Teklif No</th>
            <th>Cari</th>
            <th>Tarih</th>
            <th>Geçerlilik</th>
            <th class="num">Tutar</th>
            <th>Durum</th>
            <?php if ($arsiv): ?><th>Arşiv</th><?php endif; ?>
            <th class="cen">İşlem</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($teklifler as $t): ?>
          <tr>
            <td><strong><?= e($t['teklif_no']) ?></strong></td>
            <td><?= e($t['firma_adi'] ?? '-') ?></td>
            <td><?= fmt_date($t['tarih']) ?></td>
            <td><?= fmt_date($t['gecerlilik_tarihi']) ?></td>
            <td class="num"><?= e(fmt_money((float)$t['genel_toplam'], $t['para_birimi'])) ?></td>
            <td><?= durum_rozet($t['durum']) ?></td>
            <?php if ($arsiv): ?>
              <td><small class="muted"><?= fmt_datetime($t['arsivlendi_tarihi']) ?></small></td>
            <?php endif; ?>
            <td class="cen actions-tight">
              <a class="btn btn-ghost btn-xs" href="yonetim.php?sayfa=teklif_goruntule&id=<?= (int)$t['id'] ?>" title="Aç">Aç</a>
              <?php if (!$arsiv): ?>
                <a class="btn btn-ghost btn-xs" href="yonetim.php?sayfa=teklif_form&id=<?= (int)$t['id'] ?>" title="Düzenle">✏️</a>
              <?php endif; ?>
              <a class="btn btn-primary btn-xs" href="yonetim.php?sayfa=teklif_pdf&id=<?= (int)$t['id'] ?>&auto=1" target="_blank" title="PDF olarak indir">📄 PDF</a>
              <?php if (!$arsiv): ?>
                <a class="btn btn-accent btn-xs" href="yonetim.php?sayfa=teklif_gonder&id=<?= (int)$t['id'] ?>" title="E-posta ile gönder">📧</a>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="islem" value="arsivle">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-xs" title="Arşive kaldır"
                          data-confirm="<?= e($t['teklif_no']) ?> arşive kaldırılsın mı?">📦</button>
                </form>
              <?php else: ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="islem" value="arsivden_cikar">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button type="submit" class="btn btn-accent btn-xs" title="Arşivden çıkar">↩ Geri Al</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="islem" value="sil">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn btn-danger btn-xs" title="Kalıcı sil"
                        data-confirm="<?= e($t['teklif_no']) ?> KALICI olarak silinsin mi?">🗑️</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($total, $p, $per, 'yonetim.php?sayfa=teklif' . ($arsiv ? '&arsiv=1' : '') . '&q=' . urlencode($q) . '&durum=' . urlencode($durum) . '&pb=' . urlencode($pb) . '&cari=' . (int)$cari_f) ?>
  <?php endif; ?>
</div>
