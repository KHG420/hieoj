#!/bin/bash
# Scheduled logical backup. A maintenance-window backup is still required before migration.
set -euo pipefail
umask 077
cd "$(dirname "$0")/../.."
# Leave at least 3 GiB before starting a backup so database temporary files and
# PHP sessions still have working space. Current full archives use about 614 MiB.
available_kb=$(df -Pk /var/backups | awk 'NR == 2 { print $4 }')
if ! [[ "$available_kb" =~ ^[0-9]+$ ]] || (( available_kb < 3 * 1024 * 1024 )); then
    echo "Backup aborted: at least 3 GiB free space is required in /var/backups (available: ${available_kb:-unknown} KiB)." >&2
    exit 1
fi
# Cron runs as root without SUDO_UID; trust only this known repository, and
# resolve its version before taking locks or creating a partial backup.
backup_commit=$(git -c safe.directory="$PWD" rev-parse HEAD)
backup_dir=/var/backups/hnieoj-unified/$(date +%Y%m%d-%H%M%S)
mkdir -p "$backup_dir"
docker compose exec -T db sh -c 'MYSQL_PWD="$(cat /run/oj-secrets/root-password)" mariadb-dump -uroot --lock-all-tables --routines --events --triggers --hex-blob --databases jol' | gzip > "$backup_dir/jol.sql.gz"
docker compose exec -T web tar -czf - -C /home/judge data src/web/upload > "$backup_dir/data-upload.tar.gz"
docker compose exec -T db tar -czf - -C /run oj-secrets > "$backup_dir/credentials.tar.gz"
docker compose exec -T web tar -czf - -C /var/lib oj-nginx > "$backup_dir/nginx-state.tar.gz"
printf '%s\n' "$backup_commit" > "$backup_dir/commit"
sha256sum "$backup_dir"/*.gz > "$backup_dir/SHA256SUMS"
# Cleanup is allowed only after the latest backup passes checksum and gzip checks.
python3 docker/operations/prune_backups.py --apply
echo "Backup complete: $backup_dir"
