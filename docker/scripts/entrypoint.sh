#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/app/public \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ "${DB_CONNECTION:-}" = "sqlite" ] && [ -n "${DB_DATABASE:-}" ]; then
    mkdir -p "$(dirname "${DB_DATABASE}")"
    if [ ! -f "${DB_DATABASE}" ]; then
        touch "${DB_DATABASE}"
        chown www-data:www-data "${DB_DATABASE}"
    fi
fi

run_artisan() {
    su -s /bin/sh -c "php /var/www/html/artisan $*" www-data
}

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    run_artisan migrate --force
fi

run_artisan package:discover --ansi

if [ "${RUN_STORAGE_LINK:-true}" = "true" ]; then
    run_artisan storage:link --force 2>/dev/null || run_artisan storage:link || true
fi

if [ "${RUN_OPTIMIZE:-true}" = "true" ]; then
    run_artisan config:cache
    run_artisan route:cache
    run_artisan view:cache
fi

exec "$@"
