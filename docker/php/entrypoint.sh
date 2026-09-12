#!/bin/sh
set -e

cd /var/www/html

# Config is supplied entirely via environment (12-factor), so no .env file is
# needed. Only the main API container prepares the schema; the queue worker
# shares the same image and just starts working.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "[entrypoint] waiting for the database at ${DB_HOST}:${DB_PORT}…"
  until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);" 2>/dev/null; do
    sleep 2
  done

  echo "[entrypoint] running migrations + seed…"
  php artisan migrate --force --seed --no-interaction
  php artisan storage:link || true
fi

exec "$@"
