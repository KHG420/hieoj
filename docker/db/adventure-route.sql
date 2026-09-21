CREATE TABLE IF NOT EXISTS adventure_route_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    route_date DATE NOT NULL,
    node VARCHAR(100) NOT NULL,
    mode VARCHAR(20) NOT NULL DEFAULT 'challenge',
    problem1 INT NOT NULL,
    problem2 INT NOT NULL,
    problem3 INT NOT NULL,
    start_time DATETIME NOT NULL,
    cursor_id BIGINT NOT NULL DEFAULT 0,
    reward_id BIGINT NOT NULL,
    rewarded TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_day(user_id, route_date)
);