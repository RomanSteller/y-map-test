#!/bin/sh
set -e

cd /var/www/html

# Конфиг целиком приходит через переменные окружения (12-factor), так что файл
# .env не нужен. Схему готовит только основной контейнер API; воркер очереди
# работает на том же образе и просто начинает разбирать задачи.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "[entrypoint] ждём базу на ${DB_HOST}:${DB_PORT}…"
  until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);" 2>/dev/null; do
    sleep 2
  done

  echo "[entrypoint] накатываем миграции + сид…"
  php artisan migrate --force --seed --no-interaction
  php artisan storage:link || true
fi

exec "$@"
