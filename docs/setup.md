# AUFlow Setup and Deployment Guide

This guide covers manual installation of AUFlow on a development machine or production server.
No containerization required.

## Contents

1. Prerequisites
2. Environment Variables
3. Local Development Setup
4. Production Deployment (Nginx + PHP-FPM + Supervisor)

---

## 1. Prerequisites

### Local Development

| Requirement | Version |
|---|---|
| PHP | 8.4+ |
| PHP extensions | `pdo_mysql`, `mbstring`, `bcmath`, `exif`, `pcntl`, `zip`, `redis` |
| Composer | 2.x |
| Node.js | 20+ |
| MySQL | 8.0+ |
| Redis | 6+ |

### Production (additional)

- Nginx or Apache
- Supervisor (for queue worker and scheduler)
- Certbot / Let's Encrypt (for TLS)

---

## 2. Environment Variables

Use `.env.example` as your starting point.

### 2.1 Application and Security Keys (Required)

| Key | Required? | Purpose | How to generate / obtain |
|---|---|---|---|
| `APP_ENV` | Yes | Environment mode (`local`, `production`) | Set manually. |
| `APP_DEBUG` | Yes | Error detail toggle | `true` for dev, `false` for production. |
| `APP_URL` | Yes | Canonical URL | `http://127.0.0.1:8000` (dev) or `https://your-domain.com` (prod). |
| `APP_KEY` | Yes | Laravel encryption key | `php artisan key:generate` |
| `SNAPSHOT_SIGNING_KEY` | Yes | HMAC key for snapshot verification | `php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"` |
| `SNAPSHOT_ALLOW_LEGACY_HASH_VERIFICATION` | Recommended | Legacy hash compatibility | `true` during migration; prefer `false` once all legacy snapshots are retired. |

### 2.2 Database, Queue, Session, Cache

| Key | Purpose | Typical value |
|---|---|---|
| `DB_HOST` | MySQL server host | `127.0.0.1` (adjust for managed DB) |
| `DB_PORT` | MySQL port | `3306` |
| `DB_DATABASE` | Database name | `auflow` |
| `DB_USERNAME` | Database user | least-privileged user |
| `DB_PASSWORD` | Database password | strong random password |
| `QUEUE_CONNECTION` | Queue backend | `redis` (default in `.env.example`); switch to `database` only for simple dev setups |
| `CACHE_STORE` | Cache backend | `redis` (default in `.env.example`); `database` is an alternative for simple dev setups |
| `SESSION_DRIVER` | Session backend | `redis` (default in `.env.example`); `database` is an alternative for simple dev setups |
| `REDIS_HOST` | Redis host | `127.0.0.1` (adjust for managed Redis) |
| `REDIS_PORT` | Redis port | `6379` |
| `REDIS_PASSWORD` | Redis auth password | set if Redis requires authentication |

### 2.3 Mail

| Key | Purpose | How to obtain |
|---|---|---|
| `MAIL_MAILER` | Mail transport | `resend` or `smtp` |
| `RESEND_API_KEY` | Resend API credential | Resend dashboard |
| `MAIL_FROM_ADDRESS` | Sender address | verified sender/domain |
| `MAIL_FROM_NAME` | Sender name | your app/org name |

### 2.4 Snapshot Storage

| Key | Purpose | Value |
|---|---|---|
| `SNAPSHOT_STORAGE_DISK` | Disk for rendered snapshot HTML | `local` (dev); `s3` (production) |
| `AWS_ACCESS_KEY_ID` | S3 key ID | IAM credentials (required for S3 disk) |
| `AWS_SECRET_ACCESS_KEY` | S3 secret | IAM credentials |
| `AWS_DEFAULT_REGION` | S3 region | e.g. `us-east-1` |
| `AWS_BUCKET` | Bucket name | your S3 bucket |

### 2.5 URL and Inertia/Ziggy Notes

- `APP_URL` must always be the external URL users visit.
- Behind a reverse proxy, ensure forwarded headers are trusted in `bootstrap/app.php`.
- To use a CDN for assets, add `'asset_url' => env('ASSET_URL')` to `config/app.php` and set `ASSET_URL`.

---

## 3. Local Development Setup

### Step-by-step

1. Clone the repository:

```bash
git clone <repo-url> auflow
cd auflow
```

2. Copy the env template:

```bash
cp .env.example .env
```

3. Create a local MySQL database:

```sql
CREATE DATABASE auflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'auflow'@'127.0.0.1' IDENTIFIED BY 'replace_with_strong_password';
GRANT ALL PRIVILEGES ON auflow.* TO 'auflow'@'127.0.0.1';
FLUSH PRIVILEGES;
```

