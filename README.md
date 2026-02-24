# OYS – Ocak Yönetim Sistemi

**OYS** (Ocak Yönetim Sistemi), taş ocağı ve agrega üretimi yapan işletmeler için tek platformda **personel**, **kantar**, **araç**, **yakıt**, **makine tamir-bakım**, **agrega üretim** ve **orman raporları** gibi tüm yönetim süreçlerini kapsamayı hedefleyen web tabanlı bir yönetim sistemidir. Farklı ebatlarda taş ve taş tozu üretiminin takibi de bu çatı altında planlanmaktadır.

## 📋 Genel Bakış

Sistem, başta personel maaş ve fazla mesai takibi olmak üzere Excel tabanlı süreçleri web tabanlı bir uygulamaya taşımak için geliştirilmiştir. Personel bordroları, fazla mesai, avans, tazminat ve **Kantar Raporları** (perakende satış, özet rapor, özet malzeme satış, cari ekstre) tek platformda toplanır. Kullanıcı girişi, rol tabanlı yetkilendirme ve audit log ile güvenli bir yapı sunar.

## 🎯 Mevcut Özellikler

### ✅ Tamamlanan Modüller

#### 1. **Kullanıcı Yönetimi ve Güvenlik**
   - Kullanıcı giriş/çıkış sistemi
   - Rol tabanlı yetkilendirme (Admin, User, Viewer)
   - Kullanıcı ekleme, düzenleme, silme
   - Kullanıcı log kayıtları görüntüleme
   - Audit log sistemi (tüm CRUD işlemleri kaydedilir)
   - Session yönetimi ve güvenlik

#### 2. **Personel Listesi Yönetimi**
   - Personel ekleme, düzenleme, silme
   - Soft delete özelliği (silinen personeller geri alınabilir)
   - Maaş, SGK maaşı, mesai saat ücreti bilgileri
   - Banka bilgileri (banka adı, IBAN)
   - İşe giriş tarihi ve durum takibi
   - Aktif/Pasif personel yönetimi
   - Personel bazlı hızlı erişim menüsü (Bordro, Puantaj, Fazla Mesai, Avans kayıtları)

#### 3. **Bordro Yönetimi**
   - Aylık bordro kayıtları
   - Brüt maaş, SGK/Banka kesintileri
   - Kanal bazlı ek ödenek (Banka/Nakit)
   - Kanal bazlı avans entegrasyonu (Banka/Nakit)
   - İzin günleri ve kesintileri
   - SGK kesintileri
   - Diğer kesintiler ve açıklamalar
   - Otomatik toplam ödeme hesaplama (Banka Net + Nakit Net)
   - Ay ve yıl bazlı filtreleme
   - Personel bazlı filtreleme
   - Toplu bordro oluşturma
   - Bordro ödeme özeti (PDF/Yazdır desteği)
   - Mini ödeme özeti (Banka, Nakit, Toplam)

#### 4. **Puantaj Yönetimi**
   - Günlük puantaj kayıtları
   - Durum takibi (Çalıştı, İzin, Hastalık, Devamsızlık, Hafta Tatili, Resmi Tatil)
   - Saat bazlı çalışma takibi
   - Toplu puantaj girişi
   - Puantaj ekstresi (personel bazlı, dönem/tarih aralığı filtreleme)
   - PDF/Yazdır desteği

#### 5. **Fazla Mesai Takibi**
   - Günlük fazla mesai kayıtları
   - Personel bazlı saat ücreti otomatik çekme
   - Otomatik tutar hesaplama
   - Ayrı ödeme kayıt sistemi (`fazla_mesai_odeme` tablosu)
   - Tek ve toplu ödeme işlemleri
   - Ödeme listesi ve yönetimi
   - Fazla mesai ekstresi (personel bazlı, dönem/tarih aralığı filtreleme)
   - Fazla mesai raporu (dönem bazlı)
   - Toplu fazla mesai girişi
   - Filtreleme: Bugün, Bu Hafta, Bu Ay, Dönem, Tarih Aralığı
   - Personel bazlı filtreleme
   - Kümülatif toplamlar (Toplam FM, Toplam Ödeme, Bakiye)
   - PDF/Yazdır desteği

