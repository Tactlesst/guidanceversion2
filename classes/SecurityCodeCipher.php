<?php
final class SecurityCodeCipher {
 private static function key():string {
  $env=getenv('GUIDANCE_SECURITY_KEY');$local=$env?null:(is_file(__DIR__.'/../config/security.local.php')?require __DIR__.'/../config/security.local.php':[]);$key=base64_decode($env?:($local['key']??''),true);
  if($key===false||strlen($key)!==32)throw new RuntimeException('Account verification is not configured. Contact the Guidance Office.');return $key;
 }
 public static function seal(string $plain):string {$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,'srcb-security-mail-v1');if($cipher===false)throw new RuntimeException('Verification email could not be prepared.');return 'enc:v1:'.base64_encode($iv.$tag.$cipher);}
 public static function open(string $value):string {if(!str_starts_with($value,'enc:v1:'))throw new RuntimeException('Verification email payload is invalid.');$raw=base64_decode(substr($value,7),true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Verification email payload is invalid.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'srcb-security-mail-v1');if($plain===false)throw new RuntimeException('Verification email payload is invalid.');return $plain;}
 public static function fingerprint(string $value):string {return hash_hmac('sha256',$value,self::key());}
}
