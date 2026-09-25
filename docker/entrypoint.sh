#!/bin/sh
set -eu

echo "→ Preparing var directory…"
mkdir -p /app/var/data

# NOTE — migrations deliberately do NOT run here.
#
# This entrypoint used to run `doctrine:migrations:migrate` on every container
# start. That breaks the moment there is more than one replica: N containers
# race the same migration against the same SQLite file (GUIDING-LIGHT §8.6).
# It also means a failed migration restarts forever instead of failing once.
#
# Migrations are now a separate one-shot command, documented in
# docs/examples/compose.yaml and the README.
echo "→ Warming cache…"
php /app/bin/console cache:warm --env=prod

echo "→ Starting FrankenPHP…"
exec frankenphp run --config /app/docker/Caddyfile
