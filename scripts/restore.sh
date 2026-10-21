#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
restore_path="${1:?Usage: restore.sh /private/backup --confirm-replace-hub}"
if [ "${2:-}" != --confirm-replace-hub ]; then echo 'Explicit confirmation --confirm-replace-hub is required. Target only a dedicated Hub stack.' >&2; exit 1; fi
(cd "$restore_path" && sha256sum -c SHA256SUMS)
# Run only after an independent backup and verification of the target Compose project.
docker compose stop web worker scheduler
docker compose exec -T database sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-acl --exit-on-error' < "$restore_path/database.dump"
docker compose run --rm --no-deps --entrypoint sh web -c 'find /var/www/html/storage/app/private -mindepth 1 -delete'
docker compose run --rm --no-deps -T --entrypoint tar web -C /var/www/html/storage/app/private -xzf - < "$restore_path/documents.tar.gz"
docker compose run --rm --no-deps web php artisan migrate --force
docker compose start web worker scheduler
printf 'Restore complete. Verify health, MFA, documents and totals before reopening access.\n'
