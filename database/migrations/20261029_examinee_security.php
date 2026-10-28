<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../config/database.php';$db=(new Database)->getConnection();
function securityColumn(PDO $db,string $table,string $name,string $definition):void{$q=$db->prepare('SHOW COLUMNS FROM '.$table.' LIKE ?');$q->execute([$name]);if(!$q->fetch())$db->exec('ALTER TABLE '.$table.' ADD COLUMN '.$name.' '.$definition);}
$duplicates=(int)$db->query("SELECT COUNT(*) FROM (SELECT LOWER(TRIM(email)) k FROM users WHERE NULLIF(TRIM(email),'') IS NOT NULL GROUP BY LOWER(TRIM(email)) HAVING COUNT(*)>1) repeated")->fetchColumn();
if($duplicates)throw new RuntimeException('Existing duplicate email identities require review before adding the unique rule. No users were merged.');
securityColumn($db,'users','email_key',"VARCHAR(100) GENERATED ALWAYS AS (NULLIF(LOWER(TRIM(email)),'')) STORED");
$q=$db->query("SHOW INDEX FROM users WHERE Key_name='unique_email_identity'");if(!$q->fetch())$db->exec('ALTER TABLE users ADD UNIQUE KEY unique_email_identity(email_key)');
securityColumn($db,'users','auth_version','INT UNSIGNED NOT NULL DEFAULT 0');securityColumn($db,'users','email_verified_at','DATETIME NULL');
securityColumn($db,'entrance_exam_appointments','slot_end_at','DATETIME NULL');
securityColumn($db,'email_outbox','recipient_policy',"ENUM('account','verification','security_notice') NOT NULL DEFAULT 'account'");securityColumn($db,'email_outbox','security_challenge_id','BIGINT UNSIGNED NULL');
$db->exec("CREATE TABLE IF NOT EXISTS account_verification_codes(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, selector CHAR(32) NOT NULL UNIQUE,
 user_id INT NULL, purpose ENUM('email_change','password_change','password_reset') NOT NULL,
 recipient_email VARCHAR(100) NULL, original_email VARCHAR(100) NULL,
 identifier_hash CHAR(64) NOT NULL, binding_hash CHAR(64) NOT NULL, request_ip_hash CHAR(64) NOT NULL,
 code_hash VARCHAR(255) NOT NULL, password_snapshot CHAR(64) NULL,
 created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 used_at DATETIME NULL, revoked_at DATETIME NULL,
 INDEX account_purpose(user_id,purpose,created_at), INDEX identifier_rate(identifier_hash,purpose,created_at), INDEX ip_rate(request_ip_hash,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$keyPath=__DIR__.'/../../config/security.local.php';if(!is_file($keyPath)){
 $bytes="<?php\n// Private encryption key for short-lived security-code email payloads.\nreturn ['key'=>".var_export(base64_encode(random_bytes(32)),true)."];\n";
 if(file_put_contents($keyPath,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('The private security key could not be saved.');@chmod($keyPath,0600);
}
echo "Email identity, verification-code, session and exam cutoff storage ready. Existing records preserved.\n";
