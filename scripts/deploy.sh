#!/usr/bin/env bash
# AUFlow – Safe Production Deployment Script
#
# Prerequisites on the server:
#   - PHP 8.4, Composer, Node.js 20+, MySQL 8, Redis, Nginx + PHP-FPM
#   - Supervisor managing the queue worker and scheduler
#
# Usage:  /opt/auflow/scripts/deploy.sh
#
# What this script does:
#   1. Pulls latest code from origin/main
#   2. Installs/updates PHP and Node dependencies
#   3. Builds frontend production assets
#   4. Runs safe migrations
#   5. Clears and warms caches
#   6. Restarts queue worker and scheduler via Supervisor
#   7. Runs a basic HTTP health check

set -euo pipefail

APP_DIR="/opt/auflow"
APP_URL="https://auflow.online"
LOG_FILE="/var/log/auflow-deploy.log"
PHP_USER="www-data"

log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"
}

cd "${APP_DIR}"
log "INFO  ===== Deployment started ====="

# 1. Pull latest code
log "INFO  Pulling latest code from origin/main..."
git pull origin main

# Re-apply web server user ownership after pull
chown -R "${PHP_USER}:${PHP_USER}" "${APP_DIR}"

# 2. Install PHP dependencies (production, no dev)
log "INFO  Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Build frontend assets
log "INFO  Installing Node dependencies and building assets..."
npm ci
npm run build

# 4. Run safe migrations
log "INFO  Running migrations..."
php artisan migrate --force

# 5. Clear and warm caches
log "INFO  Clearing and warming caches..."
php artisan optimize:clear
php artisan optimize
php artisan storage:link --force

# 6. Restart queue worker and scheduler via Supervisor
log "INFO  Restarting Supervisor workers..."
if command -v supervisorctl &>/dev/null; then
  supervisorctl reread
  supervisorctl update
  supervisorctl restart auflow-worker:*
  supervisorctl restart auflow-scheduler
else
  log "WARN  supervisorctl not found – restart workers manually."
fi

# 7. Health check
log "INFO  Waiting for the application to become healthy..."
for i in $(seq 1 60); do
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -k "${APP_URL}" 2>/dev/null || echo "000")
  if [[ "${HTTP_CODE}" == "200" ]]; then
    log "INFO  Health check PASSED (HTTP ${HTTP_CODE})."
    break
  fi
  if [[ $i -eq 60 ]]; then
    log "WARN  App did not respond with 200 after 60 seconds (last code: ${HTTP_CODE})."
    log "WARN  Inspect logs: tail -n 200 ${APP_DIR}/storage/logs/laravel.log"
  fi
  sleep 1
done

log "INFO  ===== Deployment complete ====="
echo ""
echo "✓ Deployment finished. HTTP status: ${HTTP_CODE}"
echo "  Inspect logs:  tail -n 200 ${APP_DIR}/storage/logs/laravel.log"
echo "  Worker status: supervisorctl status"
