#!/bin/sh
set -eu

echo "→ Preparing var directory…"
mkdir -p /app/var/data

echo "→ Creating database schema…"
php /app/bin/console doctrine:schema:create --no-interaction --env=prod || \
    php /app/bin/console doctrine:schema:update --force --no-interaction --env=prod

echo "→ Warming cache…"
php /app/bin/console cache:warm --env=prod

echo "→ Starting FrankenPHP…"
exec frankenphp run --config /app/docker/Caddyfile
