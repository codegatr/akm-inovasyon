<?php
/**
 * Ayarlar: Şirket Bilgileri, E-posta (Gmail SMTP), Teklif No, Döviz
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

require_once ROOT_PATH . '/includes/mailer.php';

$page_title = 'Ayarlar';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $tip = post('islem');

    if ($tip === 'sirket') {
        ayar_set('sirket_adi',         trim((string)post('sirket_adi', '')));
        ayar_set('sirket_adres',       trim((string)post('sirket_adres', '')));
        ayar_set('sirket_telefon',     trim((string)post('sirket_telefon', '')));
        ayar_set('sirket_email',       trim((string)post('sirket_email', '')));
        ayar_set('sirket_web',         trim((string)post('sirket_web', '')));
        ayar_set('sirket_vkn',         trim((string)post('sirket_vkn', '')));
        ayar_set('sirket_vergi_dairesi', trim((string)post('sirket_vergi_dairesi', '')));

        // Logo yükleme
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['logo_file'];
            $allowed = [
                'image/png'     => 'png',
                'image/jpeg'    => 'jpg',
                'image/webp'    => 'webp',
                'image/svg+xml' => 'svg',
            ];
            $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : ($f['type'] ?? '');
            if (!isset($allowed[$mime])) {
                flash('error', 'Sadece PNG, JPG, WebP veya SVG yüklenebilir. (Algılanan: ' . e($mime) . ')');
                redirect('yonetim.php?sayfa=ayarlar#sirket');
            }
            if ($f['size'] > 2 * 1024 * 1024) {
                flash('error', 'Logo dosyası 2MB\'dan büyük olamaz.');
                redirect('yonetim.php?sayfa=ayarlar#sirket');
            }
            $ext = $allowed[$mime];
            $dest = ROOT_PATH . '/uploads/logo.' . $ext;
            // Eski uzantılardaki logoları temizle
            foreach (['png','jpg','jpeg','webp','svg'] as $oe) {
                if ($oe === $ext) continue;
                $old = ROOT_PATH . '/uploads/logo.' . $oe;
                if (is_file($old)) @unlink($old);
            }
            if (!@move_uploaded_file($f['tmp_name'], $dest)) {
                flash('error', 'Logo kaydedilemedi. uploads/ klasörü yazılabilir mi?');
                redirect('yonetim.php?sayfa=ayarlar#sirket');
            }
            @chmod($dest, 0644);
            ayar_set('sirket_logo', 'uploads/logo.' . $ext);
            log_yaz('logo_yukle', 'ayarlar', null, $dest);
        }

        // Logo silme
        if (post('logo_sil')) {
            $mevcut = ayar_get('sirket_logo', '');
            if ($mevcut && is_file(ROOT_PATH . '/' . $mevcut)) {
                @unlink(ROOT_PATH . '/' . $mevcut);
            }
            ayar_set('sirket_logo', '');
            log_yaz('logo_sil', 'ayarlar', null, '');
        }

        log_yaz('ayar_kaydet', 'ayarlar', null, 'Şirket bilgileri');
        flash('success', 'Şirket bilgileri kaydedildi.');
        redirect('yonetim.php?sayfa=ayarlar');
    }

    if ($tip === 'smtp') {
        ayar_set('smtp_host',       trim((string)post('smtp_host', 'smtp.gmail.com')));
        ayar_set('smtp_port',       trim((string)post('smtp_port', '587')));
        ayar_set('smtp_secure',     in_array(post('smtp_secure'), ['tls','ssl',''], true) ? post('smtp_secure') : 'tls');
        ayar_set('smtp_user',       trim((string)post('smtp_user', '')));
        $newPass = (string)post('smtp_pass', '');
        if ($newPass !== '') ayar_set('smtp_pass', $newPass);
        ayar_set('smtp_from_name',  trim((string)post('smtp_from_name', '')));
        ayar_set('smtp_from_email', trim((string)post('smtp_from_email', '')));
        ayar_set('mail_imza',       (string)post('mail_imza', ''));
        log_yaz('ayar_kaydet', 'ayarlar', null, 'SMTP bilgileri');
        flash('success', 'E-posta ayarları kaydedildi.');
        redirect('yonetim.php?sayfa=ayarlar#smtp');
    }

    if ($tip === 'smtp_test') {
        $res = mail_test_connection();
        flash($res['ok'] ? 'success' : 'error', 'SMTP Test: ' . $res['msg']);
        redirect('yonetim.php?sayfa=ayarlar#smtp');
    }

    if ($tip === 'smtp_test_mail') {
        $to = trim((string)post('test_to', ''));
        if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $res = mail_gonder($to, 'AKM - Test E-postası', '<p>Bu bir test e-postasıdır. SMTP ayarlarınız çalışıyor.</p><p>— ' . e(ayar_get('sirket_adi', SITE_NAME)) . '</p>');
            flash($res['ok'] ? 'success' : 'error', 'Test Mail: ' . $res['msg']);
        } else {
            flash('error', 'Geçerli bir test e-postası girin.');
        }
        redirect('yonetim.php?sayfa=ayarlar#smtp');
    }

    if ($tip === 'teklif') {
        ayar_set('teklif_prefix',   trim((string)post('teklif_prefix', 'AKM')));
        ayar_set('teklif_onek_yil', post('teklif_onek_yil') ? '1' : '0');
        ayar_set('teklif_sayac',    (string)max(1, int_param(post('teklif_sayac', 1), 1)));
        ayar_set('varsayilan_kdv',  (string)dec_param(post('varsayilan_kdv', 20), 20));
        flash('success', 'Teklif ayarları kaydedildi.');
        redirect('yonetim.php?sayfa=ayarlar#teklif');
    }

    if ($tip === 'kur') {
        $usd = (string)dec_param(post('kur_usd_try', 0), 0);
        $eur = (string)dec_param(post('kur_eur_try', 0), 0);
        ayar_set('kur_usd_try', $usd);
        ayar_set('kur_eur_try', $eur);
        ayar_set('kur_guncelleme', date('Y-m-d H:i:s'));
        ayar_set('kur_kaynak', 'Manuel');
        flash('success', 'Döviz kurları kaydedildi.');
        redirect('yonetim.php?sayfa=ayarlar#kur');
    }

    if ($tip === 'kur_tcmb') {
        require_once ROOT_PATH . '/includes/tcmb.php';
        $res = tcmb_kur_guncelle(true); // force
        if ($res['ok']) {
            $msg = 'TCMB kurları alındı (' . ($res['tarih'] ?? '-') . ') — ';
            $msg .= 'USD: ' . ($res['kurlar']['USD'] ?? '-') . ' / EUR: ' . ($res['kurlar']['EUR'] ?? '-');
            log_yaz('kur_tcmb', 'ayarlar', null, $msg);
            flash('success', $msg);
        } else {
            flash('error', 'TCMB hatası: ' . ($res['msg'] ?? 'bilinmeyen'));
        }
        redirect('yonetim.php?sayfa=ayarlar#kur');
    }
}

$ayarlarCache = [];
foreach (db()->query('SELECT anahtar, deger FROM ' . tbl('ayarlar'))->fetchAll() as $r) {
    $ayarlarCache[$r['anahtar']] = $r['deger'];
}
$a = fn(string $k, string $d = '') => $ayarlarCache[$k] ?? $d;
?>

<div class="card" id="sirket">
  <div class="card-head"><h2>🏢 Şirket Bilgileri</h2></div>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="sirket">

    <?php $logoPath = $a('sirket_logo'); ?>
    <div class="form-row col-1">
      <div class="field">
        <label>Şirket Logosu</label>
        <div class="logo-upload">
          <?php if ($logoPath && is_file(ROOT_PATH . '/' . $logoPath)): ?>
            <div class="logo-preview">
              <img src="<?= e($logoPath) ?>?v=<?= filemtime(ROOT_PATH . '/' . $logoPath) ?>" alt="Logo">
            </div>
          <?php endif; ?>
          <div class="logo-controls">
            <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml">
            <small class="muted">PNG, JPG, WebP veya SVG (max 2MB). Teklif çıktılarında kullanılır.</small>
            <?php if ($logoPath): ?>
              <label class="mt-1" style="display:inline-block">
                <input type="checkbox" name="logo_sil" value="1"> Mevcut logoyu sil
              </label>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="form-row col-1">
      <div class="field"><label>Şirket Adı</label>
        <input type="text" name="sirket_adi" value="<?= e($a('sirket_adi')) ?>"></div>
    </div>
    <div class="form-row col-1">
      <div class="field"><label>Adres</label>
        <textarea name="sirket_adres" rows="2"><?= e($a('sirket_adres')) ?></textarea></div>
    </div>
    <div class="form-row col-3">
      <div class="field"><label>Telefon</label>
        <input type="text" name="sirket_telefon" value="<?= e($a('sirket_telefon')) ?>"></div>
      <div class="field"><label>E-posta</label>
        <input type="email" name="sirket_email" value="<?= e($a('sirket_email')) ?>"></div>
      <div class="field"><label>Web Sitesi</label>
        <input type="url" name="sirket_web" value="<?= e($a('sirket_web')) ?>"></div>
    </div>
    <div class="form-row">
      <div class="field"><label>Vergi Dairesi</label>
        <input type="text" name="sirket_vergi_dairesi" value="<?= e($a('sirket_vergi_dairesi')) ?>"></div>
      <div class="field"><label>VKN</label>
        <input type="text" name="sirket_vkn" value="<?= e($a('sirket_vkn')) ?>"></div>
    </div>
    <div class="alert alert-info">
      🏦 <strong>Banka hesapları</strong> ayrı bir modülde yönetilmektedir.
      <a href="yonetim.php?sayfa=banka">Banka Hesapları →</a> sayfasından TRY/USD/EUR için
      ayrı hesaplar (banka adı, şube, IBAN, SWIFT) tanımlayabilirsiniz.
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </form>
</div>

<div class="card" id="smtp">
  <div class="card-head"><h2>📧 E-posta (Gmail SMTP)</h2></div>
  <div class="alert alert-info">
    <strong>Gmail için:</strong> İki Adımlı Doğrulama'yı etkinleştirdikten sonra
    <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a>
    oluşturun ve aşağıdaki şifre alanına o 16 haneli şifreyi yazın (normal Gmail şifreniz değil).
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="smtp">

    <div class="form-row col-3">
      <div class="field"><label>SMTP Host</label>
        <input type="text" name="smtp_host" value="<?= e($a('smtp_host', 'smtp.gmail.com')) ?>"></div>
      <div class="field"><label>Port</label>
        <input type="number" name="smtp_port" value="<?= e($a('smtp_port', '587')) ?>"></div>
      <div class="field"><label>Şifreleme</label>
        <select name="smtp_secure">
          <option value="tls" <?= $a('smtp_secure') === 'tls' ? 'selected':'' ?>>TLS (STARTTLS)</option>
          <option value="ssl" <?= $a('smtp_secure') === 'ssl' ? 'selected':'' ?>>SSL</option>
          <option value=""    <?= $a('smtp_secure') === ''    ? 'selected':'' ?>>Yok</option>
        </select></div>
    </div>
    <div class="form-row">
      <div class="field"><label>Kullanıcı (Gmail adresi)</label>
        <input type="email" name="smtp_user" value="<?= e($a('smtp_user')) ?>"></div>
      <div class="field"><label>Şifre (App Password) <span class="muted">(değiştirmek için doldurun)</span></label>
        <input type="password" name="smtp_pass" value="" placeholder="<?= $a('smtp_pass') ? '••••••••' : 'xxxx xxxx xxxx xxxx' ?>"></div>
    </div>
    <div class="form-row">
      <div class="field"><label>Gönderen Adı</label>
        <input type="text" name="smtp_from_name" value="<?= e($a('smtp_from_name', ayar_get('sirket_adi', SITE_NAME))) ?>"></div>
      <div class="field"><label>Gönderen E-posta</label>
        <input type="email" name="smtp_from_email" value="<?= e($a('smtp_from_email')) ?>" placeholder="kullanıcı@gmail.com"></div>
    </div>
    <div class="form-row col-1">
      <div class="field"><label>Mail İmzası (HTML)</label>
        <textarea name="mail_imza" rows="4"><?= e($a('mail_imza')) ?></textarea></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </form>

  <hr style="margin:20px 0; border:0; border-top:1px solid var(--border-2)">
  <h3>Test</h3>
  <form method="post" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="smtp_test">
    <button type="submit" class="btn btn-ghost btn-sm">🔌 Bağlantıyı Test Et</button>
  </form>
  <form method="post" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin-top:10px">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="smtp_test_mail">
    <div class="field" style="flex:1; max-width:320px">
      <label>Test E-postası Gönder</label>
      <input type="email" name="test_to" placeholder="test@ornek.com" required>
    </div>
    <button type="submit" class="btn btn-accent btn-sm">📨 Test Maili Gönder</button>
  </form>
</div>

<div class="card" id="teklif">
  <div class="card-head"><h2>📋 Teklif Numaralandırma</h2></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="teklif">
    <div class="form-row col-4">
      <div class="field"><label>Ön Ek</label>
        <input type="text" name="teklif_prefix" value="<?= e($a('teklif_prefix', 'AKM')) ?>" maxlength="10"></div>
      <div class="field"><label>Yıl Ekle</label>
        <select name="teklif_onek_yil">
          <option value="1" <?= $a('teklif_onek_yil', '1') === '1' ? 'selected':'' ?>>Evet (AKM-2026-0001)</option>
          <option value="0" <?= $a('teklif_onek_yil') === '0'      ? 'selected':'' ?>>Hayır (AKM-0001)</option>
        </select></div>
      <div class="field"><label>Sonraki Sayı</label>
        <input type="number" name="teklif_sayac" value="<?= e($a('teklif_sayac', '1')) ?>" min="1"></div>
      <div class="field"><label>Varsayılan KDV %</label>
        <input type="number" step="0.01" name="varsayilan_kdv" value="<?= e($a('varsayilan_kdv', '20')) ?>"></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
  </form>
</div>

<div class="card" id="kur">
  <div class="card-head">
    <h2>💱 Döviz Kurları</h2>
    <div class="page-actions">
      <form method="post" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="islem" value="kur_tcmb">
        <button type="submit" class="btn btn-accent btn-sm" title="TCMB Efektif Satış kurlarını çek">
          🏦 TCMB'den Güncelle
        </button>
      </form>
    </div>
  </div>
  <div class="alert alert-info">
    <strong>Kaynak:</strong> TCMB <a href="https://www.tcmb.gov.tr/kurlar/today.xml" target="_blank" rel="noopener">today.xml</a> (Efektif Satış)<br>
    <strong>Son güncelleme:</strong> <?= e($a('kur_guncelleme', '—')) ?>
    <?php if ($a('kur_kaynak')): ?> · <strong>Kaynak:</strong> <?= e($a('kur_kaynak')) ?><?php endif; ?><br>
    TCMB her iş günü saat 15:30 sonrasında yeni kurları yayınlar. Hafta sonu ve resmi tatillerde son iş gününün kurları döner.
  </div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="islem" value="kur">
    <div class="form-row col-2">
      <div class="field"><label>1 USD = ? TRY</label>
        <input type="number" step="0.0001" name="kur_usd_try" value="<?= e($a('kur_usd_try', '0')) ?>"></div>
      <div class="field"><label>1 EUR = ? TRY</label>
        <input type="number" step="0.0001" name="kur_eur_try" value="<?= e($a('kur_eur_try', '0')) ?>"></div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Manuel Kaydet</button>
    </div>
  </form>
</div>
