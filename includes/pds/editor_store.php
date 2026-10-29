<?php
require_once __DIR__.'/../../classes/notifications/SubmissionNotifications.php';require_once __DIR__.'/history.php';require_once __DIR__.'/editor_schema.php';require_once __DIR__.'/address.php';require_once __DIR__.'/drawn_signature.php';
final class PDSEditorStore {
    private PDO $db;private string $uploads;private array $created=[];
    public function __construct(PDO $db,?string $uploads=null){$this->db=$db;$this->uploads=$uploads??dirname(__DIR__,2).'/storage/uploads';}
    public function load(int $uid,bool $lock=false): array {
        $s=$this->db->prepare('SELECT * FROM pds WHERE user_id=? LIMIT 1'.($lock?' FOR UPDATE':''));$s->execute([$uid]);$result=['pds'=>$s->fetch(PDO::FETCH_ASSOC)?:[]];
        foreach(PDSEditorSchema::RELATED as $key=>$definition){$s=$this->db->prepare('SELECT * FROM '.$definition['table'].' WHERE user_id=? ORDER BY id'.($lock?' FOR UPDATE':''));$s->execute([$uid]);$result[$key]=$s->fetchAll(PDO::FETCH_ASSOC);}
        return $result;
    }
    public static function revision(array $state): string {return hash('sha256',json_encode($state,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));}
    private static function scalar($value,string $label): string {if(!is_scalar($value)&&$value!==null)throw new InvalidArgumentException('Invalid '.$label.'.');return trim((string)($value??''));}
    private static function date(string $value,string $label): ?string {
        if($value==='')return null;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Enter a valid date for '.$label.'.');return $value;
    }
    public static function validateImage(string $path,int $size): string {
        if($size<1||$size>2*1024*1024)throw new InvalidArgumentException('Images must be no larger than 2 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$image=@getimagesize($path);
        if(!isset($allowed[$mime])||!$image||($image['mime']??'')!==$mime||$image[0]>8000||$image[1]>8000||$image[0]*$image[1]>25000000)throw new InvalidArgumentException('Choose a valid JPG, PNG or WebP image.');
        return $allowed[$mime];
    }
    private function upload(array $file,string $kind,int $uid): string {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('The '.$kind.' image could not upload. Choose it again.');
        if(!is_uploaded_file($file['tmp_name']??''))throw new InvalidArgumentException('Invalid image upload.');
        $extension=self::validateImage($file['tmp_name'],(int)$file['size']);$folder=$kind==='photo'?'pds_photos':'pds_signatures';$directory=$this->uploads.'/'.$folder;
        if(!is_dir($directory)&&!mkdir($directory,0775,true))throw new RuntimeException('The image folder is unavailable.');
        $name=$uid.'_'.bin2hex(random_bytes(16)).'.'.$extension;$destination=$directory.'/'.$name;
        if(!move_uploaded_file($file['tmp_name'],$destination))throw new RuntimeException('The image could not be saved.');$this->created[]=$destination;
        return 'uploads/'.$folder.'/'.$name;
    }
    public function save(int $uid,string $education,array $post,array $files,string $expectedRevision,?int $staffActor=null): array {
        $owned=!$this->db->inTransaction();$this->created=[];
        if($owned)$this->db->beginTransaction();else $this->db->exec('SAVEPOINT pds_editor_save');
        try {
            $this->db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE')->execute([$uid]);
            if($staffActor!==null){require_once __DIR__.'/../../classes/records/StaffPDSEntry.php';StaffPDSEntry::context($this->db,$staffActor,$uid);if(($post['_paper_source_confirm']??'')!=='1')throw new InvalidArgumentException('Confirm that this entry is copied from the student’s authorized paper form.');if(($post['_pds_finalize']??'')==='1')throw new InvalidArgumentException('Paper entry cannot submit the student’s own online annual review.');foreach(['student_signature','parent_signature'] as $signature)if(!empty($post[$signature.'_drawn']))throw new InvalidArgumentException('Upload the photographed paper signature; staff cannot draw a signature for a student or guardian.');}
            $before=$this->load($uid,true);
            if(!hash_equals(self::revision($before),$expectedRevision))throw new RuntimeException('This PDS changed after you opened it. Reload the form before saving so existing changes are kept.');
            $history=new PDSHistory($this->db);if($staffActor!==null){PDSHistory::year($post['school_year']??'');foreach(['first_name','last_name'] as $requiredName)if(self::scalar($post[$requiredName]??'', $requiredName)==='')throw new InvalidArgumentException('Enter the student’s given name and last name from the paper.');}
            if(array_key_exists('school_year',$post))$post['school_year']=self::scalar($post['school_year'],'school year');
            if($before['pds']&&array_key_exists('school_year',$post)&&$post['school_year']!==(string)($before['pds']['school_year']??''))throw new RuntimeException('Use School years & history to start a new school year. This preserves the previous record and requires fresh consent and signatures.');
            if(!empty($post['school_year']))PDSHistory::year($post['school_year']);
            $history->baseline($uid,$before);$priorVersion=$history->latest($uid);
            $post=PDSAddress::apply($post);
            if(isset($post['languages_spoken'])&&is_array($post['languages_spoken'])){
                $languages=[];foreach($post['languages_spoken'] as $token){$token=self::scalar($token,'language');if($token==='Others'&&trim(self::scalar($post['languages_spoken_other']??'','other language'))!=='')$token='Others: '.trim($post['languages_spoken_other']);if($token!=='')$languages[]=$token;}$post['languages_spoken']=$languages;
            }
            $columns=[];foreach($this->db->query('SHOW COLUMNS FROM pds')->fetchAll(PDO::FETCH_ASSOC) as $column)$columns[$column['Field']]=$column['Type'];
            foreach(['father','mother'] as $parent)if(($post[$parent.'_education']??'')==='Others'&&trim(self::scalar($post[$parent.'_education_other']??'','other education'))!=='')$post[$parent.'_education']='Others: '.trim($post[$parent.'_education_other']);
            if(($post['religion']??'')==='Others'&&trim(self::scalar($post['religion_other']??'','other religion'))!=='')$post['religion']='Others: '.trim($post['religion_other']);
            $values=['education_level'=>$education];
            foreach(explode(' ',PDSEditorSchema::FIELDS) as $field){
                if(!array_key_exists($field,$post))continue;
                if(!isset($columns[$field]))throw new RuntimeException('The PDS field update has not been installed completely.');
                if(in_array($field,PDSEditorSchema::MULTIPLE,true)){$raw=$post[$field];if(!is_array($raw))$raw=[$raw];$tokens=[];foreach($raw as $part){$part=self::scalar($part,$field);if($part!=='')$tokens[]=$part;}$value=implode(', ',array_unique($tokens));}
                else $value=self::scalar($post[$field],str_replace('_',' ',$field));
                $label=str_replace('_',' ',$field);
                if(str_contains($columns[$field],'int')){if($value==='')$value=null;elseif(!ctype_digit($value)||(int)$value>($field==='age'?150:9999))throw new InvalidArgumentException('Enter a valid nonnegative number for '.$label.'.');else $value=(int)$value;}
                elseif($columns[$field]==='date')$value=self::date($value,$label);
                elseif(preg_match('/varchar\((\d+)\)/i',$columns[$field],$length)&&mb_strlen($value)>(int)$length[1])throw new InvalidArgumentException(ucfirst($label).' is too long (maximum '.$length[1].' characters).');
                elseif(mb_strlen($value)>6000)throw new InvalidArgumentException(ucfirst($label).' is too long.');
                if($field==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address.');
                $values[$field]=$value===''?null:$value;
            }
            if($staffActor!==null)foreach(['student_date_signed','parent_date_signed'] as $paperSigningDate)if(!empty($values[$paperSigningDate])&&$values[$paperSigningDate]>date('Y-m-d'))throw new InvalidArgumentException('The paper signing date cannot be in the future.');
            if(array_key_exists('privacy_agreement',$post)){
                if(!in_array($values['privacy_agreement'],[0,1],true))throw new InvalidArgumentException('Choose whether you agree to the privacy statement.');
                $values['privacy_agreement']=($values['privacy_agreement']??0)===1?1:0;
                $values['privacy_agreement_date']=$values['privacy_agreement']?((($before['pds']['privacy_agreement']??0)==1&& !empty($before['pds']['privacy_agreement_date']))?$before['pds']['privacy_agreement_date']:date('Y-m-d H:i:s')):null;
                if($staffActor!==null){$paperDate=self::date(self::scalar($post['_paper_consent_date']??'','paper consent date'),'paper consent date');if($paperDate!==null&&$paperDate>date('Y-m-d'))throw new InvalidArgumentException('The paper consent date cannot be in the future.');$values['privacy_agreement_date']=$values['privacy_agreement']&&$paperDate?$paperDate.' 00:00:00':null;}
            }
            foreach(['student_signature','parent_signature'] as $field){
                $drawn=self::scalar($post[$field.'_drawn']??'','signature drawing');$hasUpload=isset($files[$field])&&($files[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
                if(($drawn!==''||$hasUpload)&&($post[$field.'_confirm']??'')!=='1')throw new InvalidArgumentException('The signer must explicitly authorize the new signature before it can be applied.');
                if($drawn!==''&&$hasUpload)throw new InvalidArgumentException('Choose either a drawn signature or an uploaded signature, not both.');
                if($drawn!=='')$values[$field]=PDSDrawnSignature::save($drawn,$this->uploads,$uid,function($path){$this->created[]=$path;});
                if($drawn!==''||$hasUpload){$prefix=$field==='student_signature'?'student':'parent';$values[$prefix.'_date_signed']=$staffActor===null?(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d'):self::date(self::scalar($post[$prefix.'_date_signed']??'','paper signing date'),'paper signing date');if($staffActor!==null&&$values[$prefix.'_date_signed']!==null&&$values[$prefix.'_date_signed']>date('Y-m-d'))throw new InvalidArgumentException('The paper signing date cannot be in the future.');}
            }
            foreach(['student_photo'=>'photo','student_signature'=>'student signature','parent_signature'=>'parent signature'] as $field=>$kind){
                if(!empty($post['remove_'.$field])&&empty($post[$field.'_drawn']))$values[$field]=null;
                if(isset($files[$field])&&($files[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$values[$field]=$this->upload($files[$field],$kind,$uid);
            }
            if($before['pds']){$set=implode(',',array_map(static fn($field)=>"`$field`=?",array_keys($values)));$statement=$this->db->prepare("UPDATE pds SET $set WHERE user_id=?");$statement->execute([...array_values($values),$uid]);}
            else {$values['user_id']=$uid;$fields=implode(',',array_map(static fn($field)=>"`$field`",array_keys($values)));$marks=implode(',',array_fill(0,count($values),'?'));$this->db->prepare("INSERT INTO pds ($fields) VALUES ($marks)")->execute(array_values($values));}
            foreach(PDSEditorSchema::RELATED as $group=>$definition)if(array_key_exists($group,$post)||array_key_exists('removed_'.$group,$post))$this->saveRelated($uid,$group,$definition,$post,$before[$group]);
            $after=$this->load($uid);
            $annualReview=$staffActor===null?(($post['_pds_finalize']??'')==='1'):(($post['_paper_finalize']??'')==='1');
            if($annualReview)PDSHistory::validateAnnualReview($after);
            $version=$history->record($uid,$after,$staffActor!==null?'created':($annualReview?'annual_review':($before['pds']?'updated':'created')),$staffActor??$uid,$annualReview&&!$history->reviewComplete($uid,(string)$after['pds']['school_year']));
            if((int)($priorVersion['id']??0)!==(int)$version['id']){if($staffActor===null)SubmissionNotifications::pds($this->db,$uid,(int)$version['id'],(string)($after['pds']['school_year']??''),$annualReview);else{SubmissionNotifications::paperPds($this->db,$uid,(int)$version['id']);require_once __DIR__.'/../../classes/system/StaffActivity.php';if(!(new StaffActivity($this->db))->record($staffActor,'pds_entry','create_paper_pds'))throw new RuntimeException('The paper-entry audit could not be saved.');}}
            if($annualReview)$history->reviewed($uid,(string)$after['pds']['school_year']);
            if($owned)$this->db->commit();else $this->db->exec('RELEASE SAVEPOINT pds_editor_save');return $after;
        }catch(Throwable $error){
            if($owned&&$this->db->inTransaction())$this->db->rollBack();elseif(!$owned)$this->db->exec('ROLLBACK TO SAVEPOINT pds_editor_save');
            foreach($this->created as $path)if(is_file($path))unlink($path);throw $error;
        }
    }
    private function saveRelated(int $uid,string $group,array $definition,array $post,array $existing): void {
        $known=array_column($existing,null,'id');$seen=[];$table=$definition['table'];$rows=$post[$group]??[];
        if(!is_array($rows)||count($rows)>100)throw new InvalidArgumentException('Use at most 100 '.$group.' rows.');
        $removed=$post['removed_'.$group]??[];if(!is_array($removed))throw new InvalidArgumentException('Invalid removed rows.');
        foreach($removed as $id){$id=(int)self::scalar($id,$group);if(!isset($known[$id]))throw new InvalidArgumentException('A removed row does not belong to this PDS.');$this->db->prepare("DELETE FROM $table WHERE id=? AND user_id=?")->execute([$id,$uid]);}
        foreach($rows as $row){
            if(!is_array($row))throw new InvalidArgumentException('Invalid '.$group.' row.');$id=(int)self::scalar($row['id']??0,$group);
            if($id&&(!isset($known[$id])||isset($seen[$id])||in_array((string)$id,array_map('strval',$removed),true)))throw new InvalidArgumentException('A related row is invalid for this PDS.');$seen[$id]=true;$values=[];
            foreach($definition['fields'] as $field=>$limit){if(!array_key_exists($field,$row))continue;$value=self::scalar($row[$field],$field);
                if($field==='sibling_age'){if($value!==''&&(!ctype_digit($value)||(int)$value>150))throw new InvalidArgumentException('Enter a sibling age between 0 and 150.');}
                elseif($field==='test_date')$value=self::date($value,'test date');
                elseif(mb_strlen($value)>$limit)throw new InvalidArgumentException('A '.$group.' field is too long.');$values[$field]=$value===''?null:$value;
            }
            if(!array_filter($values,static fn($v)=>$v!==null&&$v!=='')){
                if($id&&count($values)===count($definition['fields']))throw new InvalidArgumentException('A saved '.$group.' row is blank. Enter its name or use Remove before saving.');
                continue;
            }
            $nameField=match($group){'siblings'=>'sibling_name','organizations'=>'organization_name','tests'=>'test_name'};
            if((array_key_exists($nameField,$values)&&empty($values[$nameField]))||(!array_key_exists($nameField,$values)&&empty($known[$id][$nameField])))throw new InvalidArgumentException('Enter a name for each '.$group.' row, or remove the row.');
            if($id){$set=implode(',',array_map(static fn($field)=>"`$field`=?",array_keys($values)));$this->db->prepare("UPDATE $table SET $set WHERE id=? AND user_id=?")->execute([...array_values($values),$id,$uid]);}
            else {$values['user_id']=$uid;$fields=implode(',',array_keys($values));$marks=implode(',',array_fill(0,count($values),'?'));$this->db->prepare("INSERT INTO $table ($fields) VALUES ($marks)")->execute(array_values($values));}
        }
    }
}
