-- Additive and safe to rerun. Private attachments live with their owning request.
CREATE TABLE IF NOT EXISTS acm_lab_settings (
 id TINYINT PRIMARY KEY,
 introduction TEXT NOT NULL,
 contact VARCHAR(500) NOT NULL DEFAULT '',
 recruitment_open TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO acm_lab_settings VALUES (1,'一起学习算法、参与训练和竞赛，也一起完善我们的在线评测平台。欢迎对编程和算法感兴趣的同学申请，零基础也可以介绍自己的学习计划。具体训练安排由管理员在这里公布。','',1);
CREATE TABLE IF NOT EXISTS acm_lab_request (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id VARCHAR(48) NOT NULL,
 kind ENUM('join','bug','suggestion') NOT NULL,
 title VARCHAR(120) NOT NULL,
 details TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 reply TEXT NOT NULL,
 admin_note TEXT NOT NULL,
 screenshot MEDIUMBLOB NULL,
 screenshot_mime VARCHAR(32) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX owner_requests(user_id,id), INDEX queue_requests(kind,status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
