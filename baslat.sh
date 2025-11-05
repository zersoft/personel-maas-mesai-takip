#!/bin/bash

# Personel Takip Sistemi - Başlatma Scripti

echo "🚀 Personel Takip Sistemi Başlatılıyor..."
echo ""

# Ana dizine git
cd /Users/ramazantuncer/mydocker

# Docker container'larını kontrol et
echo "📦 Docker container'ları kontrol ediliyor..."
if ! docker-compose ps | grep -q "Up"; then
    echo "⚠️  Docker container'ları çalışmıyor. Başlatılıyor..."
    make docker-start
    echo "⏳ Container'ların başlaması için 10 saniye bekleniyor..."
    sleep 10
else
    echo "✅ Docker container'ları zaten çalışıyor"
fi

# Container durumunu göster
echo ""
echo "📊 Container Durumu:"
docker-compose ps

# Veritabanı kontrolü
echo ""
echo "🗄️  Veritabanı kontrol ediliyor..."
DB_EXISTS=$(docker exec mydocker-mysqldb-1 mysql -u zersoftn_personel_takip -p'm@txIop=aV5c' -e "SHOW DATABASES LIKE 'zersoftn_personel_takip';" 2>/dev/null | grep -c "zersoftn_personel_takip")

if [ "$DB_EXISTS" -eq 0 ]; then
    echo "⚠️  Veritabanı bulunamadı. Oluşturuluyor..."
    
    # SQL dosyasını import et
    if [ -f "web/hympers/database.sql" ]; then
        echo "📥 SQL dosyası import ediliyor..."
        docker exec -i mydocker-mysqldb-1 mysql -u root -p1234 < web/hympers/database.sql 2>/dev/null
        
        if [ $? -eq 0 ]; then
            echo "✅ Veritabanı başarıyla oluşturuldu"
        else
            echo "❌ Veritabanı oluşturma hatası!"
            echo "💡 Manuel olarak phpMyAdmin'den (http://localhost:8181) import edebilirsiniz"
        fi
    else
        echo "❌ database.sql dosyası bulunamadı!"
    fi
else
    echo "✅ Veritabanı zaten mevcut"
fi

# Proje URL'lerini göster
echo ""
echo "🌐 Proje URL'leri:"
echo "   Test Sayfası: http://localhost:8080/hympers/test.php"
echo "   Ana Sayfa:    http://localhost:8080/hympers/"
echo "   phpMyAdmin:   http://localhost:8181"
echo ""
echo "✅ Hazır! Tarayıcıda test sayfasını açabilirsiniz."

