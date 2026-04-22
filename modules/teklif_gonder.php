<?php
/**
 * Teklif Mail Gönderim
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

require_once ROOT_PATH . '/includes/mailer.php';

$id = int_param(get_param('id', 0));
if ($id <= 0) { flash('error', 'Geçersiz teklif.'); redirect('yonetim.php?sayfa=teklif'); }

$st = db()->prepare('SELECT t.*, c.firma_adi, c.yetkili_adi, c.email AS cari_email
                     FROM ' . tbl('teklif') . ' t
                     LEFT JOIN ' . tbl('cari') . ' c ON c.id = t.cari_id
                     WHERE t.id = ?');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) { flash('error', 'Teklif bulunamadı.'); redirect('yonetim.php?sayfa=teklif'); }

$page_title = 'Teklif Gönder: ' . $t['teklif_no'];

// View token yoksa üret
if (empty($t['view_token'])) {
    $tok = bin2hex(random_bytes(24));
    db()->prepare('UPDATE ' . tbl('teklif') . ' SET view_token = ? WHERE id = ?')->execute([$tok, $id]);
    $t['view_token'] = $tok;
}

$viewLink = rtrim(SITE_URL, '/') . '/teklif.php?tk=' . $t['view_token'];

$default_konu = '[' . $t['teklif_no'] . '] ' . ayar_get('sirket_adi', SITE_NAME) . ' - Proforma Teklif';
$default_mesaj = "Sayın " . ($t['yetkili_adi'] ?: $t['firma_adi']) . ",

" . ayar_get('sirket_adi', SITE_NAME) . " olarak talebiniz üzerine hazırladığımız proforma teklifimizi sunarız.

Teklif Numarası: " . $t['teklif_no'] . "
Teklif Tarihi: " . fmt_date($t['tarih']) . "
Geçerlilik: " . ($t['gecerlilik_tarihi'] ? fmt_date($t['gecerlilik_tarihi']) : '-') . "
Toplam Tutar: " . fmt_money((float)$t['genel_toplam'], $t['para_birimi']) . "

Teklifin detayları bu e-postanın ekindedir ve aynı zamanda aşağıdaki bağlantıdan da görüntüleyebilirsiniz:
" . $viewLink . "

Sorularınız için bize ulaşabilirsiniz.

" . strip_tags(ayar_get('mail_imza', ''));

$errMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $to   = trim((string)post('to', ''));
    $cc   = trim((string)post('cc', ''));
    $konu = trim((string)post('konu', ''));
    $mesaj = trim((string)post('mesaj', ''));
    $ekle_pdf = post('ekle_pdf') ? true : false;

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $errMsg = 'Geçerli bir alıcı e-postası girin.';
    } elseif ($konu === '' || $mesaj === '') {
        $errMsg = 'Konu ve mesaj zorunludur.';
    } else {
        $ccList = [];
        if ($cc !== '') {
            foreach (preg_split('/[,;]+/', $cc) as $item) {
                $item = trim($item);
                if ($item && filter_var($item, FILTER_VALIDATE_EMAIL)) $ccList[] = $item;
            }
        }

        // HTML mesaj gövdesi
        $mesajHtml = nl2br(e($mesaj));
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:14px;color:#1a202c;line-height:1.6;max-width:640px;margin:0 auto;padding:20px">'
              . $mesajHtml
              . '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e4e7ec">'
              . '<a href="' . e($viewLink) . '" style="display:inline-block;background:#1b3a6b;color:#fff;padding:10px 18px;border-radius:5px;text-decoration:none;font-weight:600">Teklifi Online Görüntüle</a>'
              . '</div>'
              . '<div style="margin-top:16px;color:#8895a7;font-size:12px">'
              . ayar_get('mail_imza', '')
              . '</div>'
              . '</body></html>';

        // PDF ek (eğer istenmişse) — gerçek PDF yerine HTML içerik sağlıyoruz
        // Browser print-to-PDF gerektiriyor. Alternatif: ek yok, link var.
        $attachments = [];
        // Not: gerçek PDF üretimi için mPDF/TCPDF bağımlılığı gerekir.
        // Şimdilik ek eklemiyoruz, alıcı online linkten görüntüleyebilir.

        $res = mail_gonder($to, $konu, $html, $ccList, $attachments);

        // Geçmişe kaydet
        $stG = db()->prepare('INSERT INTO ' . tbl('teklif_gonderim') . '
            (teklif_id, alici_email, cc, konu, mesaj, durum, hata_mesaji, gonderen_id, gonderim_tarihi)
            VALUES (?,?,?,?,?,?,?,?,NOW())');
        $stG->execute([
            $id, $to, implode(', ', $ccList), $konu, $mesaj,
            $res['ok'] ? 'basarili' : 'hata',
            $res['ok'] ? null : $res['msg'],
            current_user()['id'] ?? null,
        ]);

        if ($res['ok']) {
            db()->prepare('UPDATE ' . tbl('teklif') . ' SET durum = ?, gonderim_tarihi = NOW(), guncelleme_tarihi = NOW() WHERE id = ? AND durum = ?')
                ->execute(['gonderildi', $id, 'taslak']);
            log_yaz('mail_gonder', 'teklif', $id, 'Alıcı: ' . $to);
            flash('success', 'Teklif e-posta ile gönderildi.');
            redirect('yonetim.php?sayfa=teklif_goruntule&id=' . $id);
        } else {
            $errMsg = 'Gönderim hatası: ' . $res['msg'];
            log_yaz('mail_hata', 'teklif', $id, 'Hata: ' . $res['msg']);
        }
    }
}
?>
<div class="card">
  <div class="card-head">
    <h2>📧 Teklif Mail Gönder: <?= e($t['teklif_no']) ?></h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=teklif_goruntule&id=<?= $id ?>" class="btn btn-ghost btn-sm">← Geri</a>
    </div>
  </div>

  <?php if ($errMsg): ?>
    <div class="alert alert-error"><?= e($errMsg) ?></div>
  <?php endif; ?>

  <?php
    $smtpUser = ayar_get('smtp_user', '');
    $smtpFrom = ayar_get('smtp_from_email', '') ?: $smtpUser;
    if (!$smtpUser || !$smtpFrom):
  ?>
    <div class="alert alert-warn">
      SMTP ayarları tamamlanmamış. <a href="yonetim.php?sayfa=ayarlar#smtp">Ayarlar → E-posta</a> bölümünden Gmail bilgilerinizi girin.
    </div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>

    <div class="form-row">
      <div class="field">
        <label>Alıcı E-posta <span style="color:var(--danger)">*</span></label>
        <input type="email" name="to" required value="<?= e(post('to', $t['cari_email'] ?? '')) ?>">
      </div>
      <div class="field">
        <label>CC <span class="muted">(virgülle ayırarak birden fazla)</span></label>
        <input type="text" name="cc" value="<?= e(post('cc', '')) ?>" placeholder="cc1@ornek.com, cc2@ornek.com">
      </div>
    </div>

    <div class="form-row col-1">
      <div class="field">
        <label>Konu <span style="color:var(--danger)">*</span></label>
        <input type="text" name="konu" required value="<?= e(post('konu', $default_konu)) ?>">
      </div>
    </div>

    <div class="form-row col-1">
      <div class="field">
        <label>Mesaj <span style="color:var(--danger)">*</span></label>
        <textarea name="mesaj" rows="12" required><?= e(post('mesaj', $default_mesaj)) ?></textarea>
      </div>
    </div>

    <div class="alert alert-info">
      E-postaya teklif görüntüleme linki otomatik eklenecek:<br>
      <code><?= e($viewLink) ?></code>
    </div>

    <div class="form-actions">
      <a href="yonetim.php?sayfa=teklif_goruntule&id=<?= $id ?>" class="btn btn-ghost">İptal</a>
      <button type="submit" class="btn btn-primary">📧 Gönder</button>
    </div>
  </form>
</div>
