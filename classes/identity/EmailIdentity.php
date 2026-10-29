<?php
final class EmailIdentity {
 public static function normalize($email,bool $required=true):?string {
  if(!is_string($email))throw new InvalidArgumentException('Enter a valid email address.');$email=strtolower(trim($email));
  if($email===''){if($required)throw new InvalidArgumentException('An email address is required.');return null;}
  if(strlen($email)>100||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address, up to 100 characters.');return $email;
 }
 public static function available(PDO $db,string $email,?int $except=null):bool {
  $q=$db->prepare("SELECT id FROM users WHERE LOWER(TRIM(email))=?".($except!==null?' AND id<>?':'').' LIMIT 1');$q->execute($except!==null?[$email,$except]:[$email]);return !$q->fetchColumn();
 }
 public static function requireAvailable(PDO $db,string $email,?int $except=null):void {if(!self::available($db,$email,$except))throw new InvalidArgumentException('This email address is already used by another account.');}
}
