-- =====================================================================
-- AKM İnovasyon Dış Ticaret - Cari & Proforma Teklif Sistemi
-- Veritabanı Kurulum Şeması
-- MySQL 5.7+ / MariaDB 10.3+
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ---------------------------------------------------------------------
-- Kullanıcılar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akm_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `ad_soyad` VARCHAR(128) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `rol` ENUM('admin','kullanici') NOT NULL DEFAULT 'kullanici',
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `son_giris` DATETIME DEFAULT NULL,
  `son_giris_ip` VARCHAR(45) DEFAULT NULL,
  `olusturma_tarihi` DATETIME NOT NULL,
  `guncelleme_tarihi` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cariler (Müşteri / Tedarikçi)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akm_cari` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cari_kodu` VARCHAR(32) NOT NULL,
  `firma_adi` VARCHAR(255) NOT NULL,
  `yetkili_adi` VARCHAR(128) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `telefon` VARCHAR(32) DEFAULT NULL,
  `adres` TEXT,
  `ulke` VARCHAR(64) DEFAULT 'Türkiye',
  `sehir` VARCHAR(64) DEFAULT NULL,
  `vkn_tckn` VARCHAR(20) DEFAULT NULL,
  `vergi_dairesi` VARCHAR(128) DEFAULT NULL,
  `tip` ENUM('musteri','tedarikci','ikisi') NOT NULL DEFAULT 'musteri',
  `para_birimi` ENUM('TRY','USD','EUR') NOT NULL DEFAULT 'TRY',
  `bakiye` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `notlar` TEXT,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `olusturan_id` INT UNSIGNED DEFAULT NULL,
  `olusturma_tarihi` DATETIME NOT NULL,
  `guncelleme_tarihi` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cari_kodu` (`cari_kodu`),
  KEY `idx_firma_adi` (`firma_adi`),
  KEY `idx_tip` (`tip`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Proforma Teklifler
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akm_teklif` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teklif_no` VARCHAR(32) NOT NULL,
  `cari_id` INT UNSIGNED NOT NULL,
  `tarih` DATE NOT NULL,
  `gecerlilik_tarihi` DATE DEFAULT NULL,
  `para_birimi` ENUM('TRY','USD','EUR') NOT NULL DEFAULT 'TRY',
  `doviz_kuru` DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
  `kdv_dahil` TINYINT(1) NOT NULL DEFAULT 0,
  `ara_toplam` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `iskonto_orani` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `iskonto_tutari` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `kdv_toplam` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `genel_toplam` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `odeme_sekli` VARCHAR(128) DEFAULT NULL,
  `teslim_sekli` VARCHAR(128) DEFAULT NULL,
  `notlar` TEXT,
  `sartlar` TEXT,
  `durum` ENUM('taslak','gonderildi','goruldu','onaylandi','reddedildi','iptal') NOT NULL DEFAULT 'taslak',
  `view_token` VARCHAR(64) DEFAULT NULL,
  `gonderim_tarihi` DATETIME DEFAULT NULL,
  `gorulme_tarihi` DATETIME DEFAULT NULL,
  `arsivlendi_tarihi` DATETIME DEFAULT NULL,
  `olusturan_id` INT UNSIGNED DEFAULT NULL,
  `olusturma_tarihi` DATETIME NOT NULL,
  `guncelleme_tarihi` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_teklif_no` (`teklif_no`),
  UNIQUE KEY `uk_view_token` (`view_token`),
  KEY `idx_cari_id` (`cari_id`),
  KEY `idx_tarih` (`tarih`),
  KEY `idx_durum` (`durum`),
  KEY `idx_arsiv` (`arsivlendi_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Proforma Teklif Kalemleri
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akm_teklif_kalem` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teklif_id` INT UNSIGNED NOT NULL,
  `sira` INT NOT NULL DEFAULT 0,
  `urun_adi` VARCHAR(255) NOT NULL,
  `aciklama` TEXT,
  `miktar` DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  `birim` VARCHAR(16) NOT NULL DEFAULT 'Adet',
  `birim_fiyat` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `iskonto_orani` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `kdv_orani` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  `satir_toplam` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_teklif_id` (`teklif_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Ayarlar (Key-Value)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akm_ayarlar` (
  `anahtar` VARCHAR(64) NOT NULL,
  `deger` TEXT,
  `guncelleme_tarihi` DATETIME DEFAULT NULL,
  PRIMARY KEY (`anahtar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- İşlem Günlüğü (Audit Log)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akm_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kullanici_id` INT UNSIGNED DEFAULT NULL,
  `kullanici_adi` VARCHAR(64) DEFAULT NULL,
  `islem` VARCHAR(64) NOT NULL,
  `modul` VARCHAR(32) DEFAULT NULL,
  `kayit_id` INT UNSIGNED DEFAULT NULL,
  `aciklama` TEXT,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `tarih` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_kullanici_id` (`kullanici_id`),
  KEY `idx_tarih` (`tarih`),
  KEY `idx_modul` (`modul`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Teklif Gönderim Geçmişi
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akm_teklif_gonderim` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teklif_id` INT UNSIGNED NOT NULL,
  `alici_email` VARCHAR(191) NOT NULL,
  `cc` VARCHAR(500) DEFAULT NULL,
  `konu` VARCHAR(255) NOT NULL,
  `mesaj` TEXT,
  `durum` ENUM('basarili','hata') NOT NULL,
  `hata_mesaji` TEXT,
  `gonderen_id` INT UNSIGNED DEFAULT NULL,
  `gonderim_tarihi` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teklif_id` (`teklif_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Banka Hesapları
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akm_banka_hesap` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `banka_adi` VARCHAR(128) NOT NULL,
  `sube_adi` VARCHAR(128) DEFAULT NULL,
  `sube_kodu` VARCHAR(32) DEFAULT NULL,
  `hesap_sahibi` VARCHAR(128) NOT NULL,
  `hesap_no` VARCHAR(64) DEFAULT NULL,
  `iban` VARCHAR(34) DEFAULT NULL,
  `swift` VARCHAR(16) DEFAULT NULL,
  `para_birimi` ENUM('TRY','USD','EUR') NOT NULL DEFAULT 'TRY',
  `sira` INT NOT NULL DEFAULT 0,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `notlar` TEXT,
  `olusturan_id` INT UNSIGNED DEFAULT NULL,
  `olusturma_tarihi` DATETIME NOT NULL,
  `guncelleme_tarihi` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pb_aktif` (`para_birimi`, `aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ---------------------------------------------------------------------
-- Varsayılan Ayarlar
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `akm_ayarlar` (`anahtar`, `deger`, `guncelleme_tarihi`) VALUES
('sirket_adi',        'AKM İnovasyon Dış Ticaret Ltd. Şti.', NOW()),
('sirket_adres',      '',                                     NOW()),
('sirket_telefon',    '',                                     NOW()),
('sirket_email',      '',                                     NOW()),
('sirket_web',        'https://akm.tekcanmetal.com',          NOW()),
('sirket_vkn',        '',                                     NOW()),
('sirket_vergi_dairesi','',                                   NOW()),
('sirket_logo',       '',                                     NOW()),
('teklif_prefix',     'AKM',                                  NOW()),
('teklif_onek_yil',   '1',                                    NOW()),
('teklif_sayac',      '1',                                    NOW()),
('varsayilan_kdv',    '20',                                   NOW()),
('kur_usd_try',       '0',                                    NOW()),
('kur_eur_try',       '0',                                    NOW()),
('kur_guncelleme',    '',                                     NOW()),
('smtp_host',         'smtp.gmail.com',                       NOW()),
('smtp_port',         '587',                                  NOW()),
('smtp_secure',       'tls',                                  NOW()),
('smtp_user',         '',                                     NOW()),
('smtp_pass',         '',                                     NOW()),
('smtp_from_name',    'AKM İnovasyon Dış Ticaret',            NOW()),
('smtp_from_email',   '',                                     NOW()),
('mail_imza',         '<p>Saygılarımızla,<br><strong>AKM İnovasyon Dış Ticaret Ltd. Şti.</strong></p>', NOW());
