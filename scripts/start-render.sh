#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ] && [ -z "${APP_URL:-}" ]; then
  export APP_URL="https://${RENDER_EXTERNAL_HOSTNAME}"
fi

is_valid_app_key() {
  php -r '
$key = getenv("APP_KEY") ?: "";
if (! str_starts_with($key, "base64:")) {
    exit(1);
}
$decoded = base64_decode(substr($key, 7), true);
if ($decoded === false || strlen($decoded) !== 32) {
    exit(1);
}
'
}

if [ -z "${APP_KEY:-}" ] || ! is_valid_app_key; then
  echo "APP_KEY is missing or invalid. Generating a runtime key."
  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  DB_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
  mkdir -p "$(dirname "${DB_PATH}")"
  touch "${DB_PATH}"
  export DB_DATABASE="${DB_PATH}"
fi

php artisan config:clear
php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
fi

php artisan config:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
