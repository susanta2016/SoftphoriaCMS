#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="/home/softphuk/softphoria-cms"
PUBLIC_DIR="/home/softphuk/public_html"
BACKUP_DIR="/home/softphuk/backups"

BRANCH="develop"
DB_NAME="softphuk_softphoria"
DB_USER="softphuk_softphoria"

DATE=$(date +%Y%m%d-%H%M%S)

echo
echo "=============================================="
echo " Softphoria CMS Production Deployment"
echo "=============================================="
echo "Date:   ${DATE}"
echo "Branch: ${BRANCH}"
echo

cd "$APP_DIR"

# ------------------------------------------------
# 1. Verify Git state
# ------------------------------------------------

echo "[1/10] Checking Git state..."

CURRENT_BRANCH=$(git branch --show-current)

if [[ "$CURRENT_BRANCH" != "$BRANCH" ]]; then
    echo
    echo "ERROR: Expected branch '${BRANCH}', found '${CURRENT_BRANCH}'."
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo
    echo "ERROR: Production working tree is not clean."
    echo
    git status --short
    exit 1
fi

echo "Git working tree is clean."
echo "Current commit: $(git rev-parse --short HEAD)"

# ------------------------------------------------
# 2. Fetch remote
# ------------------------------------------------

echo
echo "[2/10] Checking GitHub..."

git fetch origin "$BRANCH"

REMOTE_COMMIT=$(git rev-parse "origin/${BRANCH}")
LOCAL_COMMIT=$(git rev-parse HEAD)

echo "Local : ${LOCAL_COMMIT}"
echo "Remote: ${REMOTE_COMMIT}"

if [[ "$LOCAL_COMMIT" == "$REMOTE_COMMIT" ]]; then
    echo "Production is already up to date."
else
    echo "New deployment available."
fi

# ------------------------------------------------
# 3. Database backup
# ------------------------------------------------

echo
echo "[3/10] Creating database backup..."

mkdir -p "$BACKUP_DIR"

DB_BACKUP="${BACKUP_DIR}/softphoria-db-${DATE}.sql"

mysqldump \
    -u "$DB_USER" \
    -p \
    "$DB_NAME" \
    > "$DB_BACKUP"

if [[ ! -s "$DB_BACKUP" ]]; then
    echo
    echo "ERROR: Database backup was not created correctly."
    exit 1
fi

echo "Database backup created:"
echo "$DB_BACKUP"

# ------------------------------------------------
# 4. Deploy Git code
# ------------------------------------------------

echo
echo "[4/10] Updating application code..."

git pull --ff-only origin "$BRANCH"

echo "Now running commit:"
git rev-parse --short HEAD

# ------------------------------------------------
# 5. Install PHP dependencies
# ------------------------------------------------

echo
echo "[5/10] Installing Composer dependencies..."

/home/softphuk/bin/composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

# ------------------------------------------------
# 6. Database migrations
# ------------------------------------------------

echo
echo "[6/10] Running database migrations..."

php artisan migrate --force

# ------------------------------------------------
# 7. Verify storage
# ------------------------------------------------

echo
echo "[7/10] Verifying storage..."

STORAGE_TARGET="$APP_DIR/storage/app/public"
STORAGE_LINK="$PUBLIC_DIR/storage"

if [[ ! -L "$STORAGE_LINK" ]]; then
    echo "Storage symlink missing."

    php artisan storage:link
else
    LINK_TARGET=$(readlink "$STORAGE_LINK")

    echo "Storage link:"
    echo "$STORAGE_LINK -> $LINK_TARGET"

    if [[ "$LINK_TARGET" != "$STORAGE_TARGET" ]]; then
        echo
        echo "ERROR: Storage symlink points to an unexpected location."
        echo "Expected: $STORAGE_TARGET"
        echo "Actual:   $LINK_TARGET"
        exit 1
    fi
fi

# ------------------------------------------------
# 8. Permissions
# ------------------------------------------------

echo
echo "[8/10] Verifying Laravel permissions..."

chmod -R ug+rwX storage bootstrap/cache

find storage/app/public \
    -type d \
    -exec chmod 755 {} \;

find storage/app/public \
    -type f \
    -exec chmod 644 {} \;

# ------------------------------------------------
# 9. Laravel optimization
# ------------------------------------------------

echo
echo "[9/10] Optimizing Laravel..."

php artisan optimize:clear
php artisan optimize

# ------------------------------------------------
# 10. Final verification
# ------------------------------------------------

echo
echo "[10/10] Final verification..."

php artisan about --only=environment

echo
echo "Git status:"
git status --short

echo
echo "=============================================="
echo " Deployment completed successfully"
echo "=============================================="
echo
echo "Commit:          $(git rev-parse --short HEAD)"
echo "Database backup: ${DB_BACKUP}"
echo "Media directory: ${STORAGE_TARGET}"
echo
echo "IMPORTANT:"
echo "- Production .env was preserved."
echo "- Production media was preserved."
echo "- Database backup was created before deployment."
echo