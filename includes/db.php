<?php
/**
 * Veritabanı bağlantısı — PDO singleton
 */

if (!defined('ROOT_PATH')) { http_response_code(403); exit('Forbidden'); }

class DB {
    private static ?PDO $conn = null;

    public static function conn(): PDO {
        if (self::$conn === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            try {
                self::$conn = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci, time_zone = '+03:00'",
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                if (DEBUG) {
                    die('DB Bağlantı Hatası: ' . $e->getMessage());
                }
                die('Veritabanı bağlantısı kurulamadı.');
            }
        }
        return self::$conn;
    }

    /** Prefix'li tablo adı üret */
    public static function t(string $name): string {
        return DB_PREFIX . $name;
    }
}

function db(): PDO { return DB::conn(); }
function tbl(string $name): string { return DB::t($name); }
