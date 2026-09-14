-- MariaDB migration. Pause submission/judging while applying for the first time.
-- All money-related tables are transactional; existing tables and IDs stay unchanged.
CREATE TABLE IF NOT EXISTS coin_wallet (
  user_id varchar(48) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL PRIMARY KEY,
  balance bigint unsigned NOT NULL DEFAULT 0,
  KEY coin_ranking (balance, user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coin_first_ac (
  user_id varchar(48) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  problem_id int NOT NULL,
  solution_id int NOT NULL,
  PRIMARY KEY (user_id, problem_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS problem_editorial (
  id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  problem_id int NOT NULL,
  user_id varchar(48) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  title varchar(120) NOT NULL,
  content mediumtext NOT NULL,
  content_format enum('plain','markdown') NOT NULL DEFAULT 'plain',
  status enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  review_note varchar(500) NOT NULL DEFAULT '',
  reviewer varchar(48) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at datetime DEFAULT NULL,
  KEY problem_status (problem_id, status, id),
  KEY author_list (user_id, id),
  KEY review_queue (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS problem_unlock (
  user_id varchar(48) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  problem_id int NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, problem_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coin_ledger (
  id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id varchar(48) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  kind enum('first_ac','editorial_reward','problem_unlock') NOT NULL,
  reference_id bigint unsigned NOT NULL,
  amount int NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_event (user_id, kind, reference_id),
  KEY user_history (user_id, id)
) ENGINE=InnoDB;

-- Historical ACs qualify for authoring but never receive retroactive coins.
INSERT IGNORE INTO coin_first_ac (user_id, problem_id, solution_id)
SELECT user_id, problem_id, MIN(solution_id) FROM solution
WHERE result=4 AND problem_id>0 GROUP BY user_id, problem_id;

DELIMITER //
CREATE TRIGGER IF NOT EXISTS coin_solution_accepted_update AFTER UPDATE ON solution
FOR EACH ROW
BEGIN
  DECLARE first_accept BOOLEAN DEFAULT TRUE;
  IF NEW.result=4 AND OLD.result<>4 AND NEW.problem_id>0 THEN
    BEGIN
      DECLARE CONTINUE HANDLER FOR 1062 SET first_accept=FALSE;
      INSERT INTO coin_first_ac(user_id,problem_id,solution_id)
        VALUES(NEW.user_id,NEW.problem_id,NEW.solution_id);
    END;
    IF first_accept THEN
      INSERT INTO coin_wallet(user_id,balance) VALUES(NEW.user_id,2)
        ON DUPLICATE KEY UPDATE balance=balance+2;
      INSERT INTO coin_ledger(user_id,kind,reference_id,amount)
        VALUES(NEW.user_id,'first_ac',NEW.problem_id,2);
    END IF;
  END IF;
END//
CREATE TRIGGER IF NOT EXISTS coin_solution_accepted_insert AFTER INSERT ON solution
FOR EACH ROW
BEGIN
  DECLARE first_accept BOOLEAN DEFAULT TRUE;
  IF NEW.result=4 AND NEW.problem_id>0 THEN
    BEGIN
      DECLARE CONTINUE HANDLER FOR 1062 SET first_accept=FALSE;
      INSERT INTO coin_first_ac(user_id,problem_id,solution_id)
        VALUES(NEW.user_id,NEW.problem_id,NEW.solution_id);
    END;
    IF first_accept THEN
      INSERT INTO coin_wallet(user_id,balance) VALUES(NEW.user_id,2)
        ON DUPLICATE KEY UPDATE balance=balance+2;
      INSERT INTO coin_ledger(user_id,kind,reference_id,amount)
        VALUES(NEW.user_id,'first_ac',NEW.problem_id,2);
    END IF;
  END IF;
END//
DELIMITER ;
