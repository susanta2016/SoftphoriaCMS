#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="/home/softphuk/softphoria-cms"
PUBLIC_DIR="/home/softphuk/public_html"
BACKUP_DIR="/home/softphuk/backups"

BRANCH="develop"
DB_NAME="softphuk_softphoria"
DB_USER="softphuk_softphoria"

DATE=$(date +%Y%m%d-%H%M%S)

# Frontend build (Vite). public/build is gitignored, so the compiled CSS/JS
# must be built here on every deploy. Laravel reads the manifest from
# $APP_DIR/public/build, while the web server serves /build/... from
# $PUBLIC_DIR/build, so the build is also synced there.
BUILD_DIR="$APP_DIR/public/build"
PUBLIC_BUILD_DIR="$PUBLIC_DIR/build"
# Optional: a directory containing node/npm, if they are not on PATH
# (e.g. NODE_BIN_DIR=/opt/alt/alt-nodejs22/root/usr/bin). Otherwise the
# newest cPanel "Setup Node.js App" environment (~/nodevenv) is used.
NODE_BIN_DIR="${NODE_BIN_DIR:-}"

# Internal flag used when the script restarts itself
POST_UPDATE="${1:-false}"

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

echo "[1/11] Checking Git state..."

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
# 2. Fetch and synchronize GitHub
# ------------------------------------------------

echo
echo "[2/11] Checking GitHub..."

git fetch origin "$BRANCH"

REMOTE_COMMIT=$(git rev-parse "origin/${BRANCH}")
LOCAL_COMMIT=$(git rev-parse HEAD)

echo "Local : ${LOCAL_COMMIT}"
echo "Remote: ${REMOTE_COMMIT}"

if [[ "$LOCAL_COMMIT" == "$REMOTE_COMMIT" ]]; then

    echo "Production is already up to date."

else

    echo "New deployment available."
    echo
    echo "Updating application code..."

    git pull --ff-only origin "$BRANCH"

    echo "Now running commit:"
    git rev-parse --short HEAD

    echo
    echo "Restarting deployment script from updated code..."

    exec "$APP_DIR/scripts/deploy-production.sh" --post-update

fi

# ------------------------------------------------
# 3. Database backup
# ------------------------------------------------

echo
echo "[3/11] Creating database backup..."

mkdir -p "$BACKUP_DIR"

DB_BACKUP="${BACKUP_DIR}/softphoria-db-${DATE}.sql"

# Backup retention: only the backup of the latest *successful* deployment
# is kept — older ones are pruned in step 11, once everything succeeded.
# If this deployment fails at any point, nothing is pruned and this
# deployment's backup is kept as "...failed.sql" (the restore point if a
# migration half-ran); the next successful deployment removes it.
DEPLOY_SUCCEEDED=false

on_deploy_exit() {
    if [[ "$DEPLOY_SUCCEEDED" != "true" && -e "$DB_BACKUP" ]]; then
        if [[ -s "$DB_BACKUP" ]]; then
            mv "$DB_BACKUP" "${DB_BACKUP%.sql}.failed.sql"
            echo
            echo "Deployment FAILED. Pre-deployment database backup kept at:"
            echo "${DB_BACKUP%.sql}.failed.sql"
            echo "Older backups were not removed."
        else
            rm -f "$DB_BACKUP"
        fi
    fi
}

trap on_deploy_exit EXIT

DB_PASSWORD=$(php artisan tinker --execute="echo config('database.connections.mysql.password');")

if [[ -z "$DB_PASSWORD" ]]; then
    echo
    echo "ERROR: Production database password could not be read from Laravel configuration."
    exit 1
fi

MYSQL_PWD="$DB_PASSWORD" mariadb-dump \
    -u "$DB_USER" \
    "$DB_NAME" \
    > "$DB_BACKUP"

unset DB_PASSWORD

if [[ ! -s "$DB_BACKUP" ]]; then
    echo
    echo "ERROR: Database backup was not created correctly."
    exit 1
fi

echo "Database backup created:"
echo "$DB_BACKUP"

# ------------------------------------------------
# 4. Install PHP dependencies
# ------------------------------------------------

echo
echo "[4/11] Installing Composer dependencies..."

/home/softphuk/bin/composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

# ------------------------------------------------
# 5. Build frontend assets (Vite)
# ------------------------------------------------
#
# Runs before migrations so a failed build stops the deploy before any
# database change. The previous build is kept and restored if the build
# fails, so the site never loses its CSS/JS.

echo
echo "[5/11] Building frontend assets..."

if [[ -n "$NODE_BIN_DIR" ]]; then
    export PATH="$NODE_BIN_DIR:$PATH"
fi

if ! command -v npm >/dev/null 2>&1; then
    # cPanel "Setup Node.js App" (CloudLinux) environments:
    # ~/nodevenv/<app>/<version>/bin/activate — use the newest version.
    NODE_ACTIVATE=$(ls -d "$HOME"/nodevenv/*/*/bin/activate 2>/dev/null | sort -V | tail -n 1 || true)

    if [[ -n "$NODE_ACTIVATE" ]]; then
        echo "Using Node.js environment: $NODE_ACTIVATE"
        # The activate script is not written for `set -u`.
        set +u
        # shellcheck disable=SC1090
        source "$NODE_ACTIVATE"
        set -u
    fi
