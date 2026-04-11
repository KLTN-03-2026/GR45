#!/bin/sh
set -e

cd /workspace/GR45/BE

if [ ! -f ".env" ]; then
  if [ -f ".env.docker.example" ]; then
    cp .env.docker.example .env
  elif [ -f ".env.example" ]; then
    cp .env.example .env
  fi
fi

if [ -f ".env" ]; then
  sed -i 's|^APP_URL=.*|APP_URL=http://localhost:18080|g' .env 2>/dev/null || true
  sed -i 's|^APP_ENV=.*|APP_ENV=local|g' .env 2>/dev/null || true
  sed -i 's|^APP_DEBUG=.*|APP_DEBUG=true|g' .env 2>/dev/null || true
  sed -i 's|^LOG_LEVEL=.*|LOG_LEVEL=debug|g' .env 2>/dev/null || true
  sed -i 's|^LOG_CHANNEL=.*|LOG_CHANNEL=stack|g' .env 2>/dev/null || true
  sed -i 's|^DB_HOST=.*|DB_HOST=db|g' .env 2>/dev/null || true
  sed -i 's|^DB_PORT=.*|DB_PORT=3306|g' .env 2>/dev/null || true
  sed -i 's|^DB_DATABASE=.*|DB_DATABASE=gobus_db|g' .env 2>/dev/null || true
  sed -i 's|^DB_USERNAME=.*|DB_USERNAME=gobus_user|g' .env 2>/dev/null || true
  sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=gobus_pass|g' .env 2>/dev/null || true
  FRONTEND_URL_EFFECTIVE="${FRONTEND_URL:-http://localhost:15173}"
  if grep -qE '^FRONTEND_URL=' .env 2>/dev/null; then
    sed -i "s|^FRONTEND_URL=.*|FRONTEND_URL=${FRONTEND_URL_EFFECTIVE}|g" .env 2>/dev/null || true
  else
    printf '\nFRONTEND_URL=%s\n' "${FRONTEND_URL_EFFECTIVE}" >> .env
  fi
fi

composer install --no-interaction --prefer-dist

if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force
fi

echo "Waiting for database..."
until mysql --skip-ssl -h db -u"${DB_USERNAME:-gobus_user}" -p"${DB_PASSWORD:-gobus_pass}" -e "SELECT 1;" >/dev/null 2>&1; do
  sleep 2
done

php artisan migrate --force
php artisan config:clear

php artisan serve --host=0.0.0.0 --port=8000
