<?php
/**
 * Kullanıcı Ekle / Düzenle
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$id = int_param(get_param('id', 0));
$duzenle = $id > 0;
$page_title = $duzenle ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı';

$u = ['id' => 0, 'username' => '', 'ad_soyad' => '', 'email' => '', 'rol' => 'kullanici', 'aktif' => 1];

if ($duzenle) {
    $st = db()->prepare('SELECT * FROM ' . tbl('users') . ' WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { flash('error', 'Kullanıcı bulunamadı.'); redirect('yonetim.php?sayfa=kullanici'); }
    $u = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $u['username'] = trim((string)post('username', ''));
    $u['ad_soyad'] = trim((string)post('ad_soyad', ''));
    $u['email']    = trim((string)post('email', ''));
    $u['rol']      = in_array(post('rol'), ['admin','kullanici'], true) ? post('rol') : 'kullanici';
    $u['aktif']    = post('aktif') ? 1 : 0;
    $sifre         = (string)post('sifre', '');
    $sifre2        = (string)post('sifre2', '');

    $err = [];
    if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $u['username'])) $err[] = 'Kullanıcı adı 3-32 karakter ve yalnızca harf, rakam, . _ - olmalı.';
    if ($u['ad_soyad'] === '') $err[] = 'Ad Soyad zorunludur.';
    if (!filter_var($u['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Geçerli e-posta girin.';

    if (!$duzenle || $sifre !== '') {
        if (strlen($sifre) < 6) $err[] = 'Şifre en az 6 karakter olmalı.';
        if ($sifre !== $sifre2) $err[] = 'Şifreler uyuşmuyor.';
    }

    // Kendi rolümüzü / aktifliğimizi değiştirmeyi engelle
    if ($duzenle && (int)$u['id'] === (int)current_user()['id']) {
        $u['rol']   = current_user()['rol'];
        $u['aktif'] = 1;
    }

    if (empty($err)) {
        try {
            if ($duzenle) {
                $sql = 'UPDATE ' . tbl('users') . ' SET
                        username=?, ad_soyad=?, email=?, rol=?, aktif=?, guncelleme_tarihi=NOW()';
                $par = [$u['username'], $u['ad_soyad'], $u['email'], $u['rol'], $u['aktif']];
                if ($sifre !== '') {
                    $sql .= ', password_hash=?';
                    $par[] = password_hash($sifre, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id=?';
                $par[] = $id;
                db()->prepare($sql)->execute($par);
                log_yaz('guncelle', 'kullanici', $id, $u['username']);
                flash('success', 'Kullanıcı güncellendi.');
            } else {
                $sql = 'INSERT INTO ' . tbl('users') . '
                    (username, password_hash, ad_soyad, email, rol, aktif, olusturma_tarihi)
                    VALUES (?,?,?,?,?,?,NOW())';
                db()->prepare($sql)->execute([
                    $u['username'],
                    password_hash($sifre, PASSWORD_DEFAULT),
                    $u['ad_soyad'], $u['email'], $u['rol'], $u['aktif']
                ]);
                $nid = (int)db()->lastInsertId();
                log_yaz('ekle', 'kullanici', $nid, $u['username']);
                flash('success', 'Kullanıcı eklendi.');
            }
            redirect('yonetim.php?sayfa=kullanici');
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                flash('error', 'Kullanıcı adı veya e-posta zaten kullanımda.');
            } else {
                flash('error', 'Hata: ' . $e->getMessage());
            }
        }
    } else {
        flash('error', implode(' ', $err));
    }
}
?>
<form method="post" class="card" autocomplete="off">
  <?= csrf_field() ?>
  <div class="card-head">
    <h2><?= $duzenle ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı' ?></h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=kullanici" class="btn btn-ghost btn-sm">← Listeye Dön</a>
    </div>
  </div>

  <div class="form-row">
    <div class="field">
      <label>Kullanıcı Adı <span style="color:var(--danger)">*</span></label>
      <input type="text" name="username" required value="<?= e($u['username']) ?>">
    </div>
    <div class="field">
      <label>Ad Soyad <span style="color:var(--danger)">*</span></label>
      <input type="text" name="ad_soyad" required value="<?= e($u['ad_soyad']) ?>">
    </div>
  </div>

  <div class="form-row col-3">
    <div class="field" style="grid-column: span 2">
      <label>E-posta <span style="color:var(--danger)">*</span></label>
      <input type="email" name="email" required value="<?= e($u['email']) ?>">
    </div>
    <div class="field">
      <label>Rol</label>
      <select name="rol" <?= $duzenle && (int)$u['id'] === (int)current_user()['id'] ? 'disabled' : '' ?>>
        <option value="kullanici" <?= $u['rol']==='kullanici' ? 'selected':'' ?>>Kullanıcı</option>
        <option value="admin"     <?= $u['rol']==='admin' ? 'selected':'' ?>>Yönetici</option>
      </select>
      <?php if ($duzenle && (int)$u['id'] === (int)current_user()['id']): ?>
        <input type="hidden" name="rol" value="<?= e($u['rol']) ?>">
      <?php endif; ?>
    </div>
  </div>

  <div class="form-row">
    <div class="field">
      <label>Şifre <?= $duzenle ? '<span class="muted">(değiştirmek için doldurun)</span>' : '<span style="color:var(--danger)">*</span>' ?></label>
      <input type="password" name="sifre" <?= $duzenle ? '' : 'required' ?>>
    </div>
    <div class="field">
      <label>Şifre (Tekrar)</label>
      <input type="password" name="sifre2" <?= $duzenle ? '' : 'required' ?>>
    </div>
  </div>

  <div class="form-row col-1">
    <div class="field">
      <label><input type="checkbox" name="aktif" value="1" <?= $u['aktif'] ? 'checked':'' ?>
             <?= $duzenle && (int)$u['id'] === (int)current_user()['id'] ? 'disabled' : '' ?>> Hesap aktif</label>
      <?php if ($duzenle && (int)$u['id'] === (int)current_user()['id']): ?>
        <input type="hidden" name="aktif" value="1">
        <small class="muted">Kendi hesabınızı pasifleştiremezsiniz.</small>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-actions">
    <a href="yonetim.php?sayfa=kullanici" class="btn btn-ghost">İptal</a>
    <button type="submit" class="btn btn-primary"><?= $duzenle ? 'Güncelle' : 'Kaydet' ?></button>
  </div>
</form>
