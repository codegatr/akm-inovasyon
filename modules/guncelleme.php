<?php
/**
 * Güncelleme Sistemi
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

$page_title = 'Sistem Güncelleme';

$hataMsg = '';
$guncelBilgi = null;

// GitHub'dan son sürümü kontrol et
function gh_latest_release(): array {
    $url = UPDATE_CHECK_URL;
    $headers = [
        'Accept: application/vnd.github+json',
        'User-Agent: AKM-Inovasyon-Updater/1.0',
    ];
    if (defined('UPDATE_TOKEN') && UPDATE_TOKEN !== '') {
        $headers[] = 'Authorization: Bearer ' . UPDATE_TOKEN;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        return ['ok' => false, 'msg' => 'GitHub API hatası (HTTP ' . $code . '): ' . ($err ?: $body)];
    }
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['tag_name'])) {
        return ['ok' => false, 'msg' => 'Geçersiz GitHub yanıtı.'];
    }
    return ['ok' => true, 'data' => $data];
}

function normalize_ver(string $v): string {
    return ltrim($v, 'vV');
}

$k = gh_latest_release();
if (!$k['ok']) {
    $hataMsg = $k['msg'];
} else {
    $data = $k['data'];
    $latestTag = $data['tag_name'];
    $latestVer = normalize_ver($latestTag);
    $currVer   = normalize_ver(CURRENT_VERSION);
    $cmp       = version_compare($latestVer, $currVer);
    $zipUrl    = '';
    if (!empty($data['assets'])) {
        foreach ($data['assets'] as $as) {
            if (str_ends_with(strtolower($as['name'] ?? ''), '.zip')) {
                $zipUrl = $as['url'] ?? $as['browser_download_url'] ?? '';
                break;
            }
        }
    }
    if ($zipUrl === '') $zipUrl = $data['zipball_url'] ?? '';

    $guncelBilgi = [
        'tag'           => $latestTag,
        'versiyon'      => $latestVer,
        'mevcut'        => $currVer,
        'guncel_var'    => $cmp > 0,
        'yayin_notlari' => $data['body'] ?? '',
        'yayin_tarihi'  => $data['published_at'] ?? '',
        'zip_url'       => $zipUrl,
        'repo_html'     => $data['html_url'] ?? '',
    ];
}
?>
<div class="card">
  <div class="card-head">
    <h2>🔄 Sistem Güncelleme</h2>
    <div class="page-actions">
      <a href="yonetim.php?sayfa=guncelleme" class="btn btn-ghost btn-sm">↻ Yeniden Kontrol Et</a>
    </div>
  </div>

  <div class="stats" style="margin-bottom:16px">
    <div class="stat-card">
      <div class="lb">Kurulu Sürüm</div>
      <div class="vl">v<?= e(CURRENT_VERSION) ?></div>
    </div>
    <?php if ($guncelBilgi): ?>
    <div class="stat-card <?= $guncelBilgi['guncel_var'] ? 's-warn' : 's-success' ?>">
      <div class="lb">En Son Sürüm</div>
      <div class="vl">v<?= e($guncelBilgi['versiyon']) ?></div>
      <div class="sub">
        <?= $guncelBilgi['yayin_tarihi'] ? e(fmt_datetime($guncelBilgi['yayin_tarihi'])) : '' ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="stat-card s-accent">
      <div class="lb">GitHub Depo</div>
      <div class="vl" style="font-size:14px"><?= e(UPDATE_REPO) ?></div>
      <?php if ($guncelBilgi && $guncelBilgi['repo_html']): ?>
        <div class="sub"><a href="<?= e($guncelBilgi['repo_html']) ?>" target="_blank">Sürümler →</a></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($hataMsg): ?>
    <div class="alert alert-error"><strong>Hata:</strong> <?= e($hataMsg) ?></div>
  <?php endif; ?>

  <?php if ($guncelBilgi): ?>
    <?php if ($guncelBilgi['guncel_var']): ?>
      <div class="alert alert-warn">
        <strong>Yeni sürüm mevcut: v<?= e($guncelBilgi['versiyon']) ?></strong><br>
        Güncelleme ZIP dosyası indirilip sisteme uygulanacak. <code>config.php</code> dosyanız
        ve <code>uploads/</code> klasörünüz korunacak.
      </div>

      <?php if (!empty($guncelBilgi['yayin_notlari'])): ?>
        <h3>Yayın Notları</h3>
        <div class="card" style="background:#f8fafc; margin-bottom:16px">
          <pre style="white-space:pre-wrap; font-family:inherit; margin:0"><?= e($guncelBilgi['yayin_notlari']) ?></pre>
        </div>
      <?php endif; ?>

      <form method="post" action="update.php">
        <?= csrf_field() ?>
        <input type="hidden" name="versiyon" value="<?= e($guncelBilgi['tag']) ?>">
        <input type="hidden" name="zip_url"  value="<?= e($guncelBilgi['zip_url']) ?>">
        <button type="submit" class="btn btn-success btn-primary" data-confirm="v<?= e($guncelBilgi['versiyon']) ?> sürümüne güncellensin mi? İşlem sırasında site kısa süre erişilemez olabilir."
                onclick="this.disabled=true; this.form.submit();">
          ⬇ v<?= e($guncelBilgi['versiyon']) ?> Sürümüne Güncelle
        </button>
      </form>
    <?php else: ?>
      <div class="alert alert-success">
        <strong>✓ Sisteminiz güncel.</strong> En son sürüm v<?= e($guncelBilgi['versiyon']) ?> kullanılıyor.
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2>Güncelleme Nasıl Çalışır?</h2></div>
  <ol style="margin:0 0 0 18px; line-height:1.7">
    <li>Güncelle butonuna tıkladığınızda <code>update.php</code> çalışır.</li>
    <li>Yeni sürümün ZIP dosyası GitHub'dan <code>/tmp/akm_update_*.zip</code> yoluna indirilir.</li>
    <li>ZIP <code>/tmp/akm_extract_*/</code> altına açılır.</li>
    <li>Aşağıdaki dosyalar/klasörler <strong>dokunulmadan</strong> korunur:
      <br><code>config.php, uploads/, .htaccess, install.php</code></li>
    <li>Geri kalan dosyalar yeni sürümle değiştirilir.</li>
    <li><code>sql/install.sql</code> varsa <em>idempotent</em> çalıştırılır (CREATE TABLE IF NOT EXISTS).</li>
    <li><code>config.php</code> içindeki <code>CURRENT_VERSION</code> yeni versiyona güncellenir.</li>
  </ol>
</div>
