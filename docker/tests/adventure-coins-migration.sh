#!/bin/sh
# Regression for the focused adventure_reward enum migration.
#
# Runs inside the db container of a throwaway local deployment, uses randomly
# named scratch databases and drops them on exit. Never touches the live jol
# database.
#
#   docker cp docker/tests/adventure-coins-migration.sh hieoj-pr1-fix-db:/tmp/
#   docker exec hieoj-pr1-fix-db sh /tmp/adventure-coins-migration.sh \
#       /candidate-db/adventure-coins.sql /candidate-db/solutions-coins.sql /candidate-db/schema.sql
#
# Arguments are optional and default to the files baked into the image.
set -eu
export MYSQL_PWD="$(cat /run/oj-secrets/root-password)"
MIGRATION=${1:-/opt/oj/adventure-coins.sql}
FRESH=${2:-/opt/oj/solutions-coins.sql}
BASE=${3:-/opt/oj/schema.sql}
for file in "$MIGRATION" "$FRESH" "$BASE"; do
    if [ ! -r "$file" ]; then
        echo "FAIL: required SQL file is not readable: $file" >&2
        exit 1
    fi
done
NEW_ENUM="enum('first_ac','editorial_reward','problem_unlock','adventure_reward')"
old=adventure_old_$$
legacy=adventure_legacy_$$
fresh=adventure_fresh_$$
OLD_SCHEMA="/tmp/adventure-old-schema.$$.sql"
cleanup() {
    mariadb -uroot -e "DROP DATABASE IF EXISTS $old; DROP DATABASE IF EXISTS $legacy; DROP DATABASE IF EXISTS $fresh"
    rm -f "$OLD_SCHEMA"
}
trap cleanup EXIT
check() {
    if [ "$2" != "$3" ]; then
        echo "FAIL: $1 (expected [$2], got [$3])" >&2
        exit 1
    fi
    echo "ok: $1"
}
column_type() {
    mariadb -uroot -N "$1" -e "SELECT COLUMN_TYPE FROM information_schema.columns
        WHERE table_schema='$1' AND table_name='coin_ledger' AND column_name='kind'"
}
fingerprint_wallet() {
    mariadb -uroot -N "$1" -e "SELECT CONCAT(COUNT(*),'/',COALESCE(SUM(balance),0),'/',
        COALESCE(BIT_XOR(CRC32(CONCAT_WS('|',user_id,balance))),0)) FROM coin_wallet"
}
fingerprint_ledger() {
    mariadb -uroot -N "$1" -e "SELECT CONCAT(COUNT(*),'/',COALESCE(SUM(amount),0),'/',
        COALESCE(BIT_XOR(CRC32(CONCAT_WS('|',id,user_id,kind,reference_id,amount,created_at))),0)) FROM coin_ledger"
}

# The pre-PR definition, kept inline so the regression does not depend on git history.
cat > "$OLD_SCHEMA" <<'SQL'
CREATE TABLE coin_wallet (
  user_id varchar(48) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL PRIMARY KEY,
  balance bigint unsigned NOT NULL DEFAULT 0,
  KEY coin_ranking (balance, user_id)
) ENGINE=InnoDB;
CREATE TABLE coin_ledger (
  id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id varchar(48) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  kind enum('first_ac','editorial_reward','problem_unlock') NOT NULL,
  reference_id bigint unsigned NOT NULL,
  amount int NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_event (user_id, kind, reference_id),
  KEY user_history (user_id, id)
) ENGINE=InnoDB;
SQL

mariadb -uroot -e "CREATE DATABASE $old CHARACTER SET utf8mb4; CREATE DATABASE $legacy CHARACTER SET utf8mb4; CREATE DATABASE $fresh CHARACTER SET utf8mb4"

# 1. An existing installation with the old enum, real wallet and ledger rows.
mariadb -uroot "$old" < "$OLD_SCHEMA"
mariadb -uroot "$old" -e "INSERT INTO coin_wallet(user_id,balance) VALUES('legacy_a',42),('legacy_b',7);
    INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES
    ('legacy_a','first_ac',1000,2),('legacy_a','editorial_reward',5,10),
    ('legacy_a','problem_unlock',1000,-5),('legacy_b','first_ac',1001,2)"
wallet_before=$(fingerprint_wallet "$old")
ledger_before=$(fingerprint_ledger "$old")
check "old installation starts without adventure_reward" \
    "enum('first_ac','editorial_reward','problem_unlock')" "$(column_type "$old")"

