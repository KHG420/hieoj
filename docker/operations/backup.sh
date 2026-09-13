#!/bin/bash
# Scheduled logical backup. A maintenance-window backup is still required before migration.
set -euo pipefail
umask 077
cd "$(dirname "$0")/../.."
backup_dir=/var/backups/hnieoj-unified/$(date +%Y%m%d-%H%M%S)
mkdir -p "$backup_dir"
docker compose exec -T db sh -c 'MYSQL_PWD="$(cat /run/oj-secrets/root-password)" mariadb-dump -uroot --lock-all-tables --routines --events --triggers --hex-blob --databases jol' | gzip > "$backup_dir/jol.sql.gz"
docker compose exec -T web tar -czf - -C /home/judge data src/web/upload > "$backup_dir/data-upload.tar.gz"
docker compose exec -T db tar -czf - -C /run oj-secrets > "$backup_dir/credentials.tar.gz"
docker compose exec -T web tar -czf - -C /var/lib oj-nginx > "$backup_dir/nginx-state.tar.gz"
git rev-parse HEAD > "$backup_dir/commit"
sha256sum "$backup_dir"/*.gz > "$backup_dir/SHA256SUMS"
echo "Backup complete: $backup_dir"
# No automatic deletion or upload to an unverified third-party host.
