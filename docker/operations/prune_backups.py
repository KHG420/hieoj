#!/usr/bin/env python3
"""Preview scheduled-backup retention; --apply deletes the listed old backups."""

import argparse
from datetime import datetime, timedelta
import gzip
import hashlib
from pathlib import Path
import re
import shutil
import sys


BACKUP_ROOT = Path("/var/backups/hnieoj-unified")
ARCHIVES = (
    "jol.sql.gz",
    "data-upload.tar.gz",
    "credentials.tar.gz",
    "nginx-state.tar.gz",
)


def scheduled_backups(root):
    backups = {}
    for path in root.iterdir():
        if path.is_symlink() or not path.is_dir():
            continue
        if not re.fullmatch(r"\d{8}-\d{6}", path.name):
            continue
        try:
            backups[path] = datetime.strptime(path.name, "%Y%m%d-%H%M%S")
        except ValueError:
            continue
    return backups


def verify_backup(path):
    files = [path / name for name in (*ARCHIVES, "commit", "SHA256SUMS")]
    if any(file.is_symlink() or not file.is_file() for file in files):
        raise ValueError(f"Incomplete backup: {path}")
    if not re.fullmatch(r"[0-9a-f]{40}\n?", (path / "commit").read_text()):
        raise ValueError(f"Missing or invalid commit: {path}")
    checksums = {}
    lines = (path / "SHA256SUMS").read_text().splitlines()
    for line in lines:
        checksum, separator, filename = line.partition("  ")
        if not separator or not re.fullmatch(r"[0-9a-f]{64}", checksum):
            raise ValueError(f"Invalid checksum manifest: {path}")
        checksums[filename] = checksum
    if len(lines) != len(ARCHIVES) or set(checksums) != {
        str(path / name) for name in ARCHIVES
    }:
        raise ValueError(f"Checksum manifest must cover all four archives: {path}")
    for name in ARCHIVES:
        archive = path / name
        digest = hashlib.sha256()
        with archive.open("rb") as source:
            while chunk := source.read(1024 * 1024):
                digest.update(chunk)
        if digest.hexdigest() != checksums[str(archive)]:
            raise ValueError(f"Checksum mismatch: {archive}")
        with gzip.open(archive, "rb") as source:
            if not source.read(1024 * 1024):
                raise ValueError(f"Empty archive: {archive}")
            while source.read(1024 * 1024):
                pass


def prune_backups(root, now, apply=False):
    if root.is_symlink() or not root.is_dir():
        raise ValueError(f"Backup root must be a real directory: {root}")
    backups = scheduled_backups(root)
    if not backups:
        raise ValueError("No scheduled backup found; cleanup refused")
    latest = max(backups, key=backups.get)
    age = now - backups[latest]
    if age < -timedelta(minutes=5) or age > timedelta(hours=48):
        raise ValueError("Latest backup must be within the last 48 hours; cleanup refused")
    # Validate before any deletion, including when invoked manually after a failure.
    verify_backup(latest)
    print(f"Verified latest backup: {latest}", flush=True)

    kept, expired, weekly = [], [], set()
    for path in sorted(backups, reverse=True):
        days = (now.date() - backups[path].date()).days
        if days < 7:
            kept.append(path)
        elif days < 35 and (days - 7) // 7 not in weekly:
            weekly.add((days - 7) // 7)
            kept.append(path)
        else:
            expired.append(path)
    for path in kept:
        print(f"KEEP {path}", flush=True)
    for path in expired:
        print(f"{'DELETE' if apply else 'WOULD DELETE'} {path}", flush=True)
        if apply:
            # rmtree refuses a directory replaced by a symlink; it does not follow
            # symlinks within an old backup into production data or other backups.
            shutil.rmtree(path)
    print(
        f"Retention {'applied' if apply else 'preview'}: "
        f"kept={len(kept)}, {'deleted' if apply else 'would_delete'}={len(expired)}; "
        "manual backups untouched",
        flush=True,
    )


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--apply", action="store_true", help="delete expired backups")
    args = parser.parse_args()
    try:
        prune_backups(BACKUP_ROOT, datetime.now(), apply=args.apply)
    except (OSError, ValueError, EOFError) as error:
        print(f"Backup cleanup aborted: {error}", file=sys.stderr)
        sys.exit(1)
