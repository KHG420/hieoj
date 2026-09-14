-- Apply before deploying the Markdown editor. Existing bodies remain plain text.
-- MariaDB: additive and safe to reapply; no reward or permission changes.
ALTER TABLE problem_editorial
  ADD COLUMN IF NOT EXISTS content_format enum('plain','markdown') NOT NULL DEFAULT 'plain' AFTER content;
