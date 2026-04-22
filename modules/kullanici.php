<?php
/**
 * Kullanıcı Yönetimi - Liste
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$page_title = 'Kullanıcılar';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('islem') === 'sil') {
    require_csrf();
    $id = int_param(post('id'));
    if ($id > 0 && $id !== (int)(current_user()['id'] ?? 0)) {
        db()->prepare('DELETE FROM ' . tbl('users') . ' WHERE id = ?')->execute([$id]);
        log_yaz('sil', 'kullanici', $id, 'Kullanıcı silindi');
        flash('success', 'Kullanıcı silindi.');
    } else {
        flash('error', 'Kendi hesabınızı silemezsiniz.');
    }
    redirect('yonetim.php?sayfa=kullanici');
}

$users = db()->query('SELECT * FROM ' . tbl('users') . ' ORDER BY id DESC')->fetchAll();
?>
<div class="card">
  <div class="card-head">
    <h2>Kullanıcılar</h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=kullanici_form" class="btn btn-primary btn-sm">+ Yeni Kullanıcı</a>
    </div>
  </div>
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Kullanıcı Adı</th>
          <th>Ad Soyad</th>
          <th>E-posta</th>
          <th>Rol</th>
          <th>Durum</th>
          <th>Son Giriş</th>
          <th class="cen">İşlem</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['username']) ?></strong><?= (int)$u['id'] === (int)current_user()['id'] ? ' <span class="badge badge-teal">Siz</span>' : '' ?></td>
          <td><?= e($u['ad_soyad']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td>
            <?php if ($u['rol'] === 'admin'): ?>
              <span class="badge badge-blue">Yönetici</span>
            <?php else: ?>
              <span class="badge badge-gray">Kullanıcı</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($u['aktif']): ?>
              <span class="badge badge-green">Aktif</span>
            <?php else: ?>
              <span class="badge badge-red">Pasif</span>
            <?php endif; ?>
          </td>
          <td><?= fmt_datetime($u['son_giris']) ?></td>
          <td class="cen">
            <a class="btn btn-ghost btn-xs" href="yonetim.php?sayfa=kullanici_form&id=<?= (int)$u['id'] ?>">Düzenle</a>
            <?php if ((int)$u['id'] !== (int)current_user()['id']): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="islem" value="sil">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-xs" data-confirm="<?= e($u['username']) ?> silinsin mi?">Sil</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
