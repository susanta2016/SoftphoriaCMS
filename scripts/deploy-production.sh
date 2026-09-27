#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="/home/softphuk/softphoria-cms"
BACKUP_DIR="/home/softphuk/backups"
BRANCH="develop"

DATE=$(date +%Y%m%d-%H%M%S)
DB_NAME="softphuk_softphoria"
DB_USER="softphuk_softphoria"

echo "========================================"
echo " Softphoria CMS Production Deployment"
echo "========================================"
echo "Date:   ${DATE}"
echo "Branch: ${BRANCH}"
echo

cd "$APP_DIR"

echo "[1/9] Checking Git status..."
git status --short

if [[ -n "$(git status --short)" ]]; then
    echo
    echo "ERROR: Production working tree is not clean."
    echo "Commit/stash/review changes before deploying."
    exit 1
fi

echo "[2/9] Creating database backup..."
mkdir -p "$BACKUP_DIR"

mysqldump \
    -u "$DB_USER" \
    -p \
    "$DB_NAME" \
    > "${BACKUP_DIR}/softphoria-db-${DATE}.sql"

echo "Database backup created:"
echo "${BACKUP_DIR}/softphoria-db-${DATE}.sql"

echo "[3/9] Updating Git repository..."
git fetch origin
git pull --ff-only origin "$BRANCH"

echo "[4/9] Installing Composer dependencies..."
/home/softphuk/bin/composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

echo "[5/9] Running database migrations..."
php artisan migrate --force

echo "[6/9] Verifying storage..."
if [[ ! -L "/home/softphuk/public_html/storage" ]]; then
    echo "Storage symlink missing. Creating..."
    php artisan storage:link
else
    echo "Storage symlink exists."
fi

echo "[7/9] Fixing Laravel writable permissions..."
chmod -R ug+rwX storage bootstrap/cache

find storage/app/public \
    -type d \
    -exec chmod 755 {} \;

find storage/app/public \
    -type f \
    -exec chmod 644 {} \;

echo "[8/9] Clearing and rebuilding Laravel cache..."
php artisan optimize:clear
php artisan optimize

echo "[9/9] Deployment verification..."
php artisan about --only=environment

echo
echo "========================================"
echo " Deployment completed"
echo "========================================"
echo
echo "IMPORTANT:"
echo "- Production media was preserved."
echo "- Production .env was preserved."
echo "- Database backup: ${BACKUP_DIR}/softphoria-db-${DATE}.sql"
echo