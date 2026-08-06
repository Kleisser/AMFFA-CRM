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

# En Railway se traducen las variables de entorno al .env.
# En local (docker compose) el .env ya viene configurado y no se toca.
if [ -n "${RAILWAY_SERVICE_ID:-}" ] || [ -n "${MYSQLHOST:-}" ]; then
    DB_HOST_VAL="${MYSQLHOST:-${MYSQL_HOST:-${DATABASE_HOST:-127.0.0.1}}}"
    DB_PORT_VAL="${MYSQLPORT:-${MYSQL_PORT:-${DATABASE_PORT:-3306}}}"
    DB_DATABASE_VAL="${MYSQLDATABASE:-${MYSQL_DATABASE:-${DATABASE_NAME:-amffa_crm}}}"
    DB_USERNAME_VAL="${MYSQLUSER:-${MYSQL_USER:-${DATABASE_USER:-root}}}"
    DB_PASSWORD_VAL="${MYSQLPASSWORD:-${MYSQL_PASSWORD:-${DATABASE_PASSWORD:-}}}"

    sed -i "s|^DB_HOST=.*|DB_HOST=$DB_HOST_VAL|" .env
    sed -i "s|^DB_PORT=.*|DB_PORT=$DB_PORT_VAL|" .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE_VAL|" .env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME_VAL|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD_VAL|" .env
else
    DB_HOST_VAL=$(grep '^DB_HOST=' .env | cut -d= -f2-)
    DB_PORT_VAL=$(grep '^DB_PORT=' .env | cut -d= -f2-)
    DB_USERNAME_VAL=$(grep '^DB_USERNAME=' .env | cut -d= -f2-)
    DB_PASSWORD_VAL=$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)
fi

sed -i "s/listen 8080/listen ${PORT:-8080}/" /etc/nginx/sites-enabled/default

echo "Waiting for MySQL..."
for i in $(seq 1 30); do
    if php -r "new PDO('mysql:host=${DB_HOST_VAL};port=${DB_PORT_VAL}', '${DB_USERNAME_VAL}', '${DB_PASSWORD_VAL}');" 2>/dev/null; then
        echo "MySQL ready!"
        break
    fi
    sleep 2
done

php artisan migrate --force
php artisan db:seed --force

php artisan package:discover --ansi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
