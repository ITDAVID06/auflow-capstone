# Getting Started

This guide sets up AUFlow for local development.

## Prerequisites

- PHP 8.4 with extensions: `pdo_mysql`, `mbstring`, `bcmath`, `exif`, `pcntl`, `zip`, `redis`
- Composer
- Node.js 20+
- MySQL 8.0+
- Redis 6+

## Quick Start

```bash
git clone <repo-url> auflow
cd auflow
cp .env.example .env
```

Create a local MySQL database:

```sql
CREATE DATABASE auflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'auflow'@'127.0.0.1' IDENTIFIED BY 'replace_with_strong_password';
GRANT ALL PRIVILEGES ON auflow.* TO 'auflow'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Configure `.env` (at minimum):

- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=auflow`
- `DB_USERNAME=auflow`
- `DB_PASSWORD=<your-password>`
- `REDIS_HOST=127.0.0.1`
- `REDIS_PORT=6379`
- `APP_URL=http://127.0.0.1:8000`

Generate key material and install dependencies:

```bash
php artisan key:generate
php -r "echo 'SNAPSHOT_SIGNING_KEY='.bin2hex(random_bytes(32)).PHP_EOL;"
composer install
npm install
php artisan migrate --seed
php artisan storage:link
npm run build
```

Paste the `SNAPSHOT_SIGNING_KEY` value into `.env`.

## Seed Baseline Accounts

The seeder above (`--seed`) creates the default admin account:

- email: `admin@auf.edu.ph`
- password: `password`

## Seed Demo Data (Optional)

```bash
php artisan seed:demo --profile=medium
```

Useful options:

```
--profile=quick|medium|full
--with-edge
--deterministic-only
--count-submissions=<n>
--fresh
```

Key demo credentials:

- `admin@auf.edu.ph / password`
- `staff1@auf.test / password`
- `student1@auf.test / password`

## Running the Application

Start all processes in separate terminals:

```bash
# Terminal 1 – PHP dev server
php artisan serve

# Terminal 2 – Vite frontend
npm run dev

# Terminal 3 – Queue worker
php artisan queue:work --queue=default,notifications --sleep=3 --tries=3

# Terminal 4 – Scheduler
php artisan schedule:work
```

Or start everything at once using the convenience script:

```bash
npm run dev-all
```

Open `http://127.0.0.1:8000` in your browser.

## Running Tests

```bash
php artisan test --compact
```

Run a single file:

```bash
php artisan test --compact tests/Feature/WorkflowPublishVersionSnapshotTest.php
```
