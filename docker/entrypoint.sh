#!/usr/bin/env sh
set -eu

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ "${1:-web}" = "web" ]; then
    php artisan config:cache
    php artisan view:cache
    exec apache2-foreground
fi

if [ "${1:-}" = "worker" ]; then
    php artisan config:cache
    exec php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600
fi

if [ "${1:-}" = "scheduler" ]; then
    php artisan config:cache
    exec php artisan schedule:work
fi

exec "$@"
