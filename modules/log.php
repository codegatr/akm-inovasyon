<?php
/**
 * İşlem Günlüğü
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$page_title = 'İşlem Günlüğü';

// Temizle (30 günden eski)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('islem') === 'temizle') {
    require_csrf();
    $gun = max(7, int_param(post('gun', 30)));
    $st = db()->prepare('DELETE FROM ' . tbl('log') . ' WHERE tarih < DATE_SUB(NOW(), INTERVAL ? DAY)');
    $st->execute([$gun]);
    $silinen = $st->rowCount();
    flash('success', $silinen . ' kayıt silindi.');
    redirect('yonetim.php?sayfa=log');
}

$modul = (string)get_param('modul', '');
$q     = trim((string)get_param('q', ''));
$p     = max(1, int_param(get_param('p', 1), 1));
$per   = 50;

$where = ['1=1']; $par = [];
if ($modul !== '') { $where[] = 'modul = ?'; $par[] = $modul; }
if ($q !== '')      { $where[] = '(kullanici_adi LIKE ? OR islem LIKE ? OR aciklama LIKE ? OR ip LIKE ?)';
                      $par[] = "%$q%"; $par[] = "%$q%"; $par[] = "%$q%"; $par[] = "%$q%"; }
$wsql = implode(' AND ', $where);

$stc = db()->prepare('SELECT COUNT(*) FROM ' . tbl('log') . ' WHERE ' . $wsql);
$stc->execute($par);
$total = (int)$stc->fetchColumn();

$offset = ($p - 1) * $per;
$sql = 'SELECT * FROM ' . tbl('log') . ' WHERE ' . $wsql . ' ORDER BY id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)$offset;
$st = db()->prepare($sql);
$st->execute($par);
$loglar = $st->fetchAll();

$moduller = db()->query('SELECT DISTINCT modul FROM ' . tbl('log') . ' WHERE modul <> "" ORDER BY modul')->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="card">
  <div class="card-head">
    <h2>İşlem Günlüğü <span class="muted" style="font-weight:400">(<?= number_format($total, 0, ',', '.') ?>)</span></h2>
    <div class="page-actions">
      <form method="post" style="display:flex; gap:6px; align-items:center">
        <?= csrf_field() ?>
        <input type="hidden" name="islem" value="temizle">
        <input type="number" name="gun" value="30" min="7" style="width:70px; padding:5px 8px; border:1px solid var(--border); border-radius:5px; font-size:12px">
        <button type="submit" class="btn btn-danger btn-xs" data-confirm="Belirtilen günden eski loglar silinecek. Devam edilsin mi?">Eskileri Sil</button>
      </form>
    </div>
  </div>

  <form method="get" class="filter-bar">
    <input type="hidden" name="sayfa" value="log">
    <div class="field">
      <label>Arama</label>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Kullanıcı, işlem, IP...">
    </div>
    <div class="field">
      <label>Modül</label>
      <select name="modul">
        <option value="">Tümü</option>
        <?php foreach ($moduller as $m): ?>
          <option value="<?= e($m) ?>" <?= $modul === $m ? 'selected':'' ?>><?= e($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filtrele</button>
  </form>

  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Tarih</th>
          <th>Kullanıcı</th>
          <th>Modül</th>
          <th>İşlem</th>
          <th>Açıklama</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($loglar as $l): ?>
        <tr>
          <td><?= fmt_datetime($l['tarih']) ?></td>
          <td><?= e($l['kullanici_adi'] ?: '-') ?></td>
          <td><span class="badge badge-gray"><?= e($l['modul'] ?: '-') ?></span></td>
          <td><?= e($l['islem']) ?></td>
          <td><?= e($l['aciklama']) ?></td>
          <td><small class="muted"><?= e($l['ip']) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pagination($total, $p, $per, 'yonetim.php?sayfa=log&q=' . urlencode($q) . '&modul=' . urlencode($modul)) ?>
</div>
