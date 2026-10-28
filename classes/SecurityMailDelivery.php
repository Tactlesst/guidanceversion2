<?php
require_once __DIR__.'/SecurityCodeCipher.php';
final class SecurityMailExpired extends RuntimeException {}
final class SecurityMailDelivery {
 public static function body(PDO $db,array $row,array $user):string {
  $policy=$row['recipient_policy']??'account';if($policy==='account'){if(strcasecmp(trim((string)$user['email']),trim($row['to_email']))!==0)throw new SecurityMailExpired('The recipient account or email address changed.');return $row['body_html'];}
  $q=$db->prepare('SELECT * FROM account_verification_codes WHERE id=? AND user_id=?');$q->execute([$row['security_challenge_id']??0,$row['user_id']]);$c=$q->fetch(PDO::FETCH_ASSOC);if(!$c)throw new SecurityMailExpired('Verification request is unavailable.');
  if($policy==='verification'){
   $now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
   if($c['used_at']||$c['revoked_at']||$c['expires_at']<=$now||(int)$c['attempts']>=5||strcasecmp((string)$c['recipient_email'],$row['to_email'])!==0||strcasecmp((string)$c['original_email'],(string)$user['email'])!==0||!hash_equals((string)$c['password_snapshot'],hash('sha256',(string)$user['password'])))throw new SecurityMailExpired('Verification request expired or changed.');
   return SecurityCodeCipher::open($row['body_html']);
  }
  if($policy==='security_notice'&&$c['purpose']==='email_change'&&$c['used_at']&&strcasecmp((string)$c['original_email'],$row['to_email'])===0)return $row['body_html'];
  throw new SecurityMailExpired('Security notice recipient is unavailable.');
 }
}