# 2. The recorded failure mode: outside strict mode the old enum coerced the
#    missing kind to '' and paid anyway. The candidate code must prevent that
#    by requiring the migrated schema before any money moves.
mariadb -uroot "$legacy" < "$OLD_SCHEMA"
mariadb -uroot "$legacy" -e "INSERT INTO coin_wallet(user_id,balance) VALUES('coerced',0);
    SET SESSION sql_mode='';
    INSERT IGNORE INTO coin_ledger(user_id,kind,reference_id,amount) VALUES('coerced','adventure_reward',20260921,10);
    UPDATE coin_wallet SET balance=balance+10 WHERE user_id='coerced'"
check "old enum reproduces the silent coercion" "" \
    "$(mariadb -uroot -N "$legacy" -e "SELECT kind FROM coin_ledger LIMIT 1")"
check "old enum reproduction credited the wallet" "10" \
    "$(mariadb -uroot -N "$legacy" -e "SELECT balance FROM coin_wallet WHERE user_id='coerced'")"

# 3. Upgrade fidelity: rows, amounts, indexes and the unique key survive.
migration_output=$(mariadb -uroot "$old" < "$MIGRATION")
check "migration extends the enum" "$NEW_ENUM" "$(column_type "$old")"
check "wallet untouched" "$wallet_before" "$(fingerprint_wallet "$old")"
check "ledger untouched" "$ledger_before" "$(fingerprint_ledger "$old")"
check "one_event unique key kept" "3" \
    "$(mariadb -uroot -N "$old" -e "SELECT COUNT(*) FROM information_schema.statistics
        WHERE table_schema='$old' AND table_name='coin_ledger' AND index_name='one_event' AND non_unique=0")"

# 4. Reapplying the migration is safe and still reports the review queries.
mariadb -uroot "$old" < "$MIGRATION" >/dev/null
check "reapply keeps the enum" "$NEW_ENUM" "$(column_type "$old")"
check "reapply keeps the wallet" "$wallet_before" "$(fingerprint_wallet "$old")"
check "reapply keeps the ledger" "$ledger_before" "$(fingerprint_ledger "$old")"
check "reapply reports empty kinds for review" "0" \
    "$(printf '%s\n' "$migration_output" | awk -F'\t' '$1=="empty_kind_rows"{print $2}')"
mariadb -uroot "$old" -e "INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES('legacy_a','adventure_reward',20260921,10)"
check "migrated ledger accepts the real kind" "1" \
    "$(mariadb -uroot -N "$old" -e "SELECT COUNT(*) FROM coin_ledger WHERE kind='adventure_reward'")"
if mariadb -uroot "$old" -e "INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES('legacy_a','adventure_reward',20260921,10)" 2>/dev/null; then
    echo "FAIL: duplicate same-day claim must violate one_event" >&2
    exit 1
fi

# 5. Historical invalid data is reported for the operator, never rewritten.
mariadb -uroot "$legacy" < "$MIGRATION" >/dev/null
mariadb -uroot "$legacy" -e "INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES('dupe','adventure_reward',20260920,10),('dupe','adventure_reward',20260921,10)"
review_output=$(mariadb -uroot "$legacy" < "$MIGRATION")
check "coerced empty kind reported" "1" \
    "$(printf '%s\n' "$review_output" | awk -F'\t' '$1=="empty_kind_rows"{print $2}')"
check "duplicate same-day rewards reported" "1" \
    "$(printf '%s\n' "$review_output" | awk -F'\t' '$1=="duplicate_day_rewards"{print $2}')"
check "review queries left history alone" "1" \
    "$(mariadb -uroot -N "$legacy" -e "SELECT COUNT(*) FROM coin_ledger WHERE kind=''")"

# 6. Fresh installations are already correct and tolerate the migration.
mariadb -uroot "$fresh" < "$BASE"
mariadb -uroot "$fresh" < "$FRESH"
check "fresh schema carries adventure_reward" "$NEW_ENUM" "$(column_type "$fresh")"
mariadb -uroot "$fresh" < "$MIGRATION" >/dev/null
check "fresh schema survives the migration" "$NEW_ENUM" "$(column_type "$fresh")"
mariadb -uroot "$fresh" -e "INSERT INTO solution(user_id,problem_id,result,in_date) VALUES('fresh_user',1000,4,NOW())"
check "fresh first-AC reward kept" "2" \
    "$(mariadb -uroot -N "$fresh" -e "SELECT balance FROM coin_wallet WHERE user_id='fresh_user'")"
check "fresh first-AC ledger kept" "first_ac" \
    "$(mariadb -uroot -N "$fresh" -e "SELECT kind FROM coin_ledger WHERE user_id='fresh_user'")"

echo "PASS: adventure_reward migration preserves existing money rows, is repeatable, reports invalid history and supports fresh installs."