4. Configure `.env`:

```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auflow
DB_USERNAME=auflow
DB_PASSWORD=replace_with_strong_password
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
SNAPSHOT_STORAGE_DISK=local
```

5. Generate key material:

```bash
php artisan key:generate
php -r "echo 'SNAPSHOT_SIGNING_KEY='.bin2hex(random_bytes(32)).PHP_EOL;"
```

Paste the `SNAPSHOT_SIGNING_KEY` value into `.env`.

> **Note:** Submission payloads are stored as plain JSON in `tbl_form_submission.payload_json`.
> Security relies on database-level access controls. No additional encryption key is required.

6. Install dependencies:

```bash
composer install
npm install
```

7. Run migrations and seed:

```bash
php artisan migrate --seed
php artisan storage:link
```

8. Build frontend assets:

```bash
npm run build
```

9. Start local processes (separate terminals or use the convenience script):

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev

# Terminal 3
php artisan queue:work --queue=default,notifications --sleep=3 --tries=3

# Terminal 4
php artisan schedule:work
```

Or all at once:

```bash
npm run dev-all
```

10. Open `http://127.0.0.1:8000`.

---

## 4. Production Deployment (Nginx + PHP-FPM + Supervisor)

The steps below assume Ubuntu 22.04/24.04. Adapt paths and package names for other distributions.

### 4.1 Host Preparation

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-bcmath \
    php8.4-exif php8.4-pcntl php8.4-zip php8.4-redis \
    mysql-server redis-server nginx supervisor certbot python3-certbot-nginx \
    git curl unzip
```

Install Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Install Node.js 20+ (via NodeSource):

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 4.2 Clone and Configure

```bash
sudo mkdir -p /opt/auflow
sudo chown -R $USER:$USER /opt/auflow
git clone <repo-url> /opt/auflow
cd /opt/auflow
cp .env.example .env
```

Edit `.env` with production values (see section 2). At minimum:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.com`
- `DB_HOST=127.0.0.1`, `DB_*` credentials
- `REDIS_HOST=127.0.0.1`, `REDIS_PASSWORD=<strong>`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `SNAPSHOT_SIGNING_KEY=<generated>`
- `SNAPSHOT_STORAGE_DISK=s3` (or `local` if not using S3)
- `RESEND_API_KEY=<from Resend dashboard>`

### 4.3 Database

Create the production database and user:

```sql
CREATE DATABASE auflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'auflow'@'127.0.0.1' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON auflow.* TO 'auflow'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### 4.4 Install Dependencies and Build

```bash
cd /opt/auflow
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force
php artisan storage:link
php artisan optimize
```

Set ownership for PHP-FPM:

```bash
sudo chown -R www-data:www-data /opt/auflow
sudo chmod -R 755 /opt/auflow/storage /opt/auflow/bootstrap/cache
```

### 4.5 Nginx Configuration

Create `/etc/nginx/sites-available/auflow`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /opt/auflow/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site and reload Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/auflow /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Obtain a TLS certificate:

```bash
sudo certbot --nginx -d your-domain.com
```

### 4.6 Supervisor (Queue Worker and Scheduler)

Create `/etc/supervisor/conf.d/auflow-worker.conf`:

```ini
[program:auflow-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /opt/auflow/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/opt/auflow/storage/logs/worker.log
stopwaitsecs=3600
```

Create `/etc/supervisor/conf.d/auflow-scheduler.conf`:

```ini
[program:auflow-scheduler]
command=php /opt/auflow/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/opt/auflow/storage/logs/scheduler.log
```

Load and start:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start auflow-worker:*
sudo supervisorctl start auflow-scheduler
sudo supervisorctl status
```

### 4.7 Verify Deployment

```bash
curl -I https://your-domain.com
tail -n 50 /opt/auflow/storage/logs/laravel.log
supervisorctl status
```

### 4.8 Nightly Backups

Install the backup cron (reads credentials from `.env`):

```bash
chmod +x /opt/auflow/scripts/backup-db.sh
(crontab -l 2>/dev/null; echo "0 3 * * * /opt/auflow/scripts/backup-db.sh") | sort -u | crontab -
```

### 4.9 Routine Updates

Use the deploy script for subsequent releases:

```bash
chmod +x /opt/auflow/scripts/deploy.sh
/opt/auflow/scripts/deploy.sh
```

The script handles: git pull, composer install, npm build, migrations, cache clear, and Supervisor restarts.

### 4.10 Production Guardrails

Never use destructive commands in production:

- `php artisan migrate:fresh`
- `php artisan migrate:refresh`

Protect and back up secrets:

- `APP_KEY`
- `SNAPSHOT_SIGNING_KEY`
