# Personel Maaş & Fazla Mesai Takip Sistemi

## 📋 Genel Bakış

Bu sistem, Excel tabanlı personel maaş ve fazla mesai takip işlemlerini web tabanlı bir uygulamaya dönüştürmek için geliştirilmiştir. Sistem, personel bordroları, fazla mesai takibi, avans ve tazminat yönetimini tek bir platformda toplar.

## 🎯 Mevcut Özellikler

### ✅ Tamamlanan Modüller

1. **Personel Listesi Yönetimi**
   - Personel ekleme, düzenleme, silme
   - Maaş, SGK maaşı, mesai saat ücreti bilgileri
   - Banka bilgileri (banka adı, IBAN)
   - İşe giriş tarihi ve durum takibi
   - Aktif/Pasif personel yönetimi

2. **Bordro Yönetimi**
   - Aylık bordro kayıtları
   - Brüt maaş, SGK/Banka kesintileri, ek ödenek
   - Otomatik toplam ödeme hesaplama
   - Ay ve yıl bazlı filtreleme

3. **Fazla Mesai Takibi**
   - Günlük fazla mesai kayıtları
   - Personel bazlı saat ücreti otomatik çekme
   - Otomatik tutar hesaplama
   - Ödeme durumu takibi

4. **Avans Takibi**
   - Personel bazlı avans kayıtları
   - Tarih ve tutar takibi

5. **Tazminat Takibi**
   - Personel tazminat kayıtları
   - Plan ve işlem takibi

6. **Raporlar**
   - Aylık toplam bordro raporları
   - Fazla mesai saat toplamları
   - Avans ve tazminat özetleri
   - Personel bazlı bordro dağılımı

## 🔧 Teknik Gereksinimler

### Sunucu Gereksinimleri
- **PHP**: 7.4 veya üzeri
- **MySQL**: 8.0 veya üzeri
- **Web Sunucusu**: Apache/Nginx
- **PDO Extension**: MySQL için aktif olmalı

### Veritabanı
- **Veritabanı Adı**: `zersoftn_personel_takip`
- **Karakter Seti**: UTF8MB4
- **Tablo Sayısı**: 6 ana tablo

## 📦 Kurulum (cPanel)

### 1. Dosyaları Yükleme
```bash
# Proje dosyalarını cPanel File Manager veya FTP ile yükleyin
# Tüm dosyaları public_html altına veya bir alt klasöre kopyalayın
```

### 2. Veritabanı Kurulumu
1. cPanel → MySQL Databases
2. Yeni veritabanı oluşturun: `zersoftn_personel_takip`
3. Yeni kullanıcı oluşturun ve veritabanına yetki verin
4. phpMyAdmin'e giriş yapın
5. `database.sql` dosyasını içe aktarın (Import)

### 3. Veritabanı Bağlantı Ayarları
`config/db.php` dosyasını düzenleyin:
```php
$host = 'localhost'; // veya cPanel'de belirtilen host
$dbname = 'cpanel_kullaniciadi_personel_takip'; // cPanel veritabanı adı
$username = 'cpanel_kullaniciadi_dbuser'; // cPanel veritabanı kullanıcı adı
$password = 'VERITABANI_SIFRESI'; // Veritabanı şifresi
```

### 4. Veri Yükleme
1. `personel_verileri_guncel.sql` dosyasını içe aktarın
2. `fix_para_degerleri.sql` dosyasını çalıştırın (para değerlerini düzeltmek için)

### 5. Dosya İzinleri
```bash
# Gerekirse yazma izinleri verin (log dosyaları vb.)
chmod 755 config/
chmod 644 config/db.php
```

## 📊 Veritabanı Şeması

### Ana Tablolar

1. **personel_listesi**
   - Personel temel bilgileri
   - Maaş ve SGK maaş bilgileri
   - Banka bilgileri

2. **bordro**
   - Aylık bordro kayıtları
   - Brüt maaş, kesintiler, ek ödenekler
   - Otomatik toplam hesaplama

