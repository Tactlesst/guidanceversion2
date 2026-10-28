<?php
require_once __DIR__.'/EmailIdentity.php';require_once __DIR__.'/SecurityCodeCipher.php';require_once __DIR__.'/EmailConfiguration.php';require_once __DIR__.'/EmailOutbox.php';require_once __DIR__.'/AccountSecurityMail.php';require_once __DIR__.'/NotificationService.php';require_once __DIR__.'/SystemLogger.php';
final class AccountSecurity {
 private PDO $db;private $clock;private $codes;private bool $ready;
 public function __construct(PDO $db,?callable $clock=null,?callable $codes=null,?bool $ready=null){$this->db=$db;$this->clock=$clock??static fn()=>new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));$this->codes=$codes??static fn()=>str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);$this->ready=$ready??EmailConfiguration::ready();}
 private function now():DateTimeImmutable{return ($this->clock)();}
 public static function password($value):string {if(!is_string($value)||strlen($value)<8||strlen($value)>72||str_contains($value,"\0"))throw new InvalidArgumentException('Use a password between 8 and 72 characters.');return $value;}
 private function user(int $id,bool $lock=false):array {$q=$this->db->prepare('SELECT id,role,email,password,auth_version,is_active,archived FROM users WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u||!(int)$u['is_active']||(int)$u['archived'])throw new RuntimeException('This account cannot be updated. Contact the Guidance Office.');return $u;}
 public function startOwn(int $uid,string $purpose,$targetEmail,$password,string $binding,string $ip):array {
  if(!in_array($purpose,['email_change','password_change'],true))throw new InvalidArgumentException('Choose an account security action.');$u=$this->user($uid);
  if(!is_string($password)||!password_verify($password,$u['password']))throw new InvalidArgumentException('Your current password is incorrect.');
  $email=$purpose==='email_change'?EmailIdentity::normalize($targetEmail):EmailIdentity::normalize((string)$u['email']);
  if($purpose==='email_change'){if($email===strtolower(trim((string)$u['email'])))throw new InvalidArgumentException('Enter a different email address.');EmailIdentity::requireAvailable($this->db,$email,$uid);}
  return $this->issue($u,$purpose,$email,$binding,$ip,'account:'.$uid);
 }
 public function startRecovery($identifier,string $binding,string $ip):array {
  if(!is_string($identifier)||strlen($identifier)>100||trim($identifier)==='')throw new InvalidArgumentException('Enter your registered email or student ID.');$identifier=trim($identifier);$key=strtolower($identifier);
  $q=$this->db->prepare("SELECT u.id,u.role,u.email,u.password,u.auth_version,u.is_active,u.archived FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE (LOWER(TRIM(u.email))=? OR UPPER(sp.student_id)=?) AND u.role IN ('student','examinee') AND u.is_active=1 AND COALESCE(u.archived,0)=0 LIMIT 1");$q->execute([$key,strtoupper($identifier)]);$user=$q->fetch(PDO::FETCH_ASSOC)?:null;
  $email=$user&&filter_var($user['email'],FILTER_VALIDATE_EMAIL)?strtolower(trim($user['email'])):null;if(!$email)$user=null;
  return $this->issue($user,'password_reset',$email,$binding,$ip,$key);
 }
 private function issue(?array $user,string $purpose,?string $email,string $binding,string $ip,string $identifier):array {
  if(!$this->ready)throw new RuntimeException('Email verification is temporarily unavailable. Contact the Guidance Office.');if(strlen($binding)<32)throw new RuntimeException('Reload this page before requesting a code.');
  $now=$this->now();$nowText=$now->format('Y-m-d H:i:s');$since=$now->modify('-1 hour')->format('Y-m-d H:i:s');$identifierHash=SecurityCodeCipher::fingerprint($identifier);$ipHash=SecurityCodeCipher::fingerprint('ip:'.$ip);$lock='account_code_'.substr(hash('sha256',($user['id']??$identifierHash).':'.$purpose),0,40);
  $q=$this->db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if(!(int)$q->fetchColumn())throw new RuntimeException('A verification request is already being saved. Try again.');
  try{$this->db->beginTransaction();if($user){$fresh=$this->user((int)$user['id'],true);if($fresh['password']!==$user['password']||$fresh['email']!==$user['email'])throw new RuntimeException('Your account changed. Please start again.');}
   $q=$this->db->prepare('SELECT created_at FROM account_verification_codes WHERE identifier_hash=? AND purpose=? ORDER BY id DESC LIMIT 1');$q->execute([$identifierHash,$purpose]);$last=$q->fetchColumn();if($last&&$last>$now->modify('-60 seconds')->format('Y-m-d H:i:s'))throw new RuntimeException('Wait at least 60 seconds before requesting another code.');
   $q=$this->db->prepare('SELECT COUNT(*) FROM account_verification_codes WHERE created_at>=? AND (identifier_hash=? OR (? IS NOT NULL AND user_id=? AND purpose=?))');$q->execute([$since,$identifierHash,$user['id']??null,$user['id']??null,$purpose]);if((int)$q->fetchColumn()>=5)throw new RuntimeException('Too many code requests. Try again later or contact the Guidance Office.');
   $q=$this->db->prepare('SELECT COUNT(*) FROM account_verification_codes WHERE request_ip_hash=? AND created_at>=?');$q->execute([$ipHash,$since]);if((int)$q->fetchColumn()>=30)throw new RuntimeException('Too many requests from this connection. Try again later.');
   if($user){$this->db->prepare('UPDATE account_verification_codes SET revoked_at=? WHERE user_id=? AND purpose=? AND used_at IS NULL AND revoked_at IS NULL')->execute([$nowText,$user['id'],$purpose]);$this->cancelQueued((int)$user['id'],$purpose);}
   $selector=bin2hex(random_bytes(16));$code=(string)($this->codes)();if(!preg_match('/^[0-9]{6}$/D',$code))throw new RuntimeException('Verification code could not be prepared.');
   $q=$this->db->prepare('INSERT INTO account_verification_codes(selector,user_id,purpose,recipient_email,original_email,identifier_hash,binding_hash,request_ip_hash,code_hash,password_snapshot,created_at,expires_at,attempts) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,0)');
   $q->execute([$selector,$user['id']??null,$purpose,$email,$user['email']??null,$identifierHash,SecurityCodeCipher::fingerprint($binding),$ipHash,password_hash($code,PASSWORD_DEFAULT),$user?hash('sha256',$user['password']):null,$nowText,$now->modify('+10 minutes')->format('Y-m-d H:i:s')]);$id=(int)$this->db->lastInsertId();
   if($user&&$email){$title=$purpose==='email_change'?'Verify your new email address':($purpose==='password_reset'?'Reset your password':'Confirm your password change');$html=AccountSecurityMail::render($title,$purpose==='email_change'?'Use this code to confirm the new email address for your SRCB Guidance account.':'Use this code to continue your requested password change.',$code,$purpose==='password_reset');$outbox=EmailOutbox::enqueue($this->db,(int)$user['id'],$email,'SRCB Guidance — '.$title,SecurityCodeCipher::seal($html),'security-code:'.$id);if(!$outbox)throw new RuntimeException('Verification email could not be queued.');$this->db->prepare("UPDATE email_outbox SET recipient_policy='verification',security_challenge_id=? WHERE id=?")->execute([$id,$outbox]);}
   $this->db->commit();return ['selector'=>$selector,'recipient'=>$email?EmailConfiguration::masked($email):null,'expires_at'=>$now->modify('+10 minutes')->format('Y-m-d H:i:s')];
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}finally{$this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
 }
 private function cancelQueued(int $uid,?string $purpose=null):void {
  $sql="UPDATE email_outbox e JOIN account_verification_codes c ON c.id=e.security_challenge_id SET e.status='failed',e.body_html='',e.last_error='Verification request was replaced or completed.' WHERE e.user_id=? AND e.recipient_policy='verification' AND e.status='pending'";$params=[$uid];if($purpose!==null){$sql.=' AND c.purpose=?';$params[]=$purpose;}$this->db->prepare($sql)->execute($params);
 }
 public function cancel(string $selector,string $binding,?int $actor=null):void {
  $q=$this->db->prepare('SELECT * FROM account_verification_codes WHERE selector=?');$q->execute([$selector]);$c=$q->fetch(PDO::FETCH_ASSOC);
  if(!$c||!hash_equals($c['binding_hash'],SecurityCodeCipher::fingerprint($binding))||($c['purpose']!=='password_reset'&&(int)$c['user_id']!==$actor))return;
  try{$this->db->beginTransaction();if($c['user_id'])$this->user((int)$c['user_id'],true);$revoke=$this->db->prepare('UPDATE account_verification_codes SET revoked_at=? WHERE id=? AND used_at IS NULL AND revoked_at IS NULL');$revoke->execute([$this->now()->format('Y-m-d H:i:s'),$c['id']]);if($c['user_id']&&$revoke->rowCount())$this->cancelQueued((int)$c['user_id'],$c['purpose']);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function complete(string $selector,string $binding,string $purpose,$code,?int $actorId=null,$newPassword=null):array {
  if(!preg_match('/^[a-f0-9]{32}$/D',$selector)||!in_array($purpose,['email_change','password_change','password_reset'],true))throw new InvalidArgumentException('Start a new verification request.');
  if($purpose!=='email_change')$newPassword=self::password($newPassword);
  $now=$this->now()->format('Y-m-d H:i:s');
  try{$this->db->beginTransaction();$q=$this->db->prepare('SELECT user_id FROM account_verification_codes WHERE selector=?');$q->execute([$selector]);$id=$q->fetchColumn();$u=$id?$this->user((int)$id,true):null;
   $q=$this->db->prepare('SELECT * FROM account_verification_codes WHERE selector=? FOR UPDATE');$q->execute([$selector]);$c=$q->fetch(PDO::FETCH_ASSOC);
   if(!$c||!hash_equals($c['binding_hash'],SecurityCodeCipher::fingerprint($binding))||$c['purpose']!==$purpose||($purpose!=='password_reset'&&(int)$c['user_id']!==$actorId)||$c['used_at']||$c['revoked_at']||$c['expires_at']<=$now||(int)$c['attempts']>=5)throw new RuntimeException('The code is expired or unavailable. Request a new code.');
   $valid=is_string($code)&&preg_match('/^[0-9]{6}$/D',$code)&&password_verify($code,$c['code_hash']);
   if(!$valid||!$u){$this->db->prepare('UPDATE account_verification_codes SET attempts=attempts+1 WHERE id=?')->execute([$c['id']]);$this->db->commit();throw new RuntimeException('The verification code is incorrect or unavailable.');}
   if(!hash_equals($c['password_snapshot'],hash('sha256',$u['password']))||strcasecmp((string)$c['original_email'],(string)$u['email'])!==0)throw new RuntimeException('Your account changed. Start a new verification request.');
   if($purpose==='email_change'){$email=EmailIdentity::normalize($c['recipient_email']);EmailIdentity::requireAvailable($this->db,$email,(int)$u['id']);$this->db->prepare('UPDATE users SET email=?,email_verified_at=?,auth_version=auth_version+1,reset_token=NULL,reset_token_expires=NULL WHERE id=?')->execute([$email,$now,$u['id']]);}
   else{$this->db->prepare('UPDATE users SET password=?,auth_version=auth_version+1,reset_token=NULL,reset_token_expires=NULL WHERE id=?')->execute([password_hash($newPassword,PASSWORD_DEFAULT),$u['id']]);$email=$u['email'];}
   $this->db->prepare('UPDATE account_verification_codes SET used_at=? WHERE id=?')->execute([$now,$c['id']]);$this->db->prepare('UPDATE account_verification_codes SET revoked_at=? WHERE user_id=? AND used_at IS NULL AND revoked_at IS NULL')->execute([$now,$u['id']]);$this->cancelQueued((int)$u['id']);
   $title=$purpose==='email_change'?'Account email changed':'Account password changed';$message=$purpose==='email_change'?'Your account email address was verified and changed.':'Your password was changed after email verification.';
   if(!(new NotificationService($this->db))->notify((int)$u['id'],$title,$message,'info',null,null,true,null,AccountSecurityMail::render($title,$message),'account-security:'.$c['id']))throw new RuntimeException('The security confirmation could not be saved.');
   if($purpose==='email_change'&&filter_var($c['original_email'],FILTER_VALIDATE_EMAIL)){$mail=EmailOutbox::enqueue($this->db,(int)$u['id'],$c['original_email'],'SRCB Guidance — Email address changed',AccountSecurityMail::render('Your account email was changed','The email address for your SRCB Guidance account has changed. If this was not you, contact the Guidance Office.'),'old-email-notice:'.$c['id']);$this->db->prepare("UPDATE email_outbox SET recipient_policy='security_notice',security_challenge_id=? WHERE id=?")->execute([$c['id'],$mail]);}
   (new SystemLogger($this->db))->info($purpose==='email_change'?'Verified own email address change':'Changed own password',(int)$u['id']);
   $this->db->commit();return ['user_id'=>(int)$u['id'],'email'=>$email,'auth_version'=>(int)$u['auth_version']+1,'purpose'=>$purpose];
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();if($e instanceof PDOException&&($e->errorInfo[0]??'')==='23000')throw new RuntimeException('This email address is already used. Start again with another email.');throw $e;}
 }
}