fi

if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
    echo
    echo "ERROR: Node.js/npm not found, so the frontend assets cannot be built."
    echo "Install Node.js 22 (cPanel → Setup Node.js App) or set NODE_BIN_DIR."
    exit 1
fi

# Vite 8 requires Node.js ^20.19 or >=22.12.
if ! node -e 'const [a, b] = process.versions.node.split(".").map(Number); process.exit((a === 20 && b >= 19) || (a === 22 && b >= 12) || a > 22 ? 0 : 1)'; then
    echo
    echo "ERROR: Node.js $(node --version) is too old for Vite (needs ^20.19 or >=22.12)."
    exit 1
fi

echo "Node: $(node --version), npm: $(npm --version)"

BUILD_BACKUP=""

if [[ -d "$BUILD_DIR" ]]; then
    BUILD_BACKUP="${BUILD_DIR}.previous"
    rm -rf "$BUILD_BACKUP"
    cp -a "$BUILD_DIR" "$BUILD_BACKUP"
fi

restore_previous_build() {
    if [[ -n "$BUILD_BACKUP" && -d "$BUILD_BACKUP" ]]; then
        rm -rf "$BUILD_DIR"
        mv "$BUILD_BACKUP" "$BUILD_DIR"
        echo "Previous frontend build restored."
    fi
}

if ! npm ci --no-audit --no-fund || ! npm run build; then
    echo
    echo "ERROR: Frontend build failed."
    restore_previous_build
    exit 1
fi

if [[ ! -s "$BUILD_DIR/manifest.json" ]]; then
    echo
    echo "ERROR: Frontend build produced no manifest ($BUILD_DIR/manifest.json)."
    restore_previous_build
    exit 1
fi

rm -rf "$BUILD_BACKUP"

# Publish to the web root, unless it is the same directory (symlinked).
if [[ "$(realpath -m "$PUBLIC_BUILD_DIR")" != "$(realpath -m "$BUILD_DIR")" ]]; then

    if [[ -L "$PUBLIC_BUILD_DIR" ]]; then
        echo
        echo "ERROR: $PUBLIC_BUILD_DIR is a symlink to an unexpected location:"
        echo "$(readlink "$PUBLIC_BUILD_DIR")"
        exit 1
    fi

    mkdir -p "$PUBLIC_BUILD_DIR"

    if command -v rsync >/dev/null 2>&1; then
        rsync -a --delete "$BUILD_DIR/" "$PUBLIC_BUILD_DIR/"
    else
        rm -rf "${PUBLIC_BUILD_DIR}.new"
        cp -a "$BUILD_DIR" "${PUBLIC_BUILD_DIR}.new"
        rm -rf "$PUBLIC_BUILD_DIR"
        mv "${PUBLIC_BUILD_DIR}.new" "$PUBLIC_BUILD_DIR"
    fi

    echo "Published build to: $PUBLIC_BUILD_DIR"
fi

echo "Frontend assets: $(grep -o '"file": *"assets/app-[^"]*\.css"' "$BUILD_DIR/manifest.json" | head -n 1 | sed 's/.*"assets\//assets\//; s/"$//')"

# ------------------------------------------------
# 6. Database migrations
# ------------------------------------------------

echo
echo "[6/11] Running database migrations..."

php artisan migrate --force

# ------------------------------------------------
# 7. Verify storage
# ------------------------------------------------

echo
echo "[7/11] Verifying storage..."

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
echo "[8/11] Verifying Laravel permissions..."

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
echo "[9/11] Optimizing Laravel..."

php artisan optimize:clear
php artisan optimize

# ------------------------------------------------
# 10. Final verification
# ------------------------------------------------

echo
echo "[10/11] Final verification..."

php artisan about --only=environment

echo
echo "Git status:"
git status --short

# ------------------------------------------------
# 11. Deployment complete
# ------------------------------------------------

# Every step succeeded: keep only this deployment's backup.
DEPLOY_SUCCEEDED=true

PRUNED_BACKUPS=$(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'softphoria-db-*.sql' ! -name "$(basename "$DB_BACKUP")" -print -delete | wc -l)

echo
echo "[11/11] Pruned ${PRUNED_BACKUPS} older database backup(s)."

echo
echo "=============================================="
echo " Deployment completed successfully"
echo "=============================================="
echo
echo "Commit:          $(git rev-parse --short HEAD)"
echo "Database backup: ${DB_BACKUP}"
echo "Media directory: ${STORAGE_TARGET}"
echo "Frontend build:  ${PUBLIC_BUILD_DIR}"
echo
echo "IMPORTANT:"
echo "- Frontend assets were rebuilt (npm ci + npm run build)."
echo "- Production .env was preserved."
echo "- Production media was preserved."
echo "- Database backup was created before deployment; only this latest one is kept."
echo