#### 6. **Avans Takibi**
   - Personel bazlı avans kayıtları
   - Kanal bazlı avans (Banka/Nakit)
   - Bordro dönemi entegrasyonu
   - Tarih ve tutar takibi
   - Personel bazlı filtreleme
   - Dönem bazlı filtreleme (Ay/Yıl)

#### 7. **Tazminat Takibi**
   - Personel tazminat kayıtları
   - Plan ve işlem takibi

#### 8. **Raporlar**
   - Aylık toplam bordro raporları
   - Kanal bazlı toplamlar (Banka/Nakit)
   - Fazla mesai saat toplamları
   - Avans ve tazminat özetleri
   - Personel bazlı bordro dağılımı
   - Ay ve yıl bazlı filtreleme

#### 9. **Kantar Raporları** (Taş Ocağı / Agrega Satış)
   - **Perakende Satış**: Tarih aralığına göre satış ve tahsilat hareketleri, müşteri/plaka filtresi, PDF export
   - **Özet Rapor**: Müşteri bazlı toplam satış, tahsilat ve bakiye (dönem + genel), bakiyesizleri gizle/göster, PDF
   - **Özet Malzeme Satış**: Malzeme (döküm tipi) bazında kg ve tutar özeti, dönemde satışı olmayanları gizle/göster, PDF
   - **Cari Ekstre**: Müşteri cari hesap ekstresi (devir, hareketler, kümülatif bakiye), Select2 ile aranabilir müşteri seçimi, PDF
   - Periyot seçenekleri: Bugün, Bu Hafta, Bu Ay, Bu Yıl; varsayılan tarih aralığı bugün
   - Raporlama veritabanı (SahadanSatis) için ayrı bağlantı (.env `DB_REPORT_*`) desteklenir

## 🔧 Teknik Gereksinimler

### Sunucu Gereksinimleri
- **PHP**: 7.4 veya üzeri
- **MySQL**: 8.0 veya üzeri
- **Web Sunucusu**: Apache/Nginx
- **PDO Extension**: MySQL için aktif olmalı
- **Session Desteği**: Aktif olmalı

### Veritabanı
- **Ana veritabanı**: Personel, bordro, puantaj, fazla mesai, avans, tazminat (örn. `zersoftn_personel_takip`)
- **Raporlama veritabanı** (isteğe bağlı): Kantar / SahadanSatis verileri için ayrı DB (`.env` içinde `DB_REPORT_*`)
- **Karakter Seti**: UTF8MB4
- **Tablo Sayısı**: 10+ ana tablo (ana DB); Kantar modülü için harici raporlama DB kullanılabilir

## 📦 Kurulum

### 1. Dosyaları Yükleme
```bash
# Proje dosyalarını cPanel File Manager veya FTP ile yükleyin
# Tüm dosyaları public_html altına veya bir alt klasöre kopyalayın
```

### 2. Veritabanı Kurulumu

#### Adım 1: Ana Veritabanı Şeması
1. cPanel → MySQL Databases
2. Yeni veritabanı oluşturun
3. Yeni kullanıcı oluşturun ve veritabanına yetki verin
4. phpMyAdmin'e giriş yapın
5. `database.sql` dosyasını içe aktarın (Import)

#### Adım 2: Migrasyonlar (Sırayla Çalıştırın)
```sql
-- 1. Avans kanal bazlı ayrımı
migration_avans_kanal.sql

-- 2. Bordro avans entegrasyonu
migration_bordro_avans.sql

-- 3. Bordro ek ödenek kanal bazlı ayrımı
migration_bordro_ek_odenek_split.sql

-- 4. Personel soft delete
migration_personel_soft_delete.sql

-- 5. Fazla mesai ödeme tablosu
migration_fazla_mesai_odeme.sql

-- 6. Fazla mesai basitleştirme
migration_fm_simplify.sql

-- 7. Kullanıcı sistemi ve audit log
migration_user_system.sql
```

### 3. Veritabanı Bağlantı Ayarları
`.env` dosyasını proje kökünde oluşturup (örnek için `.env.example` kullanın) aşağıdakileri ayarlayın:

