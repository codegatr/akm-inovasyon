<?php
/**
 * Otomatik Şema Migration
 *
 * Yönetim panelinde ilk erişimde (oturum başına bir kez) çalışır.
 * İki aşamalı:
 *  1) install.sql içindeki CREATE TABLE IF NOT EXISTS blokları idempotent çalıştırılır.
 *  2) PHP tarafında koşullu ALTER TABLE'lar uygulanır (information_schema kontrolüyle).
 *
 * Her SQL ifadesi query() + closeCursor() ile çalıştırılır —
 * PDO unbuffered query hataları (SQLSTATE HY000 / 2014) önlenir.
 */
if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

/**
 * Tek bir SQL ifadesini güvenli çalıştırır.
 * query() döner PDOStatement; closeCursor() unbuffered query hatasını önler.
 */
function safe_exec(string $q): bool {
    try {
        $stmt = db()->query($q);
        if ($stmt instanceof PDOStatement) {
            $stmt->closeCursor();
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * install.sql'i parçala ve sırayla çalıştır.
 * Dönen değer: [okCount, warnCount].
 */
function run_install_sql(?string $sqlFile = null): array {
    $sqlFile = $sqlFile ?? ROOT_PATH . '/sql/install.sql';
    if (!is_file($sqlFile)) return [0, 0];

    $sql = file_get_contents($sqlFile);
    $clean = preg_replace('/--[^\n]*\n/', "\n", $sql);
    $ifadeler = array_filter(array_map('trim', explode(';', $clean)));

    $ok = 0; $warn = 0;
    foreach ($ifadeler as $q) {
        if ($q === '' || str_starts_with($q, '/*')) continue;
        if (safe_exec($q)) $ok++; else $warn++;
    }
    return [$ok, $warn];
}

/**
 * PHP tarafında koşullu ALTER TABLE'lar.
 * Gelecek şema değişiklikleri buraya eklenir (idempotent).
 */
function run_php_migrations(): void {
    $prefix = DB_PREFIX;

    // === v1.0.3: akm_teklif.arsivlendi_tarihi kolonu ===
    $has = 0;
    $stmt = db()->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '{$prefix}teklif'
           AND COLUMN_NAME = 'arsivlendi_tarihi'"
    );
    if ($stmt) { $has = (int)$stmt->fetchColumn(); $stmt->closeCursor(); }

    if (!$has) {
        safe_exec("ALTER TABLE `{$prefix}teklif`
                   ADD COLUMN `arsivlendi_tarihi` DATETIME DEFAULT NULL AFTER `gorulme_tarihi`,
                   ADD KEY `idx_arsiv` (`arsivlendi_tarihi`)");
    }

    // Gelecekte eklenecek ALTER'lar buraya...
}

/**
 * Oturum başına bir kere çalışır: şema eksikse migration'ları uygular.
 */
function auto_migrate(): bool {
    if (!empty($_SESSION['akm_schema_v']) && $_SESSION['akm_schema_v'] === CURRENT_VERSION) {
        return true;
    }

    try {
        $prefix = DB_PREFIX;

        // Kritik yapıları kontrol et
        $bankaTablo = 0;
        $stmt = db()->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$prefix}banka_hesap'"
        );
        if ($stmt) { $bankaTablo = (int)$stmt->fetchColumn(); $stmt->closeCursor(); }

        $arsivKolon = 0;
        $stmt = db()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '{$prefix}teklif'
               AND COLUMN_NAME = 'arsivlendi_tarihi'"
        );
        if ($stmt) { $arsivKolon = (int)$stmt->fetchColumn(); $stmt->closeCursor(); }

        if ($bankaTablo && $arsivKolon) {
            $_SESSION['akm_schema_v'] = CURRENT_VERSION;
            return true;
        }

        // Migration gerekiyor
        run_install_sql();
        run_php_migrations();

        if (function_exists('log_yaz')) {
            log_yaz('oto_migration', 'sistem', null, 'Otomatik şema güncellemesi (v' . CURRENT_VERSION . ')');
        }

        $_SESSION['akm_schema_v'] = CURRENT_VERSION;
        return true;
    } catch (Throwable $e) {
        if (function_exists('flash')) {
            flash('error', 'Otomatik şema güncellemesi başarısız: ' . $e->getMessage());
        }
        return false;
    }
}