3. **fazla_mesai**
   - Günlük fazla mesai kayıtları
   - Saat ve ücret bilgileri
   - Ödeme durumu

4. **avans_takip**
   - Personel avans kayıtları

5. **tazminat_takip**
   - Tazminat kayıtları

6. **rapor_ozet**
   - Aylık özet raporlar

## 🚀 Kullanım Senaryoları

### Excel'den Web'e Geçiş Karşılaştırması

#### Excel İş Akışı → Web Karşılığı

| Excel İşlemi | Web Modülü | Durum |
|-------------|-----------|-------|
| Personel bilgilerini formülle çekme | Personel seçimi ile otomatik doldurma | ⚠️ Kısmen var |
| Ay sonu bordro oluşturma | Bordro Yönetimi | ✅ Var |
| Kesintiler ve izinler | Kesintiler modülü | ❌ Eksik |
| Banka/Nakit ayrımı | Ödeme tipi | ❌ Eksik |
| Aylık toplam banka ödemesi | Raporlar (toplam) | ⚠️ Kısmen var |
| Aylık toplam nakit ödemesi | Raporlar (nakit) | ❌ Eksik |
| Günlük fazla mesai kaydı | Fazla Mesai Takibi | ✅ Var |
| Fazla mesai ücretini formülle çekme | Otomatik ücret çekme | ✅ Var |
| Kümülatif fazla mesai toplamı | Kümülatif toplam | ❌ Eksik |
| Ödeme yapınca eksi değerle düşme | Ödeme işlemi | ❌ Eksik |

## ⚠️ Eksik Özellikler ve Geliştirme Planı

### Öncelik 1: Bordro Modülü İyileştirmeleri

#### 1.1 Otomatik Veri Çekme
- [ ] Personel seçildiğinde maaş bilgilerinin otomatik gelmesi
- [ ] `maas_sgk` değerinin otomatik doldurulması
- [ ] Banka bilgilerinin otomatik gösterilmesi
- [ ] Mesai saat ücretinin otomatik gelmesi

#### 1.2 Kesintiler ve İzinler Modülü
- [ ] İzin günleri takibi
- [ ] İzin kesintileri hesaplama
- [ ] SGK kesintileri
- [ ] Diğer kesintiler (vergi, icra vb.)
- [ ] Kesinti açıklamaları

#### 1.3 Ödeme Tipi Ayrımı
- [ ] Bordro tablosuna `odeme_tipi` alanı ekleme (BANKA/NAKIT)
- [ ] Bordro oluştururken ödeme tipi seçimi
- [ ] Aylık toplam banka ödemesi raporu
- [ ] Aylık toplam nakit ödemesi raporu
- [ ] Raporlar sayfasında banka/nakit ayrımı

#### 1.4 Toplu Bordro Oluşturma
- [ ] Seçilen ay için tüm aktif personelleri listeleme
- [ ] Toplu bordro oluşturma sayfası
- [ ] Personel bazlı maaş bilgilerini otomatik doldurma
- [ ] Toplu düzenleme ve kaydetme

### Öncelik 2: Fazla Mesai Modülü İyileştirmeleri

#### 2.1 Kümülatif Toplam
- [ ] Personel bazlı kümülatif fazla mesai toplamı görüntüleme
- [ ] Ödenmemiş fazla mesai toplamı
- [ ] Dönem bazlı (aylık/yıllık) toplamlar

#### 2.2 Ödeme İşlemi
- [ ] Fazla mesai ödeme kaydı oluşturma
- [ ] Ödeme yapıldığında otomatik eksi değerle düşme
- [ ] Ödeme geçmişi görüntüleme
- [ ] Kısmi ödeme desteği

#### 2.3 Fazla Mesai Raporları
- [ ] Personel bazlı fazla mesai özeti
- [ ] Aylık/yıllık fazla mesai raporları
- [ ] Ödenmemiş fazla mesai listesi

