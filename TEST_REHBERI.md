# 🧪 Test Rehberi - Personel Takip Sistemi

## 1. Veritabanı Kurulumu

### Adım 1: Veritabanını Oluşturma

Veritabanını oluşturmak için `database.sql` dosyasını MySQL'e import edin:

```bash
# Terminal'den (eğer MySQL komut satırı erişiminiz varsa)
mysql -u zersoftn_personel_takip -p'm@txIop=aV5c' < database.sql

# VEYA phpMyAdmin üzerinden:
# 1. phpMyAdmin'e giriş yapın
# 2. "SQL" sekmesine tıklayın
# 3. database.sql dosyasının içeriğini kopyalayıp yapıştırın
# 4. "Git" butonuna tıklayın
```

### Adım 2: Veritabanı Bağlantı Bilgilerini Kontrol

`config/db.php` dosyasında bağlantı bilgileri doğru mu kontrol edin:
- Host: `localhost` (veya sunucu IP'si)
- Veritabanı: `zersoftn_personel_takip`
- Kullanıcı: `zersoftn_personel_takip`
- Şifre: `m@txIop=aV5c`

## 2. PHP Sunucusunu Başlatma

### Yerel Test (Built-in PHP Server)

Proje klasörüne gidin ve PHP'nin yerleşik sunucusunu başlatın:

```bash
cd /Users/ramazantuncer/mydocker/web/hympers
php -S localhost:8000
```

Tarayıcıda şu adrese gidin: `http://localhost:8000`

### Docker/XAMPP/MAMP Kullanıyorsanız

- Proje klasörünü web sunucunuzun `htdocs` veya `www` klasörüne kopyalayın
- `http://localhost/hympers` adresine gidin

## 3. İlk Test Adımları

### Adım 1: Veritabanı Bağlantı Testi

Tarayıcıda şu adrese gidin:
```
http://localhost:8000/test.php
```

Bu sayfa:
- ✅ Veritabanı bağlantısını kontrol eder
- ✅ Tüm tabloların varlığını kontrol eder
- ✅ Tablo kayıt sayılarını gösterir

### Adım 2: Ana Sayfa Testi

```
http://localhost:8000/index.php
```

Dashboard sayfası açılmalı ve istatistikler görünmeli (ilk kullanımda 0 olabilir).

### Adım 3: Personel Ekleme Testi

1. Ana sayfadan veya menüden **"Personel Listesi"** sayfasına gidin
2. **"Yeni Personel Ekle"** butonuna tıklayın
3. Formu doldurun:
   - Ad Soyad: Test Personel
   - TC No: 12345678901
   - Pozisyon: Yazılım Geliştirici
   - Maaş: 50000
   - İşe Giriş Tarihi: Bugünün tarihi
   - Banka Adı: Test Bankası
   - IBAN: TR123456789012345678901234
   - Mesai Saat Ücreti: 100
4. **"Kaydet"** butonuna tıklayın
5. Personel listesinde görünmeli

### Adım 4: Bordro Oluşturma Testi

1. **"Bordro"** sayfasına gidin
2. **"Yeni Bordro Oluştur"** butonuna tıklayın
3. Formu doldurun:
   - Personel: Az önce eklediğiniz personeli seçin
   - Ay: Mevcut ay
   - Yıl: Mevcut yıl
   - Brüt Maaş: 50000
   - SGK/Banka: 5000
   - Ek Ödenek: 2000
   - Toplam Ödeme otomatik hesaplanacak: 47000
4. **"Kaydet"** butonuna tıklayın
5. Bordro listesinde görünmeli

### Adım 5: Fazla Mesai Ekleme Testi

1. **"Fazla Mesai"** sayfasına gidin
2. **"Fazla Mesai Ekle"** butonuna tıklayın
3. Personel seçtiğinizde saat ücreti otomatik doldurulmalı
4. Formu doldurun:
   - Personel: Test Personel
   - Tarih: Bugünün tarihi
   - Süre: 4 saat
   - Saat Ücreti: 100 (otomatik doldurulmuş olmalı)
   - Tutar otomatik hesaplanacak: 400
   - Ödendi: İşaretleyin
5. **"Kaydet"** butonuna tıklayın

### Adım 6: Avans Takip Testi

1. **"Avans Takip"** sayfasına gidin
2. **"Avans Ekle"** butonuna tıklayın
3. Formu doldurun:
   - Personel: Test Personel
   - Tarih: Bugünün tarihi
   - Avans Tutarı: 5000
   - Açıklama: Test avans
4. **"Kaydet"** butonuna tıklayın

### Adım 7: Tazminat Takip Testi

1. **"Tazminat Takip"** sayfasına gidin
2. **"Tazminat Ekle"** butonuna tıklayın
3. Formu doldurun:
   - Personel: Test Personel
   - Tarih: Bugünün tarihi
   - Plan: Kıdem Tazminatı
   - İşlem: Ödeme
   - Tutar: 10000
4. **"Kaydet"** butonuna tıklayın

### Adım 8: Raporlar Testi

1. **"Raporlar"** sayfasına gidin
2. Mevcut ay ve yıl için raporlar görünmeli
3. Farklı ay/yıl seçerek filtreleme yapın
4. Personel bazlı bordro dağılımını kontrol edin

## 4. Sorun Giderme

### Veritabanı Bağlantı Hatası

**Hata:** `Veritabanı bağlantı hatası`

**Çözüm:**
1. MySQL sunucusunun çalıştığından emin olun
2. `config/db.php` dosyasındaki bilgileri kontrol edin
3. Kullanıcı adı ve şifrenin doğru olduğundan emin olun
4. Veritabanının oluşturulduğundan emin olun

### Tablo Bulunamadı Hatası

**Hata:** `Table 'zersoftn_personel_takip.personel_listesi' doesn't exist`

**Çözüm:**
1. `database.sql` dosyasını tekrar import edin
2. phpMyAdmin'de tabloların oluşturulduğunu kontrol edin

### Sayfa Bulunamadı Hatası

**Hata:** `404 Not Found` veya boş sayfa

**Çözüm:**
1. PHP sunucusunun çalıştığından emin olun
2. Doğru port numarasını kullandığınızdan emin olun
3. Dosya yollarının doğru olduğundan emin olun

### JavaScript Çalışmıyor

**Sorun:** Fazla mesai sayfasında saat ücreti otomatik doldurulmuyor

**Çözüm:**
1. Tarayıcı konsolunu açın (F12)
2. JavaScript hatalarını kontrol edin
3. `assets/js/main.js` dosyasının yüklendiğinden emin olun

## 5. Beklenen Sonuçlar

✅ Tüm sayfalar hatasız açılmalı
✅ Veritabanı bağlantısı başarılı olmalı
✅ Tüm tablolar mevcut olmalı
✅ Personel ekleme/düzenleme çalışmalı
✅ Bordro oluşturma çalışmalı
✅ Fazla mesai, avans, tazminat ekleme çalışmalı
✅ Raporlar sayfası doğru verileri göstermeli
✅ Generated columns (toplam_odeme, tutar) otomatik hesaplanmalı

## 6. Test Verileri Örnekleri

### Örnek Personel Kayıtları

```
Personel 1:
- Ad Soyad: Ahmet Yılmaz
- TC No: 12345678901
- Pozisyon: Yazılım Geliştirici
- Maaş: 50000
- Mesai Saat Ücreti: 100

Personel 2:
- Ad Soyad: Ayşe Demir
- TC No: 98765432109
- Pozisyon: Proje Yöneticisi
- Maaş: 70000
- Mesai Saat Ücreti: 150
```

### Örnek Bordro

```
- Personel: Ahmet Yılmaz
- Ay: 12 (Aralık)
- Yıl: 2024
- Brüt Maaş: 50000
- SGK/Banka: 5000
- Ek Ödenek: 2000
- Toplam Ödeme: 47000 (otomatik)
```

## 7. Son Kontroller

Test tamamlandıktan sonra:

- [ ] Tüm sayfalar açılıyor mu?
- [ ] Veritabanı bağlantısı çalışıyor mu?
- [ ] CRUD işlemleri (Create, Read, Update, Delete) çalışıyor mu?
- [ ] Generated columns doğru hesaplanıyor mu?
- [ ] Filtreleme ve raporlama çalışıyor mu?
- [ ] Responsive tasarım mobilde çalışıyor mu?

## Yardım

Sorun yaşarsanız:
1. `test.php` sayfasını çalıştırın
2. Tarayıcı konsolunu kontrol edin (F12)
3. PHP hata loglarını kontrol edin
4. Veritabanı bağlantı bilgilerini doğrulayın

