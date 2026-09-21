-- Existing deployments: apply this focused migration before enabling the
-- Adventure coin reward. A fresh installation already carries the value, and
-- rebuilding or restarting the db container never upgrades an existing volume.
--
-- It only extends the ledger kind enum. Wallet balances, ledger rows, amounts
-- and the one_event unique key are untouched, and reapplying it is safe.
ALTER TABLE coin_ledger
  MODIFY COLUMN kind enum('first_ac','editorial_reward','problem_unlock','adventure_reward') NOT NULL;

-- Read-only review for the operator; nothing below rewrites history.
--  empty_kind_rows: rows the old INSERT IGNORE silently coerced to '' while the
--    enum lacked adventure_reward (the wallet was credited, the label was lost).
--  duplicate_day_rewards: users paid adventure coins more than once on one
--    calendar day by the previous session-random reward id.
SELECT 'empty_kind_rows' AS check_name, COUNT(*) AS rows_found FROM coin_ledger WHERE kind='';
SELECT 'duplicate_day_rewards' AS check_name, COUNT(*) AS users_found FROM (
  SELECT user_id FROM coin_ledger WHERE kind='adventure_reward'
  GROUP BY user_id, DATE(created_at) HAVING COUNT(*)>1
) duplicated;
