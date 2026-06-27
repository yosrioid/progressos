# ProgressOS — Deployment Guide (Ubuntu 22.04, 1 vCPU / 2 GB)

Stack: **Nginx · PHP 8.3-FPM · MySQL 8.0 · Redis · Supervisor · Certbot**

---

## 1. Akses server pertama kali

```bash
ssh root@IP_SERVER
```

Buat user non-root:

```bash
adduser deploy
usermod -aG sudo deploy
```

Copy SSH key ke user baru (dari mesin lokal):

```bash
ssh-copy-id deploy@IP_SERVER
```

Aktifkan firewall:

```bash
ufw allow OpenSSH
ufw allow 80
ufw allow 443
ufw enable
```

Mulai semua langkah berikut sebagai user `deploy`:

```bash
ssh deploy@IP_SERVER
```

---

## 2. Install dependensi sistem

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git unzip software-properties-common
```

### PHP 8.3

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
  php8.3-fpm php8.3-cli \
  php8.3-mysql php8.3-redis php8.3-xml \
  php8.3-curl php8.3-mbstring php8.3-zip \
  php8.3-gd php8.3-bcmath php8.3-intl
```

### Nginx

```bash
sudo apt install -y nginx
```

### MySQL 8.0

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Buat database dan user:

```bash
sudo mysql -u root
```

```sql
CREATE DATABASE progressos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'progressos'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON progressos.* TO 'progressos'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
```

### Composer

```bash
curl -sLS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/bin --filename=composer
```

### Node.js 22 (untuk build aset — bisa dihapus setelah build)

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## 3. Tuning PHP-FPM untuk 2 GB RAM

Edit pool config:

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

Ubah bagian berikut:

```ini
pm = static
pm.max_children = 3
pm.max_requests = 500
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

---

## 4. Tuning MySQL untuk 2 GB RAM

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Tambahkan di bawah `[mysqld]`:

```ini
innodb_buffer_pool_size     = 128M
innodb_log_file_size        = 32M
max_connections             = 50
query_cache_size            = 0
query_cache_type            = 0
```

Restart MySQL:

```bash
sudo systemctl restart mysql
```

---

## 5. Deploy aplikasi

```bash
sudo mkdir -p /var/www/progressos
sudo chown deploy:deploy /var/www/progressos
cd /var/www/progressos
git clone https://github.com/yosrioid/progressos.git .
```

Install dependensi PHP (no dev, optimized):

```bash
composer install --no-dev --optimize-autoloader
```

Build frontend:

```bash
npm ci
npm run build
node_modules/.bin/node --version  # verifikasi build berhasil
```

Node.js tidak diperlukan lagi setelah build — boleh dihapus jika ingin menghemat RAM.

---

## 6. Konfigurasi .env

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Isi nilai-nilai berikut:

```env
APP_NAME=ProgressOS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN_KAMU

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=progressos
DB_USERNAME=progressos
DB_PASSWORD=GANTI_PASSWORD_KUAT

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.PROVIDER.com
MAIL_PORT=587
MAIL_USERNAME=EMAIL_KAMU
MAIL_PASSWORD=PASSWORD_EMAIL
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=EMAIL_KAMU
MAIL_FROM_NAME="ProgressOS"

FILESYSTEM_DISK=local
```

Permission file:

```bash
chmod 600 .env
```

---

## 7. Migrasi dan storage

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permission direktori storage:

```bash
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 8. Konfigurasi Nginx

```bash
sudo nano /etc/nginx/sites-available/progressos
```

```nginx
server {
    listen 80;
    server_name DOMAIN_KAMU;
    root /var/www/progressos/public;
    index index.php;

    client_max_body_size 10M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Aktifkan dan test:

```bash
sudo ln -s /etc/nginx/sites-available/progressos /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 9. SSL dengan Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d DOMAIN_KAMU
```

Certbot akan otomatis update config Nginx ke HTTPS dan setup auto-renew.

---

## 10. Queue worker dengan Supervisor

```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/progressos-worker.conf
```

```ini
[program:progressos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/progressos/artisan queue:work redis --sleep=3 --tries=3 --timeout=90 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/progressos/storage/logs/worker.log
stopwaitsecs=3600
```

Aktifkan:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start progressos-worker:*
sudo supervisorctl status
```

---

## 11. Scheduler (cron)

```bash
crontab -e
```

Tambahkan:

```cron
* * * * * cd /var/www/progressos && php artisan schedule:run >> /dev/null 2>&1
```

---

## 12. Verifikasi akhir

```bash
# App merespons
curl -I https://DOMAIN_KAMU

# PHP-FPM jalan
sudo systemctl status php8.3-fpm

# MySQL jalan
sudo systemctl status mysql

# Redis jalan
redis-cli ping

# Queue worker jalan
sudo supervisorctl status

# Cron terdaftar
crontab -l

# Storage writable
ls -la /var/www/progressos/storage/logs/
```

---

## 13. Deploy update di masa depan

```bash
cd /var/www/progressos
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart progressos-worker:*
```

---

## Catatan

- Ganti semua `DOMAIN_KAMU` dengan domain asli sebelum menjalankan.
- Ganti semua password placeholder dengan nilai kuat yang unik.
- File `.env` tidak masuk git — isi ulang setiap deploy ke server baru.
- Swagger UI (`/api-docs`) hanya aktif di `APP_ENV=local`. Di production otomatis tidak terbuka.
- Upload avatar disimpan di `storage/app/public` — backup direktori ini secara berkala.
