<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../config/database.php';
$db=(new Database)->getConnection();
$existing=$db->query("SHOW TABLES LIKE 'report_approvals'")->fetchColumn();
if(!$existing)$db->exec("CREATE TABLE report_approvals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 requester_id INT NOT NULL, resource VARCHAR(40) NOT NULL, output_format VARCHAR(12) NOT NULL,
 scope_json MEDIUMTEXT NOT NULL, scope_hash CHAR(64) NOT NULL,
 status ENUM('pending','approved','rejected','used','revoked') NOT NULL DEFAULT 'pending',
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 decided_by INT NULL, decided_at DATETIME NULL, used_at DATETIME NULL,
 decision_note VARCHAR(500) NULL,
 INDEX requester_scope(requester_id,scope_hash,status), INDEX review_queue(status,requested_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Report approval storage ready. Existing records preserved.\n";
