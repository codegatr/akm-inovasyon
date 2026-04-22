<?php
/**
 * Cari Kartları - Liste
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$page_title = 'Cari Kartları';

// Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('islem') === 'sil') {
    require_csrf();
    $id = int_param(post('id'));
    if ($id > 0) {
        // Tekliflerde kullanımda mı?
        $used = (int)db()->prepare('SELECT COUNT(*) FROM ' . tbl('teklif') . ' WHERE cari_id = ?')
                         ->execute([$id]) ?: 0;
        $st = db()->prepare('SELECT COUNT(*) FROM ' . tbl('teklif') . ' WHERE cari_id = ?');
        $st->execute([$id]);
        $used = (int)$st->fetchColumn();
        if ($used > 0) {
            flash('error', 'Bu cariye ait ' . $used . ' teklif var. Önce teklifleri silmelisiniz.');
        } else {
            db()->prepare('DELETE FROM ' . tbl('cari') . ' WHERE id = ?')->execute([$id]);
            log_yaz('sil', 'cari', $id, 'Cari silindi');
            flash('success', 'Cari silindi.');
        }
    }
    redirect('yonetim.php?sayfa=cari');
}

// Filtre
$q    = trim((string)get_param('q', ''));
$tip  = (string)get_param('tip', '');
$p    = max(1, int_param(get_param('p', 1), 1));
$per  = 20;

$where = ['1=1'];
$par   = [];
if ($q !== '') {
    $where[] = '(firma_adi LIKE ? OR cari_kodu LIKE ? OR email LIKE ? OR telefon LIKE ? OR yetkili_adi LIKE ?)';
    $par[] = "%$q%"; $par[] = "%$q%"; $par[] = "%$q%"; $par[] = "%$q%"; $par[] = "%$q%";
}
if ($tip !== '' && in_array($tip, ['musteri','tedarikci','ikisi'], true)) {
    $where[] = 'tip = ?';
    $par[]   = $tip;
}
$wsql = implode(' AND ', $where);

$stc = db()->prepare('SELECT COUNT(*) FROM ' . tbl('cari') . ' WHERE ' . $wsql);
$stc->execute($par);
$total = (int)$stc->fetchColumn();

$offset = ($p - 1) * $per;
$sql = 'SELECT * FROM ' . tbl('cari') . ' WHERE ' . $wsql . ' ORDER BY id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)$offset;
$st = db()->prepare($sql);
$st->execute($par);
$cariler = $st->fetchAll();
?>
<div class="card">
  <div class="card-head">
    <h2>Cari Kartları <span class="muted" style="font-weight:400">(<?= number_format($total, 0, ',', '.') ?>)</span></h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=cari_form" class="btn btn-primary btn-sm">+ Yeni Cari</a>
    </div>
  </div>

  <form method="get" class="filter-bar">
    <input type="hidden" name="sayfa" value="cari">
    <div class="field">
      <label>Arama</label>
      <input type="text" name="q" placeholder="Firma, kod, e-posta..." value="<?= e($q) ?>">
    </div>
    <div class="field">
      <label>Tip</label>
      <select name="tip">
        <option value="">Tümü</option>
        <option value="musteri"   <?= $tip === 'musteri'   ? 'selected' : '' ?>>Müşteri</option>
        <option value="tedarikci" <?= $tip === 'tedarikci' ? 'selected' : '' ?>>Tedarikçi</option>
        <option value="ikisi"     <?= $tip === 'ikisi'     ? 'selected' : '' ?>>Her İkisi</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
    <?php if ($q !== '' || $tip !== ''): ?>
      <a class="btn btn-ghost btn-sm" href="yonetim.php?sayfa=cari">Temizle</a>
    <?php endif; ?>
  </form>

  <?php if (empty($cariler)): ?>
    <p class="muted">Kayıt bulunamadı.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Kod</th>
            <th>Firma Adı</th>
            <th>Yetkili</th>
            <th>E-posta</th>
            <th>Telefon</th>
            <th>Tip</th>
            <th>PB</th>
            <th class="cen">İşlem</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($cariler as $c): ?>
          <tr>
            <td><strong><?= e($c['cari_kodu']) ?></strong></td>
            <td><?= e($c['firma_adi']) ?><?php if (!$c['aktif']): ?> <span class="badge badge-gray">Pasif</span><?php endif; ?></td>
            <td><?= e($c['yetkili_adi']) ?></td>
            <td><?php if ($c['email']): ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php endif; ?></td>
            <td><?= e($c['telefon']) ?></td>
            <td>
              <?php
                $tipCls = [
                  'musteri' => ['Müşteri', 'badge-blue'],
                  'tedarikci' => ['Tedarikçi', 'badge-amber'],
                  'ikisi' => ['İkisi', 'badge-teal'],
                ][$c['tip']] ?? [$c['tip'], 'badge-gray'];
              ?>
              <span class="badge <?= $tipCls[1] ?>"><?= e($tipCls[0]) ?></span>
            </td>
            <td><?= e($c['para_birimi']) ?></td>
            <td class="cen">
              <a class="btn btn-ghost btn-xs" href="yonetim.php?sayfa=cari_form&id=<?= (int)$c['id'] ?>">Düzenle</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="islem" value="sil">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="btn btn-danger btn-xs" data-confirm="<?= e($c['firma_adi']) ?> silinsin mi?">Sil</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($total, $p, $per, 'yonetim.php?sayfa=cari&q=' . urlencode($q) . '&tip=' . urlencode($tip)) ?>
  <?php endif; ?>
</div>
