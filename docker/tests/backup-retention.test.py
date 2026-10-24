#!/usr/bin/env python3
"""Retention regression tests; all writes and deletions use temporary directories."""

from contextlib import redirect_stdout
from datetime import datetime, timedelta
import gzip
import hashlib
from io import StringIO
from pathlib import Path
import sys
from tempfile import TemporaryDirectory
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "operations"))
import prune_backups


class BackupRetentionTests(unittest.TestCase):
    def setUp(self):
        self.workspace = TemporaryDirectory()
        self.addCleanup(self.workspace.cleanup)
        self.root = Path(self.workspace.name) / "backups"
        self.root.mkdir()
        self.now = datetime(2026, 10, 25, 12)

    def backup(self, days, complete=False, seconds=0):
        timestamp = self.now - timedelta(days=days, seconds=seconds)
        path = self.root / timestamp.strftime("%Y%m%d-%H%M%S")
        path.mkdir()
        (path / "payload").write_text("old backup data")
        if complete:
            (path / "commit").write_text("a" * 40 + "\n")
            for name in prune_backups.ARCHIVES:
                with gzip.open(path / name, "wb") as archive:
                    archive.write(b"test backup data\n")
            self.manifest(path)
        return path

    def manifest(self, path):
        (path / "SHA256SUMS").write_text("".join(
            f"{hashlib.sha256((path / name).read_bytes()).hexdigest()}  {path / name}\n"
            for name in prune_backups.ARCHIVES
        ))

    def run_cleanup(self, apply=False):
        with redirect_stdout(StringIO()) as output:
            prune_backups.prune_backups(self.root, self.now, apply=apply)
        return output.getvalue()

    def test_keeps_seven_days_and_four_weekly_points(self):
        backups = {day: self.backup(day, complete=day == 0) for day in range(46)}
        same_day = self.backup(0, seconds=3600)
        older_weekly_duplicate = self.backup(7, seconds=3600)
        self.run_cleanup(apply=True)
        kept_days = {day for day, path in backups.items() if path.exists()}
        self.assertEqual(kept_days, set(range(7)) | {7, 14, 21, 28})
        self.assertTrue(same_day.exists())
        self.assertFalse(older_weekly_duplicate.exists())

    def test_default_is_preview_only(self):
        self.backup(0, complete=True)
        old = self.backup(40)
        output = self.run_cleanup()
        self.assertIn(f"WOULD DELETE {old}", output)
        self.assertEqual((old / "payload").read_text(), "old backup data")

    def test_checksum_failure_preserves_all_backups(self):
        current = self.backup(0, complete=True)
        old = self.backup(40)
        (current / "jol.sql.gz").write_bytes(b"damaged")
        with self.assertRaisesRegex(ValueError, "Checksum mismatch"):
            self.run_cleanup(apply=True)
        self.assertTrue(old.exists())

    def test_compression_failure_preserves_all_backups(self):
        current = self.backup(0, complete=True)
        old = self.backup(40)
        (current / "jol.sql.gz").write_bytes(b"not a gzip archive")
        self.manifest(current)
        with self.assertRaises(gzip.BadGzipFile):
            self.run_cleanup(apply=True)
        self.assertTrue(old.exists())

    def test_incomplete_or_empty_backup_preserves_all_backups(self):
        current = self.backup(0, complete=True)
        old = self.backup(40)
        for name in ("commit", "SHA256SUMS", "jol.sql.gz"):
            with self.subTest(missing=name):
                file = current / name
                original = file.read_bytes()
                file.unlink()
                with self.assertRaises(ValueError):
                    self.run_cleanup(apply=True)
                self.assertTrue(old.exists())
                file.write_bytes(original)
        with gzip.open(current / "jol.sql.gz", "wb"):
            pass
        self.manifest(current)
        with self.assertRaisesRegex(ValueError, "Empty archive"):
            self.run_cleanup(apply=True)
        self.assertTrue(old.exists())

    def test_stale_or_future_latest_backup_preserves_old_data(self):
        old = self.backup(40)
        stale = self.backup(3, complete=True)
        with self.assertRaisesRegex(ValueError, "48 hours"):
            self.run_cleanup(apply=True)
        self.assertTrue(old.exists())
        self.assertTrue(stale.exists())
        future = self.backup(-1, complete=True)
        with self.assertRaisesRegex(ValueError, "48 hours"):
            self.run_cleanup(apply=True)
        self.assertTrue(old.exists())
        self.assertTrue(future.exists())

    def test_preserves_manual_backups_and_does_not_follow_symlinks(self):
        self.backup(0, complete=True)
        old = self.backup(40)
        outside = Path(self.workspace.name) / "production-data"
        outside.mkdir()
        (outside / "important").write_text("keep this")
        (old / "linked-data").symlink_to(outside, target_is_directory=True)
        manual = self.root / "release-20260901"
        manual.mkdir()
        invalid_date = self.root / "20260230-000000"
        invalid_date.mkdir()
        linked_backup = self.root / "20260901-000000"
        linked_backup.symlink_to(outside, target_is_directory=True)
        self.run_cleanup(apply=True)
        self.assertFalse(old.exists())
        self.assertTrue(manual.exists())
        self.assertTrue(invalid_date.exists())
        self.assertTrue(linked_backup.is_symlink())
        self.assertEqual((outside / "important").read_text(), "keep this")


if __name__ == "__main__":
    unittest.main()
