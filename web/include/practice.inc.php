<?php
require_once __DIR__.'/transactional_db.inc.php';

// Public practice excludes hidden, private-contest and ongoing-contest problems.
function practice_public_sql() {
    return "p.defunct='N' AND NOT EXISTS (SELECT 1 FROM contest_problem cp
        JOIN contest c ON c.contest_id=cp.contest_id WHERE cp.problem_id=p.problem_id
        AND (c.private=1 OR (c.defunct='N' AND c.end_time>NOW())))";
}

function practice_problem($id) {
    return oj_query('SELECT p.problem_id,p.title FROM problem p WHERE p.problem_id=? AND '.practice_public_sql(), array($id))->fetch(PDO::FETCH_ASSOC);
}
