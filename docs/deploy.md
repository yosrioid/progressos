# VPS Deployment Guide

Server ini dirancang untuk menjalankan **lebih dari satu project** sekaligus, apapun stacknya.
Strukturnya dibagi dua: **setup server (sekali)** dan **setup per-project (diulang tiap project baru)**.

Domain utama: `oirsoy.my.id` — tiap project pakai subdomain sendiri.

---

## Bagian A — Setup Server (Sekali)

### A1. Akses & user

```bash
ssh root@IP_SERVER

# Buat user deploy untuk semua project
adduser deploy
usermod -aG sudo deploy

# Copy SSH key dari mesin lokal
# Jalankan dari mesin lokal:
ssh-copy-id deploy@IP_SERVER

# Semua langkah berikutnya sebagai user deploy
ssh deploy@IP_SERVER
```

### A2. Firewall

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
sudo ufw status
```

### A3. Package dasar

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git unzip software-properties-common acl
```

### A4. Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
```

### A5. PHP 8.3

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y \
  php8.3-fpm php8.3-cli \
  php8.3-mysql php8.3-redis php8.3-xml \
  php8.3-curl php8.3-mbstring php8.3-zip \
  php8.3-gd php8.3-bcmath php8.3-intl
```

Matikan pool `www` default (tidak dipakai, diganti per-project):

```bash
sudo sed -i 's/^/;/' /etc/php/8.3/fpm/pool.d/www.conf
sudo systemctl restart php8.3-fpm
```

### A6. MySQL 8.0

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Tidak ada user atau database global di sini — dibuat per-project di Bagian B.

Tuning untuk 2 GB RAM:

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Tambahkan di bawah `[mysqld]`:

```ini
innodb_buffer_pool_size = 128M
innodb_log_file_size    = 32M
max_connections         = 100
query_cache_type        = 0
```

```bash
sudo systemctl restart mysql
```

### A7. Redis (shared, satu instance)

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
```

Tiap project pakai Redis DB index berbeda (`Redis::connection()->select(N)`) — dikonfigurasi via `REDIS_DB` di `.env` masing-masing project.

### A8. Composer

```bash
curl -sLS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/bin --filename=composer
```

### A9. Node.js 22

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Node dipakai untuk build aset frontend. Setelah build, prosesnya tidak jalan terus-menerus.

### A10. Supervisor (queue worker manager)

```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
```

### A11. Certbot (SSL)

```bash
sudo apt install -y certbot python3-certbot-nginx
```

### A12. Struktur direktori server

```
/var/www/
├── progressos/       ← project 1
├── project-dua/      ← project 2 nanti
└── project-tiga/     ← project 3 nanti

/etc/nginx/sites-available/
├── progressos
├── project-dua
└── project-tiga

/etc/php/8.3/fpm/pool.d/
├── progressos.conf
├── project-dua.conf
└── project-tiga.conf

/etc/supervisor/conf.d/
├── progressos-worker.conf
├── project-dua-worker.conf
└── project-tiga-worker.conf
```

---

## Bagian B — Setup Per-Project

Ganti `progressos` dan `progressos.oirsoy.my.id` dengan nama project dan subdomain yang sesuai.

### B1. Direktori project

```bash
PROJECT=progressos
DOMAIN=progressos.oirsoy.my.id

sudo mkdir -p /var/www/$PROJECT
sudo chown deploy:www-data /var/www/$PROJECT
sudo chmod 750 /var/www/$PROJECT
```

### B2. Clone dan install

```bash
cd /var/www/$PROJECT
git clone https://github.com/yosrioid/progressos.git .
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### B3. Database MySQL

```bash
# Ganti PASSWORD_KUAT dengan password unik per-project
sudo mysql -u root <<SQL
CREATE DATABASE progressos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'progressos'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON progressos.* TO 'progressos'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### B4. File .env

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

```env
APP_NAME=ProgressOS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://progressos.oirsoy.my.id

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=progressos
DB_USERNAME=progressos
DB_PASSWORD=PASSWORD_KUAT

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0          # ← ganti angka ini per-project (0, 1, 2, dst.)
REDIS_CACHE_DB=1    # ← REDIS_DB + 1

MAIL_MAILER=smtp
MAIL_HOST=smtp.PROVIDER.com
MAIL_PORT=587
MAIL_USERNAME=EMAIL
MAIL_PASSWORD=PASSWORD_EMAIL
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=EMAIL
MAIL_FROM_NAME="ProgressOS"

