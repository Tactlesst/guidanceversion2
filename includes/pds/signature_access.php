<?php
final class PDSSignatureAccess {
    public static function read(PDO $db,int $actorId,string $key,?string $uploads=null): array {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new RuntimeException('Signature unavailable.');
        $q=$db->prepare('SELECT id,role,is_active FROM users WHERE id=? AND COALESCE(archived,0)=0');$q->execute([$actorId]);$actor=$q->fetch(PDO::FETCH_ASSOC);
        if(!$actor||!(int)$actor['is_active'])throw new RuntimeException('Signature unavailable.');
        $q=$db->prepare('SELECT user_id,student_signature,parent_signature FROM pds WHERE SHA2(student_signature,256)=? OR SHA2(parent_signature,256)=? LIMIT 1');$q->execute([$key,$key]);$record=$q->fetch(PDO::FETCH_ASSOC);
        // Historical signatures remain owner/admin protected after yearly renewal clears the working copy.
        if(!$record){
            $scope=in_array($actor['role'],['admin','guidance_advocate','super_admin'],true)?'':' AND user_id=?';$params=[$key,$key];if($scope!=='')$params[]=$actorId;
            $q=$db->prepare("SELECT user_id,JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,'$.pds.student_signature')) AS student_signature,JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,'$.pds.parent_signature')) AS parent_signature FROM pds_versions WHERE (SHA2(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,'$.pds.student_signature')),256)=? OR SHA2(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,'$.pds.parent_signature')),256)=?)".$scope.' ORDER BY id DESC LIMIT 1');$q->execute($params);$record=$q->fetch(PDO::FETCH_ASSOC);
        }
        if(!$record||((int)$record['user_id']!==$actorId&&!in_array($actor['role'],['admin','guidance_advocate','super_admin'],true)))throw new RuntimeException('Signature unavailable.');
        $value=hash('sha256',(string)$record['student_signature'])===$key?$record['student_signature']:$record['parent_signature'];
        if(preg_match('~^data:image/(png|jpeg|gif|webp);base64,([a-zA-Z0-9+/=\r\n]+)$~D',$value,$m)){$bytes=base64_decode($m[2],true);$mime='image/'.$m[1];}
        else{
            $path=str_replace('\\','/',$value);while(str_starts_with($path,'../'))$path=substr($path,3);if(!str_starts_with($path,'uploads/'))$path='uploads/pds_signatures/'.basename($path);
            $root=realpath($uploads??__DIR__.'/../../storage/uploads');$file=$root?realpath($root.'/'.substr($path,8)):false;
            if(!$root||!$file||!str_starts_with(strtolower($file),strtolower($root.DIRECTORY_SEPARATOR))||!is_file($file)||filesize($file)>2*1024*1024)throw new RuntimeException('Signature unavailable.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);$bytes=file_get_contents($file);
        }
        if(!$bytes||strlen($bytes)>2*1024*1024||!in_array($mime,['image/png','image/jpeg','image/gif','image/webp'],true)||!@getimagesizefromstring($bytes))throw new RuntimeException('Signature unavailable.');
        return ['mime'=>$mime,'bytes'=>$bytes];
    }
}
