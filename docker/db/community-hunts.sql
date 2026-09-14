-- Apply explicitly to existing databases; fresh installations load this file once.
CREATE TABLE IF NOT EXISTS hunt_challenge (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  author CHAR(48) NOT NULL,
  title VARCHAR(160) NOT NULL,
  statement TEXT NOT NULL,
  buggy_source TEXT NOT NULL,
  reference_source TEXT NOT NULL,
  validator_source TEXT NOT NULL,
  language INT NOT NULL,
  hidden TINYINT NOT NULL DEFAULT 0,
  version INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY public_list (hidden,id), KEY author_list (author,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
CREATE TABLE IF NOT EXISTS hunt_attempt (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  challenge_id INT NOT NULL,
  challenge_version INT NOT NULL,
  user_id CHAR(48) NOT NULL,
  input_text TEXT NOT NULL,
  validator_sid INT NOT NULL,
  reference_sid INT NOT NULL,
  buggy_sid INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY user_attempts (user_id,created_at), KEY challenge_attempts (challenge_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
CREATE TABLE IF NOT EXISTS hunt_comment (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  target VARCHAR(48) NOT NULL,
  author CHAR(48) NOT NULL,
  content TEXT NOT NULL,
  deleted TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY target_comments (target,deleted,id), KEY author_time (author,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
