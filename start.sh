#!/bin/bash
set -e

cd /var/www/backend

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -n "$APP_KEY" ]; then
    sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
fi

if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force
fi

if [ -n "$APP_URL" ]; then
    sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env
fi

if [ -n "$SESSION_DOMAIN" ]; then
    sed -i "s|^SESSION_DOMAIN=.*|SESSION_DOMAIN=$SESSION_DOMAIN|" .env
fi

sed -i "s/listen 8080/listen ${PORT:-8080}/" /etc/nginx/sites-enabled/default

echo "Waiting for MySQL..."
for i in $(seq 1 30); do
    if php -r "new PDO('mysql:host=${MYSQLHOST:-${MYSQL_HOST:-${DATABASE_HOST:-127.0.0.1}}};port=${MYSQLPORT:-${MYSQL_PORT:-${DATABASE_PORT:-3306}}}', '${MYSQLUSER:-${MYSQL_USER:-${DATABASE_USER:-root}}}', '${MYSQLPASSWORD:-${MYSQL_PASSWORD:-${DATABASE_PASSWORD:-}}}');" 2>/dev/null; then
        echo "MySQL ready!"
        break
    fi
    sleep 2
done

php artisan migrate --force

TABLE_COUNT=$(php -r "try { echo count(DB::select('SHOW TABLES')); } catch(\$e) { echo 0; }" 2>/dev/null || echo 0)
if [ "$TABLE_COUNT" -lt 2 ]; then
    php artisan db:seed --force
fi

php artisan package:discover --ansi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
