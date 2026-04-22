<?php
/**
 * Proforma Teklif Ekle / Düzenle
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$id = int_param(get_param('id', 0));
$duzenle = $id > 0;
$page_title = $duzenle ? 'Teklif Düzenle' : 'Yeni Proforma Teklif';

$t = [
    'id' => 0, 'teklif_no' => '', 'cari_id' => 0,
    'tarih' => date('Y-m-d'),
    'gecerlilik_tarihi' => date('Y-m-d', strtotime('+30 days')),
    'para_birimi' => 'TRY', 'doviz_kuru' => 1,
    'kdv_dahil' => 0, 'iskonto_orani' => 0,
    'odeme_sekli' => 'Peşin', 'teslim_sekli' => 'Fabrika Teslim',
    'notlar' => '',
    'sartlar' => "1. Fiyatlarımız " . date('d.m.Y', strtotime('+30 days')) . " tarihine kadar geçerlidir.\n2. Ödeme koşulları yukarıda belirtildiği gibidir.\n3. Teslimat süresi sipariş onayından itibaren geçerlidir.",
    'durum' => 'taslak',
];
$kalemler = [];

if ($duzenle) {
    $st = db()->prepare('SELECT * FROM ' . tbl('teklif') . ' WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('error', 'Teklif bulunamadı.');
        redirect('yonetim.php?sayfa=teklif');
    }
    $t = array_merge($t, $row);

    $st = db()->prepare('SELECT * FROM ' . tbl('teklif_kalem') . ' WHERE teklif_id = ? ORDER BY sira, id');
    $st->execute([$id]);
    $kalemler = $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $t['teklif_no']         = trim((string)post('teklif_no', ''));
    $t['cari_id']           = int_param(post('cari_id'));
    $t['tarih']             = (string)post('tarih', date('Y-m-d'));
    $t['gecerlilik_tarihi'] = (string)post('gecerlilik_tarihi');
    $t['para_birimi']       = in_array(post('para_birimi'), ['TRY','USD','EUR'], true) ? post('para_birimi') : 'TRY';
    $t['doviz_kuru']        = dec_param(post('doviz_kuru', 1));
    $t['kdv_dahil']         = post('kdv_dahil') ? 1 : 0;
    $t['iskonto_orani']     = dec_param(post('iskonto_orani', 0));
    $t['odeme_sekli']       = trim((string)post('odeme_sekli', ''));
    $t['teslim_sekli']      = trim((string)post('teslim_sekli', ''));
    $t['notlar']            = trim((string)post('notlar', ''));
    $t['sartlar']           = trim((string)post('sartlar', ''));
    $t['durum']             = in_array(post('durum'), ['taslak','gonderildi','goruldu','onaylandi','reddedildi','iptal'], true) ? post('durum') : 'taslak';

    $adlar    = $_POST['kalem_adi']      ?? [];
    $aciklar  = $_POST['kalem_aciklama'] ?? [];
    $miktarlr = $_POST['kalem_miktar']   ?? [];
    $birimler = $_POST['kalem_birim']    ?? [];
    $fiyatlar = $_POST['kalem_fiyat']    ?? [];
    $isklar   = $_POST['kalem_iskonto']  ?? [];
    $kdvler   = $_POST['kalem_kdv']      ?? [];

    $yeniKalemler = [];
    for ($i = 0; $i < count($adlar); $i++) {
        $ad = trim((string)($adlar[$i] ?? ''));
        if ($ad === '') continue;
        $yeniKalemler[] = [
            'urun_adi'     => $ad,
            'aciklama'     => trim((string)($aciklar[$i]  ?? '')),
            'miktar'       => dec_param($miktarlr[$i]   ?? 1),
            'birim'        => trim((string)($birimler[$i]  ?? 'Adet')) ?: 'Adet',
            'birim_fiyat'  => dec_param($fiyatlar[$i]   ?? 0),
            'iskonto_orani'=> dec_param($isklar[$i]     ?? 0),
            'kdv_orani'    => dec_param($kdvler[$i]     ?? 0),
        ];
    }

    $err = [];
    if ($t['cari_id'] <= 0)        $err[] = 'Cari seçimi zorunludur.';
    if (empty($yeniKalemler))       $err[] = 'En az bir kalem eklemelisiniz.';
    if (!$t['tarih'])               $err[] = 'Tarih zorunludur.';

    if (empty($err)) {
        $hesap = hesapla_teklif($yeniKalemler, $t['iskonto_orani'], (bool)$t['kdv_dahil']);
        try {
            db()->beginTransaction();

            if ($t['teklif_no'] === '') {
                $t['teklif_no'] = generate_teklif_no();
            }

            if ($duzenle) {
                $sql = 'UPDATE ' . tbl('teklif') . ' SET
                    teklif_no=?, cari_id=?, tarih=?, gecerlilik_tarihi=?,
                    para_birimi=?, doviz_kuru=?, kdv_dahil=?,
                    ara_toplam=?, iskonto_orani=?, iskonto_tutari=?, kdv_toplam=?, genel_toplam=?,
                    odeme_sekli=?, teslim_sekli=?, notlar=?, sartlar=?, durum=?,
                    guncelleme_tarihi=NOW()
                    WHERE id=?';
                $st = db()->prepare($sql);
                $st->execute([
                    $t['teklif_no'], $t['cari_id'], $t['tarih'], $t['gecerlilik_tarihi'] ?: null,
                    $t['para_birimi'], $t['doviz_kuru'], $t['kdv_dahil'],
                    $hesap['ara_toplam'], $t['iskonto_orani'], $hesap['iskonto_tutari'], $hesap['kdv_toplam'], $hesap['genel_toplam'],
                    $t['odeme_sekli'], $t['teslim_sekli'], $t['notlar'], $t['sartlar'], $t['durum'],
                    $id
                ]);
                // kalemleri sil/yeniden ekle
                db()->prepare('DELETE FROM ' . tbl('teklif_kalem') . ' WHERE teklif_id = ?')->execute([$id]);
                $teklifId = $id;
            } else {
                $view_token = bin2hex(random_bytes(24));
                $sql = 'INSERT INTO ' . tbl('teklif') . '
                    (teklif_no, cari_id, tarih, gecerlilik_tarihi,
                     para_birimi, doviz_kuru, kdv_dahil,
                     ara_toplam, iskonto_orani, iskonto_tutari, kdv_toplam, genel_toplam,
                     odeme_sekli, teslim_sekli, notlar, sartlar, durum,
                     view_token, olusturan_id, olusturma_tarihi)
                    VALUES (?,?,?,?, ?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,NOW())';
                $st = db()->prepare($sql);
                $st->execute([
                    $t['teklif_no'], $t['cari_id'], $t['tarih'], $t['gecerlilik_tarihi'] ?: null,
                    $t['para_birimi'], $t['doviz_kuru'], $t['kdv_dahil'],
                    $hesap['ara_toplam'], $t['iskonto_orani'], $hesap['iskonto_tutari'], $hesap['kdv_toplam'], $hesap['genel_toplam'],
                    $t['odeme_sekli'], $t['teslim_sekli'], $t['notlar'], $t['sartlar'], $t['durum'],
                    $view_token, current_user()['id'] ?? null
                ]);
                $teklifId = (int)db()->lastInsertId();
            }

            $stK = db()->prepare('INSERT INTO ' . tbl('teklif_kalem') . '
                (teklif_id, sira, urun_adi, aciklama, miktar, birim, birim_fiyat, iskonto_orani, kdv_orani, satir_toplam)
                VALUES (?,?,?,?,?,?,?,?,?,?)');
            foreach ($hesap['satirlar'] as $i => $k) {
                $stK->execute([
                    $teklifId, $i + 1,
                    $k['urun_adi'], $k['aciklama'],
                    $k['miktar'], $k['birim'],
                    $k['birim_fiyat'], $k['iskonto_orani'], $k['kdv_orani'],
                    $k['satir_toplam'],
                ]);
            }

            db()->commit();
            log_yaz($duzenle ? 'guncelle' : 'ekle', 'teklif', $teklifId, $t['teklif_no']);
            flash('success', 'Teklif kaydedildi.');
            redirect('yonetim.php?sayfa=teklif_goruntule&id=' . $teklifId);
        } catch (Throwable $e) {
            db()->rollBack();
            flash('error', 'Kayıt hatası: ' . $e->getMessage());
        }
    } else {
        flash('error', implode(' ', $err));
        $kalemler = $yeniKalemler; // formu tekrar göster
    }
}

// Cariler dropdown için
$cariler = db()->query('SELECT id, cari_kodu, firma_adi, para_birimi, email FROM ' . tbl('cari') . ' WHERE aktif=1 ORDER BY firma_adi')->fetchAll();

// Yeni teklif ise 1 boş satır göster
if (!$duzenle && empty($kalemler)) {
    $kalemler = [['urun_adi' => '', 'aciklama' => '', 'miktar' => 1, 'birim' => 'Adet', 'birim_fiyat' => 0, 'iskonto_orani' => 0, 'kdv_orani' => (float)ayar_get('varsayilan_kdv', '20')]];
}

$kdvVars = (float)ayar_get('varsayilan_kdv', '20');
?>
<form method="post" class="card" autocomplete="off">
  <?= csrf_field() ?>
  <div class="card-head">
    <h2><?= $duzenle ? 'Teklif Düzenle' : 'Yeni Proforma Teklif' ?></h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=teklif" class="btn btn-ghost btn-sm">← Listeye Dön</a>
    </div>
  </div>

  <div class="form-row col-4">
    <div class="field">
      <label>Teklif No <span class="muted">(boş = otomatik)</span></label>
      <input type="text" name="teklif_no" value="<?= e($t['teklif_no']) ?>" placeholder="AKM-<?= date('Y') ?>-0001">
    </div>
    <div class="field">
      <label>Tarih <span style="color:var(--danger)">*</span></label>
      <input type="date" name="tarih" value="<?= e($t['tarih']) ?>" required>
    </div>
    <div class="field">
      <label>Geçerlilik Tarihi</label>
      <input type="date" name="gecerlilik_tarihi" value="<?= e($t['gecerlilik_tarihi']) ?>">
    </div>
    <div class="field">
      <label>Durum</label>
      <select name="durum">
        <?php foreach (['taslak','gonderildi','goruldu','onaylandi','reddedildi','iptal'] as $d): ?>
          <option value="<?= e($d) ?>" <?= ($t['durum'] ?? 'taslak') === $d ? 'selected':'' ?>><?= e(tr_durum($d)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row col-3">
    <div class="field" style="grid-column: span 2">
      <label>Cari <span style="color:var(--danger)">*</span></label>
      <select name="cari_id" id="cari_select" required>
        <option value="">-- Cari seçin --</option>
        <?php foreach ($cariler as $ci): ?>
          <option value="<?= (int)$ci['id'] ?>"
                  data-para-birimi="<?= e($ci['para_birimi']) ?>"
                  <?= (int)$t['cari_id'] === (int)$ci['id'] ? 'selected' : '' ?>>
            <?= e($ci['cari_kodu']) ?> — <?= e($ci['firma_adi']) ?>
            <?= $ci['email'] ? ' (' . e($ci['email']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Para Birimi</label>
      <select name="para_birimi" id="para_birimi" data-locked="<?= $duzenle ? '1' : '' ?>">
        <option value="TRY" <?= $t['para_birimi']==='TRY' ? 'selected':'' ?>>TRY - Türk Lirası</option>
        <option value="USD" <?= $t['para_birimi']==='USD' ? 'selected':'' ?>>USD - Dolar</option>
        <option value="EUR" <?= $t['para_birimi']==='EUR' ? 'selected':'' ?>>EUR - Euro</option>
      </select>
    </div>
  </div>

  <div class="form-row col-3">
    <div class="field">
      <label>Döviz Kuru <span class="muted">(Para birimi TRY dışı ise)</span></label>
      <div class="kur-grup">
        <input type="number" step="0.0001" min="0" name="doviz_kuru" id="doviz_kuru" value="<?= e((string)$t['doviz_kuru']) ?>">
        <button type="button" id="btnKurYenile" class="btn btn-accent btn-sm" title="TCMB'den güncel Efektif Satış kurunu çek">
          🏦 TCMB
        </button>
      </div>
      <small class="kur-info muted" id="kurInfo">
        <?php
          $kurKaynak = ayar_get('kur_kaynak', '');
          $kurTarih  = ayar_get('tcmb_son_tarih', '');
          if ($kurKaynak): echo 'Kayıtlı: ' . e($kurKaynak);
            if ($kurTarih) echo ' (' . e($kurTarih) . ')';
          endif;
        ?>
      </small>
    </div>
    <div class="field">
      <label>Ödeme Şekli</label>
      <input type="text" name="odeme_sekli" value="<?= e($t['odeme_sekli']) ?>">
    </div>
    <div class="field">
      <label>Teslim Şekli</label>
      <input type="text" name="teslim_sekli" value="<?= e($t['teslim_sekli']) ?>">
    </div>
  </div>

  <?php /* JS için kayıtlı kurları data attribute olarak aktar */ ?>
  <script>
    window.__AKM_KUR = {
      USD: <?= json_encode((float)ayar_get('kur_usd_try', 0)) ?>,
      EUR: <?= json_encode((float)ayar_get('kur_eur_try', 0)) ?>,
      tarih: <?= json_encode((string)ayar_get('tcmb_son_tarih', '')) ?>,
      kaynak: <?= json_encode((string)ayar_get('kur_kaynak', '')) ?>
    };
  </script>

  <h3 class="mt-2 mb-1">Teklif Kalemleri</h3>
  <div class="table-wrap">
    <table class="kalem-tbl" id="kalemTbl">
      <thead>
        <tr>
          <th style="width:34px">#</th>
          <th style="min-width:220px">Ürün / Hizmet</th>
          <th style="min-width:180px">Açıklama</th>
          <th style="width:90px" class="num">Miktar</th>
          <th style="width:80px">Birim</th>
          <th style="width:120px" class="num">Birim Fiyat</th>
          <th style="width:80px" class="num">İsk.%</th>
          <th style="width:80px" class="num">KDV%</th>
          <th style="width:110px" class="num">Tutar</th>
          <th style="width:44px"></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($kalemler as $idx => $k): ?>
        <tr class="kalem-row">
          <td class="cen"><span class="kalem-sira"><?= $idx + 1 ?></span></td>
          <td><input type="text" name="kalem_adi[]"      value="<?= e($k['urun_adi']) ?>" required></td>
          <td><input type="text" name="kalem_aciklama[]" value="<?= e($k['aciklama'] ?? '') ?>"></td>
          <td class="num"><input type="text" name="kalem_miktar[]"  value="<?= e(number_format((float)$k['miktar'], 2, ',', '')) ?>" class="num"></td>
          <td><input type="text" name="kalem_birim[]"    value="<?= e($k['birim'] ?? 'Adet') ?>"></td>
          <td class="num"><input type="text" name="kalem_fiyat[]"   value="<?= e(number_format((float)$k['birim_fiyat'], 2, ',', '')) ?>" class="num"></td>
          <td class="num"><input type="text" name="kalem_iskonto[]" value="<?= e(number_format((float)($k['iskonto_orani'] ?? 0), 2, ',', '')) ?>" class="num"></td>
          <td class="num"><input type="text" name="kalem_kdv[]"     value="<?= e(number_format((float)($k['kdv_orani'] ?? $kdvVars), 2, ',', '')) ?>" class="num"></td>
          <td class="num satir-toplam">0,00</td>
          <td class="cen"><button type="button" class="btn btn-danger btn-xs btn-kalem-sil" title="Satırı sil">×</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <template id="kalemTpl">
    <tr class="kalem-row">
      <td class="cen"><span class="kalem-sira"></span></td>
      <td><input type="text" name="kalem_adi[]" required></td>
      <td><input type="text" name="kalem_aciklama[]"></td>
      <td class="num"><input type="text" name="kalem_miktar[]"  value="1,00" class="num"></td>
      <td><input type="text" name="kalem_birim[]" value="Adet"></td>
      <td class="num"><input type="text" name="kalem_fiyat[]"   value="0,00" class="num"></td>
      <td class="num"><input type="text" name="kalem_iskonto[]" value="0,00" class="num"></td>
      <td class="num"><input type="text" name="kalem_kdv[]"     value="<?= e(number_format($kdvVars, 2, ',', '')) ?>" class="num"></td>
      <td class="num satir-toplam">0,00</td>
      <td class="cen"><button type="button" class="btn btn-danger btn-xs btn-kalem-sil" title="Satırı sil">×</button></td>
    </tr>
  </template>

  <button type="button" id="btnKalemEkle" class="btn btn-accent btn-sm mt-1">+ Satır Ekle</button>

  <div class="kalem-toplamlar">
    <div>
      <div class="form-row col-1">
        <div class="field">
          <label>Genel İskonto (%)</label>
          <input type="number" step="0.01" min="0" max="100" name="iskonto_orani" id="iskonto_orani" value="<?= e((string)$t['iskonto_orani']) ?>">
        </div>
        <div class="field">
          <label>Notlar (müşteriye görünür)</label>
          <textarea name="notlar" rows="2"><?= e($t['notlar']) ?></textarea>
        </div>
        <div class="field">
          <label>Şartlar</label>
          <textarea name="sartlar" rows="4"><?= e($t['sartlar']) ?></textarea>
        </div>
      </div>
    </div>
    <div class="toplam-box">
      <div class="row"><span>Ara Toplam</span><strong><span class="pb-sym">₺</span> <span id="sumAra">0,00</span></strong></div>
      <div class="row"><span>İskonto</span><strong>- <span class="pb-sym">₺</span> <span id="sumIskonto">0,00</span></strong></div>
      <div id="kdvDagilim" class="kdv-dagilim"></div>
      <div class="row" id="kdvTopRow"><span id="kdvTopLabel">KDV</span><strong><span class="pb-sym">₺</span> <span id="sumKdv">0,00</span></strong></div>
      <div class="row grand"><span>GENEL TOPLAM</span><span><span class="pb-sym">₺</span> <span id="sumGenel">0,00</span></span></div>
    </div>
  </div>

  <div class="form-actions">
    <a href="yonetim.php?sayfa=teklif" class="btn btn-ghost">İptal</a>
    <button type="submit" class="btn btn-primary">Kaydet</button>
  </div>
</form>
