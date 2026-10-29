<?php
/** Private photo delivery follows the same owner/staff permissions as the PDS. */
final class PDSPhotoAccess {
 public static function read(PDO $db,int $actor,string $key,?string $uploads=null):array {
  if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new RuntimeException('Photo unavailable.');
  $q=$db->prepare('SELECT role,is_active,archived FROM users WHERE id=?');$q->execute([$actor]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u||!(int)$u['is_active']||(int)$u['archived'])throw new RuntimeException('Photo unavailable.');
  $staff=in_array($u['role'],['admin','guidance_advocate','super_admin'],true);$ownerScope=$staff?'':' AND user_id=?';$args=[$key];if(!$staff)$args[]=$actor;
  $q=$db->prepare('SELECT user_id,student_photo FROM pds WHERE SHA2(student_photo,256)=?'.$ownerScope.' LIMIT 1');$q->execute($args);$row=$q->fetch(PDO::FETCH_ASSOC);
  if(!$row){$q=$db->prepare("SELECT user_id,JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,'$.pds.student_photo')) AS student_photo FROM pds_versions WHERE SHA2(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,'$.pds.student_photo')),256)=?".$ownerScope.' ORDER BY id DESC LIMIT 1');$q->execute($args);$row=$q->fetch(PDO::FETCH_ASSOC);}
  if(!$row)throw new RuntimeException('Photo unavailable.');$value=(string)$row['student_photo'];
  if(preg_match('~^data:image/(png|jpeg|gif|webp);base64,([a-zA-Z0-9+/=\r\n]+)$~D',$value,$m)){$bytes=base64_decode($m[2],true);$mime='image/'.$m[1];}
  else{$path=str_replace('\\','/',$value);while(str_starts_with($path,'../'))$path=substr($path,3);if(!str_starts_with($path,'uploads/'))$path='uploads/pds_photos/'.basename($path);$root=realpath($uploads??dirname(__DIR__,2).'/storage/uploads');$file=$root?realpath($root.'/'.substr($path,8)):false;if(!$root||!$file||!str_starts_with(strtolower($file),strtolower($root.DIRECTORY_SEPARATOR))||!is_file($file)||filesize($file)>2*1024*1024)throw new RuntimeException('Photo unavailable.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);$bytes=file_get_contents($file);}
  if(!$bytes||strlen($bytes)>2*1024*1024||!in_array($mime,['image/png','image/jpeg','image/gif','image/webp'],true)||!@getimagesizefromstring($bytes))throw new RuntimeException('Photo unavailable.');return ['mime'=>$mime,'bytes'=>$bytes];
 }
}
