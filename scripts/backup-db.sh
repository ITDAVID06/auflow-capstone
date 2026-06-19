#!/usr/bin/env bash
# AUFlow – Nightly MySQL Backup Script
# Dumps the auflow database to /opt/backups/, retains the last 7, logs to /var/log/auflow-backup.log.
#
# Usage:  /opt/auflow/scripts/backup-db.sh
# Cron:   0 3 * * * /opt/auflow/scripts/backup-db.sh
#
# Requirements: mysqldump available on PATH; .env readable by the backup user.

set -euo pipefail

APP_DIR="/opt/auflow"
BACKUP_DIR="/opt/backups"
LOG_FILE="/var/log/auflow-backup.log"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/auflow_${TIMESTAMP}.sql.gz"
RETAIN=7

mkdir -p "${BACKUP_DIR}"

log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "${LOG_FILE}"
}

# Read DB credentials from .env
DB_HOST=$(grep -E '^DB_HOST=' "${APP_DIR}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d ' ')
DB_PORT=$(grep -E '^DB_PORT=' "${APP_DIR}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d ' ')
DB_DATABASE=$(grep -E '^DB_DATABASE=' "${APP_DIR}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d ' ')
DB_USERNAME=$(grep -E '^DB_USERNAME=' "${APP_DIR}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d ' ')
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "${APP_DIR}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'")

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

if [[ -z "${DB_DATABASE}" || -z "${DB_USERNAME}" || -z "${DB_PASSWORD}" ]]; then
  log "ERROR Could not read DB credentials from ${APP_DIR}/.env"
  exit 1
fi

log "INFO  Starting backup → ${BACKUP_FILE}"

if mysqldump \
    -h "${DB_HOST}" \
    -P "${DB_PORT}" \
    -u "${DB_USERNAME}" \
    -p"${DB_PASSWORD}" \
    --single-transaction --routines --triggers \
    "${DB_DATABASE}" \
  | gzip > "${BACKUP_FILE}"; then
  SIZE=$(du -sh "${BACKUP_FILE}" | cut -f1)
  log "INFO  Backup complete – ${BACKUP_FILE} (${SIZE})"
else
  log "ERROR Backup FAILED."
  exit 1
fi

# Verify the dump is not empty (gzip header is ~20 bytes; real dumps are much larger)
if [[ $(stat -c%s "${BACKUP_FILE}") -lt 1024 ]]; then
  log "WARN  Backup file is suspiciously small ($(stat -c%s "${BACKUP_FILE}") bytes). Verify manually."
fi

# Retain only the last $RETAIN backups
BACKUP_COUNT=$(ls -1 "${BACKUP_DIR}"/auflow_*.sql.gz 2>/dev/null | wc -l)
if [[ "${BACKUP_COUNT}" -gt "${RETAIN}" ]]; then
  EXCESS=$(( BACKUP_COUNT - RETAIN ))
  log "INFO  Pruning ${EXCESS} old backup(s) (keeping ${RETAIN})"
  ls -1t "${BACKUP_DIR}"/auflow_*.sql.gz | tail -n "${EXCESS}" | xargs rm -f
fi

log "INFO  Done."
