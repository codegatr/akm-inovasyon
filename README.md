# AKM İnovasyon — Cari & Proforma Teklif Sistemi

PHP 8.3 + MySQL PDO tabanlı, framework'süz, DirectAdmin paylaşımlı hosting uyumlu
cari yönetimi ve proforma teklif hazırlama sistemi.

- **Domain:** https://akm.tekcanmetal.com
- **Repo:** `codegatr/akm-inovasyon`
- **Sürüm:** v1.0.0
- **Geliştirici:** CODEGA

---

## Özellikler

- 🏢 **Cari Yönetimi** — Müşteri / Tedarikçi / Her İkisi; TRY/USD/EUR varsayılan para birimi.
- 📋 **Proforma Teklif** — TRY/USD/EUR destekli, kalem bazlı iskonto/KDV, otomatik numaralandırma.
- 📧 **Gmail SMTP** — Native STARTTLS istemci (PHPMailer bağımlılığı yok). App Password ile çalışır.
- 🔗 **Herkese Açık Teklif Linki** — Müşteri 48 karakterlik tokenle teklifi tarayıcıda görüntüler, ilk görüntüleme damgalanır.
- 🖨️ **PDF / Yazdır** — Tarayıcının print-to-PDF özelliği ile A4 formatında yazdırma.
- 👤 **Kullanıcı & Rol** — Admin / Kullanıcı ayrımı, her kullanıcı için aktif/pasif.
- 📊 **Dashboard** — Aylık toplamlar para birimi bazında + son 8 teklif.
- 📝 **İşlem Günlüğü** — Tüm CRUD aksiyonları IP/UserAgent ile loglanır.
- 🔄 **GitHub Releases Updater** — Tek tıkla ZIP tabanlı güncelleme; `config.php` ve `uploads/` korunur.
- 🔒 **Güvenlik** — CSRF koruması, PDO prepared statements, CSP header, HTTPS zorunluluğu.

---

## Sistem Gereksinimleri

- **PHP 8.3+**
- **MySQL 5.7+** veya **MariaDB 10.3+**
- PHP eklentileri: `pdo_mysql`, `openssl`, `curl`, `zip`, `mbstring`
- **Apache/LiteSpeed** (`.htaccess` destekli)
- Gmail hesabı için **İki Adımlı Doğrulama** + **App Password** (e-posta gönderimi için)

---

## Kurulum

### 1. Dosyaları Yükle

ZIP içeriğini `akm.tekcanmetal.com` document root'una açın. DirectAdmin'de genellikle:

```
~/domains/tekcanmetal.com/public_html/akm/
```

veya subdomain root'u.

### 2. Veritabanı Oluştur

DirectAdmin → MySQL Management → Yeni DB + Kullanıcı oluşturun. Not: Veritabanı adı ve kullanıcıda Türkçe karakter kullanmayın.

### 3. `config.php` Oluştur

```bash
cp config.sample.php config.php
```

Açın ve düzenleyin:

```php
define('DB_HOST',        'localhost');
define('DB_NAME',        'kullanici_akm');
define('DB_USER',        'kullanici_akm');
define('DB_PASS',        '***');
define('DB_PREFIX',      'akm_');

define('SITE_URL',       'https://akm.tekcanmetal.com');
define('APP_KEY',        '[64-haneli-hex]');  // Aşağıdan üretin

define('UPDATE_REPO',    'codegatr/akm-inovasyon');
define('CURRENT_VERSION','1.0.0');
define('UPDATE_TOKEN',   '');  // Private repo ise PAT token
```

**APP_KEY** üretmek için terminalde:

```bash
php -r 'echo bin2hex(random_bytes(32)) . "\n";'
```

### 4. Kurulum Sihirbazı

Tarayıcıdan aç:

```
https://akm.tekcanmetal.com/install.php
```

- Veritabanı tablolarını oluşturur.
- İlk yönetici (admin) hesabını açar.

### 5. `install.php` Dosyasını Sil

**Güvenlik için zorunlu:**

```bash
rm install.php
```

### 6. Gmail SMTP Yapılandır

Panelde **Ayarlar → E-posta**:

1. `myaccount.google.com/apppasswords` adresinden 16 haneli App Password alın.
2. `smtp_user` → Gmail adresiniz
3. `smtp_pass` → App Password (normal Gmail şifresi değil!)
4. `smtp_from_email` → Gmail adresiniz (Gmail, from:user@gmail.com dışını kabul etmez)
5. Kaydet → **Bağlantıyı Test Et** → **Test Maili Gönder**

### 7. Şirket Bilgilerini Doldur

**Ayarlar → Şirket Bilgileri** (teklif başlığında görünür).

---

## Güncelleme

**Panel → Sistem Güncelleme** → Yeni sürüm varsa "Güncelle" butonuna tıklayın.

Güncelleme motoru:

1. GitHub'dan ZIP indirir.
2. `config.php`, `uploads/`, `install.php`, `.htaccess.local` dosyalarını **dokunmadan** korur.
3. Diğer dosyaları yeni sürümle değiştirir.
4. `sql/install.sql` varsa idempotent çalıştırır (`CREATE TABLE IF NOT EXISTS`).
5. `config.php` içindeki `CURRENT_VERSION` değerini otomatik günceller.

### Yeni Sürüm Yayınlamak (geliştirici için)

1. Kod değişikliklerini commit + push et.
2. `CHANGELOG.md`'ye yeni sürüm notlarını ekle.
3. `manifest.json` içindeki `version` alanını güncelle.
4. GitHub'da yeni tag + Release oluştur (örn. `v1.0.1`).
5. Release'e ZIP dosyasını asset olarak ekle: `akm-inovasyon-v1.0.1.zip`.

---

## Dosya Yapısı

```
akm/
├── assets/              Statik dosyalar
│   ├── css/app.css
│   ├── js/app.js
│   └── img/logo.png
├── includes/            Ortak kütüphaneler
│   ├── auth.php         Session / giriş
│   ├── db.php           PDO singleton
│   ├── functions.php    Yardımcılar
│   ├── header.php       HTML layout başlığı
│   ├── footer.php       HTML layout sonu
│   └── mailer.php       Native SMTP istemcisi
├── modules/             Yönetim paneli modülleri
│   ├── dashboard.php
│   ├── cari.php
│   ├── cari_form.php
│   ├── teklif.php
│   ├── teklif_form.php
│   ├── teklif_goruntule.php
│   ├── teklif_pdf.php
│   ├── teklif_gonder.php
│   ├── kullanici.php
│   ├── kullanici_form.php
│   ├── ayarlar.php
│   ├── log.php
│   └── guncelleme.php
├── sql/
│   └── install.sql      Şema
├── uploads/             Yükleme klasörü (korunur)
├── .htaccess            Güvenlik + HTTPS
├── config.sample.php    Config şablonu
├── config.php           (sizin oluşturacağınız, GitIgnore)
├── index.php            Giriş sayfası
├── yonetim.php          Admin dispatcher
├── teklif.php           Public teklif görüntüleme
├── update.php           Güncelleme motoru
├── install.php          İlk kurulum (sonra silinir)
├── logout.php
├── manifest.json
├── CHANGELOG.md
└── README.md            (bu dosya)
```

---

## Mimari Prensipler

- **Framework yok.** Sadece native PHP 8.3 + PDO.
- **Tek config dosyası** (`config.php`) — git'e girmez, update'te korunur.
- **Tablo prefix'i** (`akm_`) — aynı DB'de başka projeyle çakışmaz.
- **Modül dispatcher** — `yonetim.php?sayfa=X` whitelist ile route eder; doğrudan erişim yasak.
- **CSRF her form**, **PDO her sorgu** (emulate_prepares=false).
- **Türkçe karakter yasağı:** href / Location / filename / sayfa parametrelerinde. Görünür metinlerde serbest.

---

## Bilinen Sınırlar

- **PDF gerçek dosya değil** — Tarayıcının yazdır → PDF olarak kaydet özelliği kullanılır. Mail ekine PDF gömülmek istenirse mPDF/TCPDF eklenmeli.
- **LiteSpeed 103KB çıktı sınırı** — Büyük sayfalar harici CSS/JS ile çözülmüştür.

---

## Lisans

Proprietary — CODEGA tarafından AKM İnovasyon Dış Ticaret Ltd. Şti. için geliştirilmiştir.
