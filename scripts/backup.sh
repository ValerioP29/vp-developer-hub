#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
: "${BACKUP_DIR:?Set BACKUP_DIR to a private backup destination}"
backup_path="$BACKUP_DIR/vp-hub-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$backup_path"
resume() { docker compose start web worker scheduler >/dev/null; }
# Stop all application writers for a consistent database + documents backup.
docker compose stop web worker scheduler
trap resume EXIT
docker compose exec -T database sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner --no-acl' > "$backup_path/database.dump"
docker compose run --rm --no-deps --entrypoint tar web -C /var/www/html/storage/app/private -czf - . > "$backup_path/documents.tar.gz"
(cd "$backup_path" && sha256sum database.dump documents.tar.gz > SHA256SUMS)
printf 'Backup completed: %s\nPreserve APP_KEY separately in an encrypted secret store. Encrypt and copy this backup off-server.\n' "$backup_path"