```env
# Ana veritabanı (personel, bordro, puantaj, vb.)
DB_HOST=localhost
DB_NAME=veritabani_adi
DB_USER=kullanici
DB_PASS=sifre

# Raporlama veritabanı (Kantar Raporları – SahadanSatis)
DB_REPORT_HOST=localhost
DB_REPORT_NAME=rapor_db_adi
DB_REPORT_USER=rapor_user
DB_REPORT_PASS=rapor_sifre
```

`config/db.php` ve `config/load_env.php` bu değişkenleri kullanır. Kantar Raporları kullanılmayacaksa raporlama DB bilgileri boş bırakılabilir.

### 4. Session Klasörü
```bash
# storage/sessions klasörünün yazılabilir olduğundan emin olun
chmod 755 storage/sessions
```

### 5. İlk Giriş
- **Kullanıcı Adı**: `admin`
- **Şifre**: `admin123`
- İlk girişten sonra şifrenizi değiştirmeniz önerilir.

### 6. Dosya İzinleri
```bash
# Gerekirse yazma izinleri verin
chmod 755 config/
chmod 644 config/db.php
chmod 755 storage/
chmod 755 storage/sessions/
```

## 📊 Veritabanı Şeması

### Ana Tablolar

1. **users**
   - Kullanıcı bilgileri ve yetkilendirme
   - Rol: admin, user, viewer
   - Son giriş takibi

2. **user_logs**
   - Audit log kayıtları
   - Tüm CRUD işlemleri kaydedilir
   - Kullanıcı, işlem tipi, tablo, kayıt ID, açıklama, IP adresi

3. **personel_listesi**
   - Personel temel bilgileri
   - Maaş ve SGK maaş bilgileri
   - Banka bilgileri
   - Soft delete desteği (`aktif`, `silinme_tarihi`)
   - Audit alanları (`created_by`, `updated_by`, `updated_at`)

4. **bordro**
   - Aylık bordro kayıtları
   - Brüt maaş, SGK/Banka kesintileri
   - Kanal bazlı ek ödenek (`ek_odenek_banka`, `ek_odenek_nakit`)
   - Kanal bazlı avans (`banka_avans`, `nakit_avans`)
   - İzin günleri ve kesintileri
   - Otomatik toplam hesaplama
   - Audit alanları (`created_by`, `updated_by`, `updated_at`)

5. **puantaj**
   - Günlük puantaj kayıtları
   - Durum ve saat bilgileri
   - Audit alanları (`created_by`, `updated_by`, `updated_at`)

6. **fazla_mesai**
   - Günlük fazla mesai kayıtları
   - Saat ve ücret bilgileri
   - Otomatik tutar hesaplama
   - Audit alanları (`created_by`, `updated_by`, `updated_at`)

7. **fazla_mesai_odeme**
   - Fazla mesai ödeme kayıtları
   - Personel, ödeme tarihi, tutar
   - Audit alanları (`created_by`, `updated_by`, `updated_at`)

8. **avans_takip**
   - Personel avans kayıtları
   - Kanal bazlı avans (`banka_tutari`, `nakit_tutari`)
   - Bordro dönemi (`bordro_ay`, `bordro_yil`)
   - Audit alanları (`created_by`, `updated_by`, `updated_at`)

9. **tazminat_takip**
   - Tazminat kayıtları
   - Audit alanları (`created_by`, `updated_by`, `updated_at`)

## 🔐 Güvenlik Özellikleri

### 1. Kullanıcı Yetkilendirme
- **Admin**: Tüm yetkilere sahip, kullanıcı yönetimi yapabilir
- **User**: CRUD işlemleri yapabilir, raporları görüntüleyebilir
- **Viewer**: Sadece görüntüleme yetkisi

### 2. Session Yönetimi
- Güvenli session yönetimi
- HttpOnly cookie desteği
- Session timeout kontrolü
- Uygulama bazlı session dizini

### 3. SQL Injection Koruması
- Tüm sorgular PDO prepared statements kullanıyor ✅
- Kullanıcı girdileri escape ediliyor ✅

### 4. XSS Koruması
- `escape()` fonksiyonu kullanılıyor ✅
- HTML çıktıları temizleniyor ✅

### 5. Audit Log
- Tüm CRUD işlemleri kaydediliyor
- Kullanıcı, işlem tipi, tablo, kayıt ID, açıklama, IP adresi
- Admin kullanıcılar log kayıtlarını görüntüleyebilir

