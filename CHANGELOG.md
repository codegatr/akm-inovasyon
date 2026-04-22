# Değişiklik Günlüğü

## v1.0.3 — 2026-04-22

### Eklendi
- 🖼️ **Logo Yükleme** — Ayarlar → Şirket Bilgileri'ne PNG/JPG/WebP/SVG yükleme (max 2MB).
  - Yüklenen logo `uploads/logo.{ext}` olarak saklanır, güncellemelerde korunur.
  - Teklif admin görüntüleme, PDF ve müşteri linkinde kullanılır.
  - Logo yoksa eski "AKM" metin blokuna düşer.
- 🏦 **Banka Hesapları Modülü** (Yönetim → Banka Hesapları)
  - Banka adı, şube adı, şube kodu, hesap sahibi, hesap no, IBAN, SWIFT/BIC, para birimi (TRY/USD/EUR).
  - IBAN otomatik 4'erli gruplanır (TR00 0000 0000 0000...).
  - Türkiye'deki 25 yaygın banka autocomplete listesi.
  - Aktif/pasif durumu, liste sıralaması.
  - Aktif hesaplar teklif çıktılarında para birimine göre listelenir.
- 📄 **Teklif Listesinde Direkt PDF** — Liste ekranındaki `📄 PDF` butonu yeni sekmede PDF sayfasını açıp yazdırma diyaloğunu otomatik tetikler.
- 📦 **Arşiv Sistemi**
  - Teklifleri listeden "Arşive Kaldır" ile gizleyebilirsiniz.
  - "Aktif" ve "Arşiv" sekmeleri — sayım badge'leri ile.
  - "Geri Al" butonu ile arşivden geri alma.
  - Yeni kolon: `akm_teklif.arsivlendi_tarihi` (otomatik migration).

### Değişti
- **KDV Etiketi Akıllandı:** Teklifte tek KDV oranı varsa etiket "KDV %20" olarak gösterilir; birden fazla oran varsa dağılım + "Toplam KDV" gösterilir.
- **Ayarlardan IBAN kaldırıldı** — Banka Hesapları modülüne taşındı. Daha zengin banka bilgileri için.
- **`includes/functions.php`**: `sirket_logo()`, `banka_hesaplari($pb)`, `iban_format()` yardımcıları eklendi.
- **`includes/header.php`**: Sidebar'a 🏦 Banka Hesapları eklendi.

### Veritabanı
- Yeni tablo: `akm_banka_hesap`
- Yeni sütun: `akm_teklif.arsivlendi_tarihi` (idempotent migration)
- Yeni ayar: `sirket_logo`

## v1.0.2 — 2026-04-22

### Eklendi
- 🏦 **TCMB Döviz Kuru Entegrasyonu**
  - Kaynak: `https://www.tcmb.gov.tr/kurlar/today.xml` (resmi, ücretsiz)
  - Baz alınan kur: **Efektif Satış** (BanknoteSelling)
  - Ayarlar → Döviz Kurları → **TCMB'den Güncelle** butonu ile manuel çekme
  - Aynı gün içinde cache (TCMB'yi yormamak için)
  - Son güncelleme tarih/kaynak bilgisi Ayarlar sayfasında görünür
- 💱 **Teklif formunda canlı kur**
  - Para birimi USD/EUR seçilince kaydedilmiş kur otomatik doluyor
  - Kur alanının yanında 🏦 **TCMB** butonu — anlık güncel kur çekimi
  - Çekilen kur hem teklife hem ayarlara kaydediliyor
  - TCMB ulaşılamazsa manuel override her zaman mümkün
- `includes/tcmb.php` — TCMB helper (`tcmb_kurlari_cek`, `tcmb_kur_guncelle`)
- `modules/kur_api.php` — JSON endpoint (yalnızca giriş yapmış kullanıcılar)

## v1.0.1 — 2026-04-22

### Eklendi
- 📊 **KDV Oranlarına Göre Dağılım** — Birden fazla KDV oranı (örn. %10 ve %20) içeren tekliflerde:
  - Teklif formunda canlı toplam kutusunda her KDV oranı için ayrı satır ve matrah gösterimi.
  - Teklif görüntüleme, PDF ve müşteri linkinde de aynı dağılım gösteriliyor.
  - Tek KDV oranı varsa yalnızca o oran etiketi ("KDV %20") gösterilir, eskisi gibi.
- `includes/functions.php` içine `kdv_dagilimi()` helper'ı eklendi.

## v1.0.0 — 2026-04-22

İlk sürüm.

### Eklendi
- 🏢 Cari Yönetimi modülü (Müşteri / Tedarikçi / Her İkisi)
  - Otomatik cari kodu (C00001)
  - TRY/USD/EUR varsayılan para birimi
  - Ülke seçimi (25 ülke)
- 📋 Proforma Teklif modülü
  - Otomatik teklif numarası (`AKM-2026-0001`)
  - Kalem bazlı iskonto ve KDV
  - Genel iskonto
  - Dinamik kalem ekle/sil
  - Canlı JS hesaplama (TR sayı formatı)
  - Ödeme/Teslim şekli
  - Şartlar metni
  - Durum yönetimi (Taslak/Gönderildi/Görüldü/Onaylandı/Reddedildi/İptal)
- 📧 Gmail SMTP E-posta Gönderimi
  - Native STARTTLS istemci (PHPMailer bağımlılığı yok)
  - App Password desteği
  - HTML + plain text multipart
  - UTF-8 Base64 header kodlama (Türkçe karakterler)
  - Bağlantı testi ve test mail gönderimi
- 🔗 Herkese Açık Teklif Linki
  - 48 karakter hex token
  - İlk görüntülemede durum otomatik "Görüldü" olur
  - IP loglanır
- 🖨️ PDF / Yazdırma
  - A4 optimize, tarayıcı print-to-PDF
- 👤 Kullanıcı Yönetimi
  - Admin / Kullanıcı rol ayrımı
  - Aktif/Pasif durum
  - Kendi rolünü değiştirme koruması
- ⚙️ Ayarlar
  - Şirket bilgileri (başlıkta görünür)
  - SMTP yapılandırması
  - Teklif numara ön eki ve sayaç
  - Varsayılan KDV oranı
  - Döviz kuru (TRY/USD/EUR)
- 📝 İşlem Günlüğü
  - IP + UserAgent
  - Modül ve kullanıcı filtresi
  - Eski logları toplu silme
- 🔄 GitHub Releases Updater
  - Tek tıkla ZIP güncelleme
  - `config.php` ve `uploads/` otomatik korunur
  - `CURRENT_VERSION` otomatik yazılır
  - SQL şeması idempotent çalıştırılır
- 🛠️ Kurulum sihirbazı (`install.php`)
- 🔒 Güvenlik
  - CSRF her formda
  - PDO emulate_prepares=false
  - HTTPS zorunluluğu (`.htaccess`)
  - Hassas dosyaların web erişiminden korunması
  - X-Content-Type-Options, X-Frame-Options, Referrer-Policy, HSTS header'ları

### Mimari
- PHP 8.3+ native
- MySQL/MariaDB PDO
- Tablo prefix: `akm_`
- Tek config dosyası (`config.php`)
- Modül dispatcher (`yonetim.php?sayfa=X`)
- DirectAdmin paylaşımlı hosting uyumlu