### Öncelik 3: Genel İyileştirmeler

#### 3.1 Bordro İşlem Dosyası
- [ ] `bordro_islem.php` dosyası oluşturma
- [ ] Bordro ekleme işlevi
- [ ] Bordro düzenleme işlevi
- [ ] Bordro silme işlevi

#### 3.2 Fazla Mesai İşlem Dosyası
- [ ] `fazla_mesai_islem.php` dosyası oluşturma
- [ ] Fazla mesai ekleme işlevi
- [ ] Fazla mesai düzenleme işlevi
- [ ] Fazla mesai silme işlevi
- [ ] Ödeme işaretleme işlevi

#### 3.3 Personel İşlem Dosyası İyileştirmeleri
- [ ] Personel düzenleme işlevi
- [ ] Personel silme işlevi
- [ ] Form validasyonları

#### 3.4 Arayüz İyileştirmeleri
- [ ] JavaScript ile dinamik form doldurma
- [ ] Ajax ile sayfa yenilemeden işlemler
- [ ] Hata ve başarı mesajları
- [ ] Loading göstergeleri

## 📝 Veritabanı Güncellemeleri

### Gerekli ALTER TABLE Komutları

```sql
-- Bordro tablosuna ödeme tipi ekleme
ALTER TABLE bordro 
ADD COLUMN odeme_tipi ENUM('BANKA', 'NAKIT') DEFAULT 'BANKA' AFTER ek_odenek;

-- Bordro tablosuna kesintiler ekleme
ALTER TABLE bordro 
ADD COLUMN izin_gunu DECIMAL(5,2) DEFAULT 0 AFTER ek_odenek,
ADD COLUMN izin_kesintisi DECIMAL(10,2) DEFAULT 0 AFTER izin_gunu,
ADD COLUMN sgk_kesintisi DECIMAL(10,2) DEFAULT 0 AFTER izin_kesintisi,
ADD COLUMN diger_kesintiler DECIMAL(10,2) DEFAULT 0 AFTER sgk_kesintisi,
ADD COLUMN kesinti_aciklama TEXT AFTER diger_kesintiler;

-- Fazla mesai tablosuna ödeme tarihi ekleme
ALTER TABLE fazla_mesai 
ADD COLUMN odeme_tarihi DATE NULL AFTER odendi;

-- Fazla mesai tablosuna ödeme tutarı ekleme (kısmi ödeme için)
ALTER TABLE fazla_mesai 
ADD COLUMN odeme_tutari DECIMAL(10,2) DEFAULT 0 AFTER tutar;
```

## 🔐 Güvenlik Notları

1. **Veritabanı Bağlantısı**
   - `config/db.php` dosyası kesinlikle public erişimden korunmalı
   - Şifreler güçlü olmalı

2. **SQL Injection**
   - Tüm sorgular PDO prepared statements kullanıyor ✅
   - Kullanıcı girdileri escape ediliyor ✅

3. **XSS Koruması**
   - `escape()` fonksiyonu kullanılıyor ✅

4. **Dosya İzinleri**
   - Config dosyaları 644 izni ile korunmalı
   - Yazma gerektiren klasörler 755 izni ile olmalı

## 📞 Destek ve Geliştirme

### Geliştirme Notları
- Sistem PHP PDO kullanıyor
- Bootstrap 5 ile responsive tasarım
- Bootstrap Icons kullanılıyor
- Türkçe karakter desteği mevcut (UTF8MB4)

### İyileştirme Önerileri
1. Kullanıcı yetkilendirme sistemi eklenebilir
2. Excel export/import özelliği eklenebilir
3. E-posta bildirimleri eklenebilir
4. Mobil uyumluluk iyileştirilebilir
5. Gelişmiş raporlama ve grafikler eklenebilir

## 📄 Lisans

Bu proje özel kullanım için geliştirilmiştir.

---

**Son Güncelleme**: 2024
**Versiyon**: 1.0.0
