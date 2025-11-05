# 🚀 Projeyi Başlatma Rehberi

## Hızlı Başlatma (Docker ile)

### Adım 1: Docker Container'larını Başlat

Ana dizinde (mydocker klasöründe) çalıştırın:

```bash
cd /Users/ramazantuncer/mydocker
make docker-start
```

VEYA

```bash
docker-compose up -d
```

Bu komut şunları başlatır:
- ✅ Nginx web sunucusu (Port 80)
- ✅ PHP-FPM (Port 9000)
- ✅ MySQL veritabanı (Port 8989)
- ✅ phpMyAdmin (Port 8181)

### Adım 2: Container'ların Çalıştığını Kontrol Et

```bash
docker-compose ps
```

Tüm servislerin "Up" durumunda olduğunu kontrol edin.

### Adım 3: Veritabanını Oluştur

#### Seçenek A: phpMyAdmin ile (Önerilen)

1. Tarayıcıda şu adrese gidin: `http://localhost:8181`
2. Giriş bilgileri:
   - Sunucu: `mysqldb` veya `mysql`
   - Kullanıcı adı: `.env` dosyasındaki `MYSQL_USER` değeri
   - Şifre: `.env` dosyasındaki `MYSQL_PASSWORD` değeri
3. "SQL" sekmesine tıklayın
4. `database.sql` dosyasının içeriğini kopyalayıp yapıştırın
5. "Git" butonuna tıklayın

#### Seçenek B: Docker exec ile

```bash
# SQL dosyasını container'a kopyala
docker cp web/hympers/database.sql mydocker-mysqldb-1:/tmp/database.sql

# MySQL container'ına bağlan
docker exec -it mydocker-mysqldb-1 mysql -u zersoftn_personel_takip -p'm@txIop=aV5c'

# MySQL içinde:
source /tmp/database.sql;
exit;
```

VEYA tek satırda:

```bash
docker exec -i mydocker-mysqldb-1 mysql -u zersoftn_personel_takip -p'm@txIop=aV5c' < web/hympers/database.sql
```

### Adım 4: Veritabanı Bağlantı Bilgilerini Kontrol Et

`config/db.php` dosyasında:

```php
$host = 'mysqldb';  // Docker içinde container adı
// VEYA
$host = 'localhost'; // Host'tan erişim için (port 8989)
```

**Önemli:** Docker container'ları arasında iletişim için `mysqldb` kullanın. Host'tan erişim için `localhost:8989` kullanın.

### Adım 5: Nginx Konfigürasyonu (Gerekirse)

Eğer proje `/var/www/html` dışındaysa, Nginx konfigürasyonu ekleyin:

`etc/nginx/hympers.conf` dosyası oluşturun:

```nginx
server {
    listen 8080;
    server_name localhost;
    
    root /var/www/hympers;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Adım 6: Projeyi Test Et

Tarayıcıda şu adreslere gidin:

- **Test Sayfası:** `http://localhost:8080/hympers/test.php`
- **Ana Sayfa:** `http://localhost:8080/hympers/index.php`

## Alternatif: PHP Built-in Server (Docker olmadan)

Eğer Docker kullanmak istemiyorsanız:

```bash
cd /Users/ramazantuncer/mydocker/web/hympers
php -S localhost:8000
```

Sonra tarayıcıda: `http://localhost:8000`

**Not:** Bu durumda MySQL'in ayrıca çalışıyor olması gerekir.

## Sorun Giderme

### Container'lar başlamıyor

```bash
# Logları kontrol et
docker-compose logs

# Container'ları durdur ve yeniden başlat
docker-compose down
docker-compose up -d
```

### Veritabanı bağlantı hatası

1. MySQL container'ının çalıştığını kontrol edin:
   ```bash
   docker ps | grep mysql
   ```

2. Veritabanı bilgilerini kontrol edin:
   ```bash
   docker exec -it mydocker-mysqldb-1 mysql -u root -p
   ```

3. `config/db.php` dosyasındaki host'u kontrol edin:
   - Docker içinden: `mysqldb`
   - Host'tan: `localhost` (port 8989)

### Sayfa bulunamadı (404)

1. Nginx konfigürasyonunu kontrol edin
2. Dosya yollarının doğru olduğundan emin olun
3. Port numarasını kontrol edin (80 veya 8080)

### PHP hatası

```bash
# PHP loglarını kontrol et
docker-compose logs php

# PHP container'ına bağlan
docker exec -it mydocker-php-1 sh
```

## Kullanışlı Komutlar

```bash
# Tüm container'ları başlat
make docker-start

# Tüm container'ları durdur
make docker-stop

# Logları görüntüle
make logs

# MySQL yedek al
make mysql-dump

# MySQL yedek geri yükle
make mysql-restore
```

## Veritabanı Bilgileri

- **Host:** `mysqldb` (Docker içinden) veya `localhost:8989` (Host'tan)
- **Veritabanı:** `zersoftn_personel_takip`
- **Kullanıcı:** `zersoftn_personel_takip`
- **Şifre:** `m@txIop=aV5c`

## Proje URL'leri

- **Ana Sayfa:** `http://localhost:8080/hympers/`
- **Test Sayfası:** `http://localhost:8080/hympers/test.php`
- **phpMyAdmin:** `http://localhost:8181`

## İlk Kullanım

1. ✅ Docker container'larını başlatın
2. ✅ Veritabanını oluşturun
3. ✅ Test sayfasını çalıştırın: `http://localhost:8080/hympers/test.php`
4. ✅ Ana sayfaya gidin ve personel ekleyin

