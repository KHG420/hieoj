<?php
require_once __DIR__.'/transactional_db.inc.php';

function coin_ready() {
    $tables = pdo_query("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE()
        AND table_name IN ('coin_wallet','coin_first_ac','coin_ledger')");
    $triggers = pdo_query("SELECT COUNT(*) n FROM information_schema.triggers WHERE trigger_schema=DATABASE()
        AND trigger_name IN ('coin_solution_accepted_update','coin_solution_accepted_insert')");
    return $tables && intval($tables[0]['n']) === 3 && $triggers && intval($triggers[0]['n']) === 2;
}

function coin_balance($user) {
    return $user ? (int)oj_query('SELECT balance FROM coin_wallet WHERE user_id=?', array($user))->fetchColumn() : 0;
}

// These helpers join the caller's transaction. Approval/unlock and money must
// commit together; never start or commit a nested transaction here.
function coin_lock_wallet($user) {
    oj_query('INSERT INTO coin_wallet(user_id,balance) VALUES(?,0) ON DUPLICATE KEY UPDATE balance=balance', array($user));
}

function coin_debit_unlock($user, $problem) {
    $debit = oj_query('UPDATE coin_wallet SET balance=balance-5 WHERE user_id=? AND balance>=5', array($user));
    if ($debit->rowCount() !== 1) throw new DomainException('金币不足，需要 5 金币。通过本题可免费查看全部题解。');
    oj_query("INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES(?,'problem_unlock',?,-5)", array($user,$problem));
}

function coin_reward_editorial($user, $id) {
    oj_query('INSERT INTO coin_wallet(user_id,balance) VALUES(?,10) ON DUPLICATE KEY UPDATE balance=balance+10', array($user));
    oj_query("INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES(?,'editorial_reward',?,10)", array($user,$id));
}

function coin_adventure_claimed($user, $referenceId) {
    return (bool)oj_query("SELECT 1 FROM coin_ledger WHERE user_id=? AND kind='adventure_reward' AND reference_id=? LIMIT 1", array($user,$referenceId))->fetchColumn();
}

function coin_reward_adventure($user, $referenceId, $amount = 10) {
    if (!$user || !$referenceId || $amount <= 0) { return false; }
    $db = oj_transaction_db();
    $db->beginTransaction();
    try {
        // A server without the focused migration would silently coerce the kind
        // to '' outside strict mode and still pay, so require the real value.
        $kind = oj_query("SELECT COLUMN_TYPE FROM information_schema.columns
            WHERE table_schema=DATABASE() AND table_name='coin_ledger' AND column_name='kind'")->fetchColumn();
        if (!$kind || strpos($kind, "'adventure_reward'") === false) {
            throw new RuntimeException('coin_ledger.kind lacks adventure_reward; apply docker/db/adventure-coins.sql.');
        }
        try {
            oj_query("INSERT INTO coin_ledger(user_id,kind,reference_id,amount) VALUES(?,'adventure_reward',?,?)",array($user, $referenceId, $amount));
        } catch (PDOException $e) {
            // The only unique key here is the per-event claim itself.
            if (intval($e->errorInfo[1] ?? 0) === 1062) { $db->rollBack(); return false; }
            throw $e;
        }
        oj_query('INSERT INTO coin_wallet(user_id,balance) VALUES(?,?) ON DUPLICATE KEY UPDATE balance=balance+VALUES(balance)',array($user, $amount));
        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack();}
        throw $e;
    }
}
