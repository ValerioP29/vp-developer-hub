#!/bin/sh
set -eu
if [ -z "${APP_KEY:-}" ]; then echo 'APP_KEY is required; generate once and preserve it in the secret store.' >&2; exit 1; fi
if [ "${APP_ENV:-production}" = production ] && [ "${APP_DEBUG:-false}" != false ]; then echo 'APP_DEBUG must be false in production.' >&2; exit 1; fi
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
gosu www-data php artisan package:discover --ansi
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache
# Migrations are an explicit release step; never run them from worker or scheduler.
if [ "${RUN_MIGRATIONS:-false}" = true ] && [ "$1" = apache2-foreground ]; then gosu www-data php artisan migrate --force; fi
if [ "$1" = apache2-foreground ]; then exec docker-php-entrypoint "$@"; fi
exec gosu www-data "$@"
