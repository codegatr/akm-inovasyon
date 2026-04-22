<?php
/**
 * Gösterge Paneli
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$page_title = 'Gösterge Paneli';

// İstatistikler
$db = db();
$cariSay       = (int)$db->query("SELECT COUNT(*) FROM " . tbl('cari') . " WHERE aktif=1")->fetchColumn();
$teklifSay     = (int)$db->query("SELECT COUNT(*) FROM " . tbl('teklif'))->fetchColumn();
$bekleyenSay   = (int)$db->query("SELECT COUNT(*) FROM " . tbl('teklif') . " WHERE durum IN ('taslak','gonderildi','goruldu')")->fetchColumn();
$onayliSay     = (int)$db->query("SELECT COUNT(*) FROM " . tbl('teklif') . " WHERE durum='onaylandi'")->fetchColumn();

// Bu ayın toplamları
$ayToplam = $db->query("SELECT para_birimi, SUM(genel_toplam) t FROM " . tbl('teklif') . "
                        WHERE DATE_FORMAT(tarih, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                          AND durum <> 'iptal'
                        GROUP BY para_birimi")->fetchAll();

// Son teklifler
$sonTeklifler = $db->query("SELECT t.id, t.teklif_no, t.tarih, t.para_birimi, t.genel_toplam, t.durum, c.firma_adi
                            FROM " . tbl('teklif') . " t
                            LEFT JOIN " . tbl('cari') . " c ON c.id = t.cari_id
                            ORDER BY t.id DESC LIMIT 8")->fetchAll();
?>
<div class="stats">
  <div class="stat-card">
    <div class="lb">Aktif Cari</div>
    <div class="vl"><?= number_format($cariSay, 0, ',', '.') ?></div>
    <div class="sub">Müşteri & tedarikçi</div>
  </div>
  <div class="stat-card s-accent">
    <div class="lb">Toplam Teklif</div>
    <div class="vl"><?= number_format($teklifSay, 0, ',', '.') ?></div>
    <div class="sub">Tüm zamanlar</div>
  </div>
  <div class="stat-card s-warn">
    <div class="lb">Bekleyen Teklif</div>
    <div class="vl"><?= number_format($bekleyenSay, 0, ',', '.') ?></div>
    <div class="sub">Taslak · Gönderildi · Görüldü</div>
  </div>
  <div class="stat-card s-success">
    <div class="lb">Onaylanan Teklif</div>
    <div class="vl"><?= number_format($onayliSay, 0, ',', '.') ?></div>
    <div class="sub">Kazanılan fırsatlar</div>
  </div>
</div>

<?php if (!empty($ayToplam)): ?>
<div class="card">
  <div class="card-head"><h2>Bu Ay (<?= date('m.Y') ?>)</h2></div>
  <div class="stats" style="margin-bottom:0">
    <?php foreach ($ayToplam as $r): ?>
      <div class="stat-card s-accent">
        <div class="lb">Toplam Teklif (<?= e($r['para_birimi']) ?>)</div>
        <div class="vl"><?= e(fmt_money((float)$r['t'], $r['para_birimi'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>Son Teklifler</h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=teklif_form" class="btn btn-primary btn-sm">+ Yeni Teklif</a>
      <a href="yonetim.php?sayfa=teklif" class="btn btn-ghost btn-sm">Tümü</a>
    </div>
  </div>
  <?php if (empty($sonTeklifler)): ?>
    <p class="muted">Henüz teklif yok.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Teklif No</th>
            <th>Cari</th>
            <th>Tarih</th>
            <th class="num">Tutar</th>
            <th>Durum</th>
            <th class="cen">İşlem</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($sonTeklifler as $t): ?>
          <tr>
            <td><strong><?= e($t['teklif_no']) ?></strong></td>
            <td><?= e($t['firma_adi'] ?? '-') ?></td>
            <td><?= fmt_date($t['tarih']) ?></td>
            <td class="num"><?= e(fmt_money((float)$t['genel_toplam'], $t['para_birimi'])) ?></td>
            <td><?= durum_rozet($t['durum']) ?></td>
            <td class="cen">
              <a class="btn btn-ghost btn-xs" href="yonetim.php?sayfa=teklif_goruntule&id=<?= (int)$t['id'] ?>">Aç</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
