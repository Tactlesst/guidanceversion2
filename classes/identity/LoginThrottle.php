<?php
require_once __DIR__.'/SecurityCodeCipher.php';
/** Persistent, bounded attempt counters contain only keyed hashes, never passwords or identifiers. */
final class LoginThrottle {
 private PDO $db;private $clock;
 public function __construct(PDO $db,?callable $clock=null){$this->db=$db;$this->clock=$clock??static fn()=>new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));}
 private function keys(string $identifier,string $ip):array {
  $id=strtolower(trim($identifier));$q=$this->db->prepare('SELECT u.id FROM users u LEFT JOIN student_profiles p ON p.user_id=u.id WHERE LOWER(TRIM(u.email))=? OR LOWER(p.student_id)=? LIMIT 1');$q->execute([$id,$id]);$uid=$q->fetchColumn();
  return [SecurityCodeCipher::fingerprint('login-account:'.($uid?'user:'.$uid:$id))=>10,SecurityCodeCipher::fingerprint('login-peer:'.$ip)=>100];
 }
 public function reserve(string $identifier,string $ip):array {
  $this->db->exec("DELETE FROM auth_login_throttle WHERE updated_at<DATE_SUB(NOW(),INTERVAL 2 DAY) AND (locked_until IS NULL OR locked_until<NOW()) LIMIT 100");$keys=$this->keys($identifier,$ip);$ordered=$keys;ksort($ordered);$now=($this->clock)();$text=$now->format('Y-m-d H:i:s');
  try{$this->db->beginTransaction();$rows=[];
   foreach($ordered as $key=>$limit){$this->db->prepare('INSERT INTO auth_login_throttle(bucket_key,attempts,window_start,updated_at) VALUES(?,0,?,?) ON DUPLICATE KEY UPDATE bucket_key=bucket_key')->execute([$key,$text,$text]);$q=$this->db->prepare('SELECT * FROM auth_login_throttle WHERE bucket_key=? FOR UPDATE');$q->execute([$key]);$r=$q->fetch(PDO::FETCH_ASSOC);if($r['locked_until']&&$r['locked_until']>$text)throw new RuntimeException('Too many sign-in attempts. Please wait before trying again, or use password recovery.');if($r['window_start']<=$now->modify('-15 minutes')->format('Y-m-d H:i:s')){$r['attempts']=0;$r['window_start']=$text;}$rows[$key]=$r;}
   foreach($ordered as $key=>$limit){$r=$rows[$key];$attempts=(int)$r['attempts']+1;$until=$attempts>=$limit?$now->modify('+15 minutes')->format('Y-m-d H:i:s'):null;$this->db->prepare('UPDATE auth_login_throttle SET attempts=?,window_start=?,locked_until=?,updated_at=? WHERE bucket_key=?')->execute([$attempts,$r['window_start'],$until,$text,$key]);}
   $this->db->commit();return array_keys($keys);
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function success(array $keys):void {
  if(count($keys)!==2)throw new InvalidArgumentException('Invalid sign-in state.');
  $this->db->prepare('DELETE FROM auth_login_throttle WHERE bucket_key=?')->execute([$keys[0]]);$this->db->prepare('UPDATE auth_login_throttle SET attempts=GREATEST(0,attempts-1),locked_until=NULL WHERE bucket_key=? AND attempts<=100')->execute([$keys[1]]);
 }
}
