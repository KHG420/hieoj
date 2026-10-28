<?php
// Business operations share the existing connection, including its transaction.
// Unlike pdo_query(), failed writes must propagate so the caller can roll back.
function oj_transaction_db() {
    global $dbh;
    if (!$dbh) pdo_query('SELECT 1');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

function oj_query($sql, $args = array()) {
    $stmt = oj_transaction_db()->prepare($sql);
    $stmt->execute($args);
    return $stmt;
}