### 6. Dosya Güvenliği
- Config dosyaları public erişimden korunmalı
- Session dosyaları güvenli dizinde saklanıyor
- Şifreler hash'lenerek saklanıyor (bcrypt)

## 🚀 Kullanım Senaryoları

### Kullanıcı Girişi
1. `login.php` sayfasına gidin
2. Kullanıcı adı ve şifre ile giriş yapın
3. Rolünüze göre yetkili sayfalara erişebilirsiniz

### Bordro Oluşturma
1. **Bordro** menüsüne gidin
2. Ay ve yıl seçin
3. **Yeni Bordro Oluştur** butonuna tıklayın
4. Personel seçin, bilgileri doldurun
5. Kanal bazlı ek ödenek ve avansları girin
6. Kaydedin

### Toplu Bordro Oluşturma
1. **Bordro** menüsüne gidin
2. **Toplu Bordro Oluştur** butonuna tıklayın
3. Ay ve yıl seçin
4. Personelleri seçin
5. Bilgileri doldurun ve kaydedin

### Fazla Mesai Kaydı
1. **Fazla Mesai** menüsüne gidin
2. **FM Ekle** butonuna tıklayın
3. Personel, tarih, saat ve saat ücreti girin
4. Kaydedin

### Fazla Mesai Ödemesi
1. **Fazla Mesai** menüsüne gidin
2. **Ödeme Yap** veya **Toplu Ödeme** butonuna tıklayın
3. Personel ve tutar bilgilerini girin
4. Ödeme tarihi ve açıklama ekleyin
5. Kaydedin

### Puantaj Girişi
1. **Puantaj** menüsüne gidin
2. Ay ve yıl seçin
3. **Yeni Puantaj Ekle** butonuna tıklayın
4. Personel, tarih, durum ve saat bilgilerini girin
5. Kaydedin

### Kantar Raporları (Taş Ocağı / Agrega)
1. **Kantar Raporları** menüsüne gidin
2. Sekmeyi seçin: **Perakende Satış**, **Özet Rapor**, **Özet Malzeme Satış** veya **Cari Ekstre**
3. Periyot (Bugün, Bu Hafta, Bu Ay, Bu Yıl) veya özel başlangıç/bitiş tarihi seçin
4. Perakende’de müşteri/plaka ile filtreleyebilir; Cari’de Select2 ile müşteri arayıp seçebilirsiniz
5. **Uygula** ile listeyi güncelleyin; **PDF** ile raporu indirin

### Kullanıcı Log Görüntüleme (Admin)
1. **Kullanıcı Yönetimi** sayfasına gidin (üst menüdeki ayarlar ikonu)
2. İlgili kullanıcının **Log Kayıtları** butonuna tıklayın
3. Filtreleme yaparak log kayıtlarını görüntüleyin

## 📝 Önemli Notlar

### Para Formatları
- Sistem hem Türkçe (1.234,56) hem İngilizce (1234.56) para formatlarını destekler
- Binlik ayırıcılar otomatik olarak temizlenir
- Veritabanına ham sayısal değerler kaydedilir

### Kanal Bazlı Ödemeler
- **Banka**: Banka hesabına yapılan ödemeler
- **Nakit**: Nakit olarak yapılan ödemeler
- Avanslar ve ek ödenekler kanal bazlı ayrılabilir
- Bordro hesaplamalarında kanal bazlı toplamlar gösterilir

### Soft Delete
- Personel silme işlemi soft delete olarak çalışır
- Silinen personeller `aktif=0` olarak işaretlenir
- Silinen personeller geri alınabilir
- Silinen personeller filtrelenerek görüntülenebilir

### Audit Log
- Tüm INSERT, UPDATE, DELETE işlemleri otomatik olarak loglanır
- Log kayıtları kullanıcı, işlem tipi, tablo, kayıt ID, açıklama ve IP adresini içerir
- Admin kullanıcılar tüm kullanıcıların log kayıtlarını görüntüleyebilir

## 🔄 Güncelleme Notları