FILESYSTEM_DISK=local
```

```bash
chmod 600 .env
```

### B5. Migrate dan cache

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permission storage:

```bash
sudo chown -R deploy:www-data /var/www/$PROJECT/storage /var/www/$PROJECT/bootstrap/cache
sudo chmod -R 775 /var/www/$PROJECT/storage /var/www/$PROJECT/bootstrap/cache
```

### B6. PHP-FPM pool per-project

```bash
sudo nano /etc/php/8.3/fpm/pool.d/progressos.conf
```

```ini
[progressos]
user = deploy
group = www-data
listen = /run/php/php8.3-fpm-progressos.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = static
pm.max_children = 3
pm.max_requests = 500

; Isolasi environment per-project
env[APP_ENV] = production
```

```bash
sudo systemctl reload php8.3-fpm
```

### B7. Nginx virtual host

```bash
sudo nano /etc/nginx/sites-available/progressos
```

```nginx
server {
    listen 80;
    server_name progressos.oirsoy.my.id;
    root /var/www/progressos/public;
    index index.php;

    client_max_body_size 10M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm-progressos.sock;
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

```bash
sudo ln -s /etc/nginx/sites-available/progressos /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### B8. SSL

```bash
sudo certbot --nginx -d progressos.oirsoy.my.id
```

Certbot otomatis update Nginx ke HTTPS dan setup auto-renew.

Untuk project berikutnya nanti cukup:

```bash
sudo certbot --nginx -d namabaru.oirsoy.my.id
```

### B9. Queue worker (Supervisor)

```bash
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

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start progressos-worker:*
sudo supervisorctl status
```

### B10. Scheduler (cron)

Satu cron entry sudah cukup untuk semua project Laravel di server — Laravel hanya menjalankan job yang due:

```bash
crontab -e
```

```cron
* * * * * cd /var/www/progressos && php artisan schedule:run >> /dev/null 2>&1
```

Untuk project Laravel lain nanti, tambahkan baris baru:

```cron
* * * * * cd /var/www/project-dua && php artisan schedule:run >> /dev/null 2>&1
```

---

## Bagian C — Verifikasi

```bash
# App merespons
curl -I https://progressos.oirsoy.my.id

# PHP-FPM pool berjalan
sudo systemctl status php8.3-fpm

# Queue worker berjalan
sudo supervisorctl status

# Redis bisa diakses
redis-cli ping

# Storage writable
ls -la /var/www/progressos/storage/logs/

# SSL valid
curl -I https://progressos.oirsoy.my.id | grep -i strict
```

---

## Bagian D — Deploy Update

Jalankan setiap kali ada update kode:

```bash
cd /var/www/progressos
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
APP_VER=$(git describe --tags --always 2>/dev/null || echo "dev")
sed -i "s/^APP_VERSION=.*/APP_VERSION=${APP_VER}/" .env || echo "APP_VERSION=${APP_VER}" >> .env
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart progressos-worker:*
```

---

## Bagian E — Menambah Project Baru

Cukup ulangi Bagian B dengan nama dan subdomain berbeda:

| Yang berubah | Contoh project baru |
|---|---|
| Direktori | `/var/www/project-baru` |
| Database | `project_baru` / user `project_baru` |
| `.env` `REDIS_DB` | angka belum dipakai (cek yang sudah: `redis-cli client list`) |
| PHP-FPM pool | `/etc/php/8.3/fpm/pool.d/project-baru.conf` (socket beda) |
| Nginx site | `/etc/nginx/sites-available/project-baru` |
| Supervisor | `/etc/supervisor/conf.d/project-baru-worker.conf` |
| SSL | `certbot --nginx -d project-baru.oirsoy.my.id` |

Untuk project **non-Laravel** (Node.js, Go, dll): Nginx cukup reverse proxy ke port lokal — tidak perlu PHP-FPM pool atau Supervisor Laravel worker.

```nginx
# Contoh untuk Node.js app di port 3000
location / {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

---

## Ringkasan resource per-project (estimasi, 2 GB total)

| Resource | ProgressOS | Sisa untuk project lain |
|---|---|---|
| PHP-FPM workers | 3 × ~80 MB = 240 MB | — |
| MySQL | 128 MB buffer + overhead | shared |
| Redis | ~50 MB | shared |
| Queue worker | ~80 MB | ~80 MB/project |
| Nginx | ~30 MB | shared |
| OS | ~250 MB | — |
| **Total terpakai** | **~780 MB** | **~1.2 GB tersisa** |

Dengan sisa 1.2 GB, masih bisa tambah 1–2 project ringan (static site, Node.js app kecil, atau Laravel kedua dengan 2 PHP-FPM worker).
