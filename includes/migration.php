<?php
/**
 * Otomatik Şema Migration
 *
 * Yönetim panelinde ilk erişimde (oturum başına bir kez) çalışır.
 * Gereken tablolar/kolonlar yoksa install.sql idempotent olarak uygulanır.
 * Bu sayede panel dosyaları yüklendikten sonra DB otomatik olarak güncellenir,
 * kullanıcının manuel migration script'i çalıştırması gerekmez.
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

function auto_migrate(): bool {
    // Session içinde bir kere çalıştıktan sonra tekrar kontrole gerek yok
    if (!empty($_SESSION['akm_schema_v']) && $_SESSION['akm_schema_v'] === CURRENT_VERSION) {
        return true;
    }

    try {
        $prefix = DB_PREFIX;

        // v1.0.3'te eklenen iki kritik yapı: banka_hesap tablosu + teklif.arsivlendi_tarihi
        $bankaTablo = (int)db()->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$prefix}banka_hesap'"
        )->fetchColumn();

        $arsivKolon = (int)db()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '{$prefix}teklif'
               AND COLUMN_NAME = 'arsivlendi_tarihi'"
        )->fetchColumn();

        if ($bankaTablo && $arsivKolon) {
            $_SESSION['akm_schema_v'] = CURRENT_VERSION;
            return true;
        }

        // Migration gerekiyor — install.sql'i idempotent çalıştır
        $sqlFile = ROOT_PATH . '/sql/install.sql';
        if (!is_file($sqlFile)) return false;

        $sql = file_get_contents($sqlFile);
        $clean = preg_replace('/--[^\n]*\n/', "\n", $sql);
        $ifadeler = array_filter(array_map('trim', explode(';', $clean)));

        foreach ($ifadeler as $q) {
            if ($q === '' || str_starts_with($q, '/*')) continue;
            try { db()->exec($q); }
            catch (Throwable $e) { /* idempotent çalıştırma — mevcut ifadeler atlanır */ }
        }

        // Log (fonksiyon mevcut olmayabilir — defansif)
        if (function_exists('log_yaz')) {
            log_yaz('oto_migration', 'sistem', null, 'Otomatik şema güncellemesi çalıştırıldı (v' . CURRENT_VERSION . ')');
        }

        $_SESSION['akm_schema_v'] = CURRENT_VERSION;
        return true;
    } catch (Throwable $e) {
        // Kritik hata — yöneticiye göster, ama sistemi durdurma
        if (function_exists('flash')) {
            flash('error', 'Otomatik şema güncellemesi başarısız: ' . $e->getMessage());
        }
        return false;
    }
}