### Versiyon 2.0.x – OYS (Ocak Yönetim Sistemi)
- ✅ Uygulama adı **OYS – Ocak Yönetim Sistemi** olarak güncellendi (taş ocağı / agrega üretimi odağı)
- ✅ **Kantar Raporları** modülü: Perakende Satış, Özet Rapor, Özet Malzeme Satış, Cari Ekstre sekmeleri
- ✅ Kantar PDF’leri: perakende, özet, özet malzeme, cari ekstre (TCPDF)
- ✅ Özet malzeme satış: döküm tipi (malzeme) bazında kg/tutar, dönemde satışı olmayanları gizle/göster
- ✅ Cari ekstre müşteri seçimi Select2 ile aranabilir
- ✅ Tarih aralığı varsayılanı bugün; periyot butonlarında seçili vurgusu
- ✅ Veritabanı ayarları `.env` ve `config/load_env.php` ile yönetiliyor; raporlama DB ayrı tanımlanabiliyor

### Versiyon 2.0.0 (Ocak 2025)
- ✅ Kullanıcı giriş sistemi ve yetkilendirme eklendi
- ✅ Audit log sistemi eklendi
- ✅ Kanal bazlı ödemeler (Banka/Nakit) eklendi
- ✅ Puantaj yönetimi modülü eklendi
- ✅ Fazla mesai modülü tamamen yenilendi
- ✅ Soft delete özelliği eklendi
- ✅ Tüm işlem sayfaları güvenlik güncellemeleri yapıldı
- ✅ Kullanıcı log görüntüleme özelliği eklendi
- ✅ PDF/Yazdır desteği eklendi (Bordro Ödeme Özeti, Fazla Mesai Ekstresi, Kantar Raporları, vb.)
- ✅ Gelişmiş filtreleme özellikleri eklendi
- ✅ Responsive tasarım iyileştirmeleri

## 📞 Destek ve Geliştirme

### Geliştirme Notları
- Sistem PHP PDO kullanıyor
- Bootstrap 5 ile responsive tasarım
- Bootstrap Icons kullanılıyor (OYS simgesi: `bi-layers` – agrega/katman teması)
- Türkçe karakter desteği mevcut (UTF8MB4)
- Session yönetimi uygulama bazlı dizinde
- Güvenli redirect fonksiyonu (`safeRedirect`)
- Kantar Raporları: TCPDF ile PDF; jQuery + Select2 (Cari müşteri seçimi)

### Teknik Detaylar
- **Para Parsing**: `parseMoney()` fonksiyonu TR/EN format desteği
- **Money Formatting**: `formatMoney()` fonksiyonu Türkçe format
- **Safe Redirect**: `safeRedirect()` fonksiyonu output buffering ile güvenli yönlendirme
- **Audit Logging**: `logUserAction()` fonksiyonu tüm işlemleri loglar
- **Dynamic Schema**: `created_by` ve `updated_by` alanları dinamik kontrol edilir

### İyileştirme Önerileri
1. Excel export/import özelliği eklenebilir
2. E-posta bildirimleri eklenebilir
3. Mobil uyumluluk iyileştirilebilir
4. Gelişmiş raporlama ve grafikler eklenebilir
5. API endpoint'leri eklenebilir
6. Çoklu dil desteği eklenebilir

## 📄 Lisans

OYS – Ocak Yönetim Sistemi, ZERSOFT (zersoft.net) tarafından geliştirilmiştir ve telif haklarına tabidir.

**Copyright (c) 2025 ZERSOFT**

Bu yazılım, ZERSOFT'un özel mülkiyetidir ve yalnızca zersoft.net veya yetkili alt şirketleri tarafından iç iş kullanımı için lisanslanmıştır.

### Lisans Koşulları

- Bu yazılım yalnızca yetkili kullanıcılar tarafından kullanılabilir
- Yazılımın kopyalanması, dağıtılması veya satılması yasaktır
- Kaynak kodu değiştirilemez veya tersine mühendislik yapılamaz
- Telif hakkı bildirimleri kaldırılamaz

Detaylı lisans bilgileri için `LICENSE` dosyasına bakınız.

**Lisans Sahibi**: ZERSOFT (zersoft.net)  
**İletişim**: info@zersoft.net  
**Web**: https://zersoft.net

---

**Son Güncelleme**: Şubat 2025  
**Versiyon**: 2.0.3 (OYS)  
**Geliştirici**: ZERSOFT
