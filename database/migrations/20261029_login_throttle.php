<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../config/database.php';$db=(new Database)->getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS auth_login_throttle(bucket_key CHAR(64) NOT NULL PRIMARY KEY,attempts INT UNSIGNED NOT NULL DEFAULT 0,window_start DATETIME NOT NULL,locked_until DATETIME NULL,updated_at DATETIME NOT NULL,INDEX stale_counters(updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
echo "Persistent sign-in attempt storage is ready. No accounts changed.\n";
