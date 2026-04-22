<?php
/**
 * Banka Hesabı Ekle / Düzenle
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$id = int_param(get_param('id', 0));
$duzenle = $id > 0;
$page_title = $duzenle ? 'Banka Hesabı Düzenle' : 'Yeni Banka Hesabı';

$h = [
    'id' => 0, 'banka_adi' => '', 'sube_adi' => '', 'sube_kodu' => '',
    'hesap_sahibi' => '', 'hesap_no' => '', 'iban' => '', 'swift' => '',
    'para_birimi' => 'TRY', 'sira' => 0, 'aktif' => 1, 'notlar' => '',
];

if ($duzenle) {
    $st = db()->prepare('SELECT * FROM ' . tbl('banka_hesap') . ' WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('error', 'Banka hesabı bulunamadı.');
        redirect('yonetim.php?sayfa=banka');
    }
    $h = array_merge($h, $row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $h['banka_adi']    = trim((string)post('banka_adi', ''));
    $h['sube_adi']     = trim((string)post('sube_adi', ''));
    $h['sube_kodu']    = trim((string)post('sube_kodu', ''));
    $h['hesap_sahibi'] = trim((string)post('hesap_sahibi', ''));
    $h['hesap_no']     = trim((string)post('hesap_no', ''));
    $h['iban']         = strtoupper(str_replace(' ', '', trim((string)post('iban', ''))));
    $h['swift']        = strtoupper(str_replace(' ', '', trim((string)post('swift', ''))));
    $h['para_birimi']  = in_array(post('para_birimi'), ['TRY','USD','EUR'], true) ? post('para_birimi') : 'TRY';
    $h['sira']         = int_param(post('sira', 0));
    $h['aktif']        = post('aktif') ? 1 : 0;
    $h['notlar']       = trim((string)post('notlar', ''));

    $err = [];
    if ($h['banka_adi']    === '') $err[] = 'Banka adı zorunludur.';
    if ($h['hesap_sahibi'] === '') $err[] = 'Hesap sahibi zorunludur.';
    if ($h['iban'] !== '' && !preg_match('/^[A-Z0-9]{15,34}$/', $h['iban'])) {
        $err[] = 'IBAN formatı geçersiz (15-34 karakter, yalnız harf/rakam).';
    }
    if ($h['swift'] !== '' && !preg_match('/^[A-Z0-9]{8,11}$/', $h['swift'])) {
        $err[] = 'SWIFT kodu geçersiz (8-11 karakter).';
    }

    if (empty($err)) {
        if ($duzenle) {
            $sql = 'UPDATE ' . tbl('banka_hesap') . ' SET
                banka_adi=?, sube_adi=?, sube_kodu=?, hesap_sahibi=?, hesap_no=?,
                iban=?, swift=?, para_birimi=?, sira=?, aktif=?, notlar=?,
                guncelleme_tarihi=NOW()
                WHERE id=?';
            $st = db()->prepare($sql);
            $st->execute([
                $h['banka_adi'], $h['sube_adi'], $h['sube_kodu'], $h['hesap_sahibi'], $h['hesap_no'],
                $h['iban'], $h['swift'], $h['para_birimi'], $h['sira'], $h['aktif'], $h['notlar'],
                $id
            ]);
            log_yaz('guncelle', 'banka', $id, $h['banka_adi']);
            flash('success', 'Banka hesabı güncellendi.');
        } else {
            $sql = 'INSERT INTO ' . tbl('banka_hesap') . '
                (banka_adi, sube_adi, sube_kodu, hesap_sahibi, hesap_no,
                 iban, swift, para_birimi, sira, aktif, notlar,
                 olusturan_id, olusturma_tarihi)
                VALUES (?,?,?,?,?, ?,?,?,?,?,?, ?, NOW())';
            $st = db()->prepare($sql);
            $st->execute([
                $h['banka_adi'], $h['sube_adi'], $h['sube_kodu'], $h['hesap_sahibi'], $h['hesap_no'],
                $h['iban'], $h['swift'], $h['para_birimi'], $h['sira'], $h['aktif'], $h['notlar'],
                current_user()['id'] ?? null,
            ]);
            $nid = (int)db()->lastInsertId();
            log_yaz('ekle', 'banka', $nid, $h['banka_adi']);
            flash('success', 'Banka hesabı eklendi.');
        }
        redirect('yonetim.php?sayfa=banka');
    } else {
        flash('error', implode(' ', $err));
    }
}

// Türkiye'nin yaygın bankaları için autocomplete listesi
$bankalar = [
    'Ziraat Bankası', 'Vakıfbank', 'Halkbank', 'İş Bankası', 'Garanti BBVA',
    'Yapı Kredi', 'Akbank', 'QNB Finansbank', 'Denizbank', 'TEB', 'ING',
    'HSBC', 'Şekerbank', 'Albaraka Türk', 'Kuveyt Türk', 'Türkiye Finans',
    'Ziraat Katılım', 'Vakıf Katılım', 'Emlak Katılım', 'Odeabank', 'Anadolubank',
    'Fibabanka', 'Burgan Bank', 'ICBC Turkey', 'Alternatif Bank',
];
?>
<form method="post" class="card" autocomplete="off">
  <?= csrf_field() ?>
  <div class="card-head">
    <h2><?= $duzenle ? 'Banka Hesabı Düzenle' : 'Yeni Banka Hesabı' ?></h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=banka" class="btn btn-ghost btn-sm">← Listeye Dön</a>
    </div>
  </div>

  <div class="form-row">
    <div class="field">
      <label>Banka Adı <span style="color:var(--danger)">*</span></label>
      <input type="text" name="banka_adi" list="bankalar_list" required value="<?= e($h['banka_adi']) ?>">
      <datalist id="bankalar_list">
        <?php foreach ($bankalar as $b): ?>
          <option value="<?= e($b) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>
    <div class="field">
      <label>Para Birimi <span style="color:var(--danger)">*</span></label>
      <select name="para_birimi" required>
        <option value="TRY" <?= $h['para_birimi']==='TRY' ? 'selected':'' ?>>TRY - Türk Lirası</option>
        <option value="USD" <?= $h['para_birimi']==='USD' ? 'selected':'' ?>>USD - Dolar</option>
        <option value="EUR" <?= $h['para_birimi']==='EUR' ? 'selected':'' ?>>EUR - Euro</option>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="field">
      <label>Şube Adı</label>
      <input type="text" name="sube_adi" value="<?= e($h['sube_adi']) ?>" placeholder="örn. Selçuklu Şubesi">
    </div>
    <div class="field">
      <label>Şube Kodu</label>
      <input type="text" name="sube_kodu" value="<?= e($h['sube_kodu']) ?>" placeholder="örn. 1234">
    </div>
  </div>

  <div class="form-row">
    <div class="field">
      <label>Hesap Sahibi <span style="color:var(--danger)">*</span></label>
      <input type="text" name="hesap_sahibi" required value="<?= e($h['hesap_sahibi'] ?: ayar_get('sirket_adi', '')) ?>">
    </div>
    <div class="field">
      <label>Hesap Numarası</label>
      <input type="text" name="hesap_no" value="<?= e($h['hesap_no']) ?>" placeholder="opsiyonel">
    </div>
  </div>

  <div class="form-row col-1">
    <div class="field">
      <label>IBAN <span class="muted">(boşluklar otomatik temizlenir)</span></label>
      <input type="text" name="iban" value="<?= e($h['iban']) ?>" placeholder="TR00 0000 0000 0000 0000 0000 00" style="font-family: monospace; letter-spacing: 1px" maxlength="50">
    </div>
  </div>

  <div class="form-row col-3">
    <div class="field">
      <label>SWIFT / BIC Kodu</label>
      <input type="text" name="swift" value="<?= e($h['swift']) ?>" placeholder="örn. TGBATRISXXX" style="font-family: monospace" maxlength="11">
    </div>
    <div class="field">
      <label>Sıra <span class="muted">(liste ve PDF sıralaması için)</span></label>
      <input type="number" name="sira" value="<?= e((string)$h['sira']) ?>" min="0">
    </div>
    <div class="field">
      <label>Durum</label>
      <select name="aktif">
        <option value="1" <?= $h['aktif'] ? 'selected':'' ?>>Aktif</option>
        <option value="0" <?= !$h['aktif'] ? 'selected':'' ?>>Pasif</option>
      </select>
    </div>
  </div>

  <div class="form-row col-1">
    <div class="field">
      <label>Notlar</label>
      <textarea name="notlar" rows="2" placeholder="İstenirse ek açıklama (örn. 'USD havaleler için', 'sadece dış müşteriler' gibi)"><?= e($h['notlar']) ?></textarea>
    </div>
  </div>

  <div class="form-actions">
    <a href="yonetim.php?sayfa=banka" class="btn btn-ghost">İptal</a>
    <button type="submit" class="btn btn-primary"><?= $duzenle ? 'Güncelle' : 'Kaydet' ?></button>
  </div>
</form>
