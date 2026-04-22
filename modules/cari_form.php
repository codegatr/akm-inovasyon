<?php
/**
 * Cari Ekle / Düzenle
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$id = int_param(get_param('id', 0));
$duzenle = $id > 0;
$page_title = $duzenle ? 'Cari Düzenle' : 'Yeni Cari';

$c = [
    'id' => 0, 'cari_kodu' => '', 'firma_adi' => '', 'yetkili_adi' => '',
    'email' => '', 'telefon' => '', 'adres' => '',
    'ulke' => 'Türkiye', 'sehir' => '', 'vkn_tckn' => '', 'vergi_dairesi' => '',
    'tip' => 'musteri', 'para_birimi' => 'TRY', 'notlar' => '', 'aktif' => 1,
];

if ($duzenle) {
    $st = db()->prepare('SELECT * FROM ' . tbl('cari') . ' WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('error', 'Cari bulunamadı.');
        redirect('yonetim.php?sayfa=cari');
    }
    $c = array_merge($c, $row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $c['cari_kodu']     = trim((string)post('cari_kodu', ''));
    $c['firma_adi']     = trim((string)post('firma_adi', ''));
    $c['yetkili_adi']   = trim((string)post('yetkili_adi', ''));
    $c['email']         = trim((string)post('email', ''));
    $c['telefon']       = trim((string)post('telefon', ''));
    $c['adres']         = trim((string)post('adres', ''));
    $c['ulke']          = trim((string)post('ulke', 'Türkiye'));
    $c['sehir']         = trim((string)post('sehir', ''));
    $c['vkn_tckn']      = trim((string)post('vkn_tckn', ''));
    $c['vergi_dairesi'] = trim((string)post('vergi_dairesi', ''));
    $c['tip']           = in_array(post('tip'), ['musteri','tedarikci','ikisi'], true) ? post('tip') : 'musteri';
    $c['para_birimi']   = in_array(post('para_birimi'), ['TRY','USD','EUR'], true) ? post('para_birimi') : 'TRY';
    $c['notlar']        = trim((string)post('notlar', ''));
    $c['aktif']         = post('aktif') ? 1 : 0;

    $err = [];
    if ($c['firma_adi'] === '')  $err[] = 'Firma adı zorunludur.';
    if ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'E-posta geçersiz.';

    if (empty($err)) {
        try {
            if ($c['cari_kodu'] === '') $c['cari_kodu'] = generate_cari_kodu();

            if ($duzenle) {
                $sql = 'UPDATE ' . tbl('cari') . ' SET
                    cari_kodu=?, firma_adi=?, yetkili_adi=?, email=?, telefon=?, adres=?,
                    ulke=?, sehir=?, vkn_tckn=?, vergi_dairesi=?, tip=?, para_birimi=?,
                    notlar=?, aktif=?, guncelleme_tarihi=NOW()
                    WHERE id=?';
                $st = db()->prepare($sql);
                $st->execute([
                    $c['cari_kodu'], $c['firma_adi'], $c['yetkili_adi'], $c['email'], $c['telefon'], $c['adres'],
                    $c['ulke'], $c['sehir'], $c['vkn_tckn'], $c['vergi_dairesi'], $c['tip'], $c['para_birimi'],
                    $c['notlar'], $c['aktif'], $id
                ]);
                log_yaz('guncelle', 'cari', $id, $c['firma_adi']);
                flash('success', 'Cari güncellendi.');
            } else {
                $sql = 'INSERT INTO ' . tbl('cari') . '
                    (cari_kodu, firma_adi, yetkili_adi, email, telefon, adres, ulke, sehir,
                     vkn_tckn, vergi_dairesi, tip, para_birimi, notlar, aktif,
                     olusturan_id, olusturma_tarihi)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())';
                $st = db()->prepare($sql);
                $st->execute([
                    $c['cari_kodu'], $c['firma_adi'], $c['yetkili_adi'], $c['email'], $c['telefon'], $c['adres'],
                    $c['ulke'], $c['sehir'], $c['vkn_tckn'], $c['vergi_dairesi'], $c['tip'], $c['para_birimi'],
                    $c['notlar'], $c['aktif'], current_user()['id'] ?? null
                ]);
                $newId = (int)db()->lastInsertId();
                log_yaz('ekle', 'cari', $newId, $c['firma_adi']);
                flash('success', 'Cari eklendi.');
            }
            redirect('yonetim.php?sayfa=cari');
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                flash('error', 'Bu cari kodu zaten kullanımda.');
            } else {
                flash('error', 'Kayıt hatası: ' . $e->getMessage());
            }
        }
    } else {
        flash('error', implode(' ', $err));
    }
}

$ulkeler = ['Türkiye','Almanya','ABD','İngiltere','Fransa','İtalya','İspanya','Hollanda','Belçika','Rusya','Çin','Japonya','Suudi Arabistan','BAE','Irak','İran','Azerbaycan','Kazakistan','Özbekistan','Mısır','Libya','Cezayir','Fas','Diğer'];
?>
<form method="post" class="card" autocomplete="off">
  <?= csrf_field() ?>
  <div class="card-head">
    <h2><?= $duzenle ? 'Cari Düzenle' : 'Yeni Cari' ?></h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=cari" class="btn btn-ghost btn-sm">← Listeye Dön</a>
    </div>
  </div>

  <div class="form-row col-3">
    <div class="field">
      <label>Cari Kodu <span class="muted">(boş bırakılırsa otomatik)</span></label>
      <input type="text" name="cari_kodu" value="<?= e($c['cari_kodu']) ?>" placeholder="C00001">
    </div>
    <div class="field">
      <label>Tip <span style="color:var(--danger)">*</span></label>
      <select name="tip">
        <option value="musteri"   <?= $c['tip']==='musteri' ? 'selected':'' ?>>Müşteri</option>
        <option value="tedarikci" <?= $c['tip']==='tedarikci' ? 'selected':'' ?>>Tedarikçi</option>
        <option value="ikisi"     <?= $c['tip']==='ikisi' ? 'selected':'' ?>>Her İkisi</option>
      </select>
    </div>
    <div class="field">
      <label>Varsayılan Para Birimi</label>
      <select name="para_birimi">
        <option value="TRY" <?= $c['para_birimi']==='TRY' ? 'selected':'' ?>>TRY - Türk Lirası</option>
        <option value="USD" <?= $c['para_birimi']==='USD' ? 'selected':'' ?>>USD - Dolar</option>
        <option value="EUR" <?= $c['para_birimi']==='EUR' ? 'selected':'' ?>>EUR - Euro</option>
      </select>
    </div>
  </div>

  <div class="form-row col-1">
    <div class="field">
      <label>Firma Adı <span style="color:var(--danger)">*</span></label>
      <input type="text" name="firma_adi" value="<?= e($c['firma_adi']) ?>" required>
    </div>
  </div>

  <div class="form-row">
    <div class="field">
      <label>Yetkili Adı Soyadı</label>
      <input type="text" name="yetkili_adi" value="<?= e($c['yetkili_adi']) ?>">
    </div>
    <div class="field">
      <label>E-posta</label>
      <input type="email" name="email" value="<?= e($c['email']) ?>">
    </div>
  </div>

  <div class="form-row col-3">
    <div class="field">
      <label>Telefon</label>
      <input type="tel" name="telefon" value="<?= e($c['telefon']) ?>">
    </div>
    <div class="field">
      <label>VKN / TCKN</label>
      <input type="text" name="vkn_tckn" value="<?= e($c['vkn_tckn']) ?>">
    </div>
    <div class="field">
      <label>Vergi Dairesi</label>
      <input type="text" name="vergi_dairesi" value="<?= e($c['vergi_dairesi']) ?>">
    </div>
  </div>

  <div class="form-row col-3">
    <div class="field">
      <label>Ülke</label>
      <select name="ulke">
        <?php foreach ($ulkeler as $u): ?>
          <option value="<?= e($u) ?>" <?= $c['ulke'] === $u ? 'selected':'' ?>><?= e($u) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Şehir</label>
      <input type="text" name="sehir" value="<?= e($c['sehir']) ?>">
    </div>
    <div class="field">
      <label>Durum</label>
      <select name="aktif">
        <option value="1" <?= $c['aktif'] ? 'selected':'' ?>>Aktif</option>
        <option value="0" <?= !$c['aktif'] ? 'selected':'' ?>>Pasif</option>
      </select>
    </div>
  </div>

  <div class="form-row col-1">
    <div class="field">
      <label>Adres</label>
      <textarea name="adres" rows="2"><?= e($c['adres']) ?></textarea>
    </div>
  </div>

  <div class="form-row col-1">
    <div class="field">
      <label>Notlar</label>
      <textarea name="notlar" rows="3"><?= e($c['notlar']) ?></textarea>
    </div>
  </div>

  <div class="form-actions">
    <a href="yonetim.php?sayfa=cari" class="btn btn-ghost">İptal</a>
    <button type="submit" class="btn btn-primary"><?= $duzenle ? 'Güncelle' : 'Kaydet' ?></button>
  </div>
</form>
