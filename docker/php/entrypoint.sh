#!/bin/sh
set -e

# The project directory is bind-mounted from the Windows host, so file
# ownership does not reliably match the php-fpm worker user (www-data).
# Realign the directories Laravel writes to at runtime (compiled views,
# cache, logs) on every container start.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache

exec "$@"
