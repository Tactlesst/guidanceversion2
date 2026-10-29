<?php
require_once __DIR__.'/editor_schema.php';
/** Append-only full-state versions; annual rows point to their year's latest preserved version. */
final class PDSHistory {
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    public static function now():string{return (new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');}
    public static function year($value):string {
        if(!is_string($value)||!preg_match('/^(20[0-9]{2})-(20[0-9]{2})$/D',$value,$m)||(int)$m[2]!==((int)$m[1]+1))throw new InvalidArgumentException('Choose a valid school year, such as 2026-2027.');return $value;
    }
    public static function authorize(PDO $db,int $actor,int $owner):array {
        $q=$db->prepare('SELECT id,role,is_active,COALESCE(archived,0) AS archived FROM users WHERE id=?');$q->execute([$actor]);$user=$q->fetch(PDO::FETCH_ASSOC);
        if(!$user||!(int)$user['is_active']||(int)$user['archived']||!(in_array($user['role'],['admin','guidance_advocate','super_admin'],true)||($actor===$owner&&in_array($user['role'],['student','examinee'],true))))throw new RuntimeException('You do not have permission to view this PDS history.');return $user;
    }
    public function context(int $uid):array {
        $q=$this->db->prepare('SELECT u.first_name AS account_first_name,u.middle_name AS account_middle_name,u.last_name AS account_last_name,u.email AS account_email,sp.student_id,sp.department AS profile_department,sp.grade_level AS profile_grade,sp.program AS profile_program,sp.strand AS profile_strand FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=?');$q->execute([$uid]);return $q->fetch(PDO::FETCH_ASSOC)?:[];
    }
    private static function normalized($value){
        if(!is_array($value))return $value===null||$value===''?null:(string)$value;
        $out=[];foreach($value as $key=>$part){if(in_array((string)$key,['id','user_id','created_at','updated_at'],true))continue;$out[$key]=self::normalized($part);}if($out!==[]&&array_keys($out)!==range(0,count($out)-1))ksort($out);return $out;
    }
    private static function flatten(array $state):array {
        $out=[];foreach($state['pds']??[] as $key=>$value){if(in_array($key,['id','user_id','created_at','updated_at'],true))continue;$out['pds.'.$key]=$value;}
        foreach(PDSEditorSchema::RELATED as $group=>$definition)foreach($state[$group]??[] as $index=>$row){$id=$row['id']??($index+1);foreach($definition['fields'] as $field=>$_)$out[$group.'.'.$id.'.'.$field]=$row[$field]??null;}
        foreach($state['context']??[] as $field=>$value)$out['school_record.'.$field]=$value;ksort($out);return $out;
    }
    public static function changes(array $before,array $after):array {
        $a=self::flatten($before);$b=self::flatten($after);$out=[];
        foreach(array_unique(array_merge(array_keys($a),array_keys($b))) as $key){$old=$a[$key]??null;$new=$b[$key]??null;if(self::normalized($old)===self::normalized($new))continue;
            if(in_array($key,['pds.student_signature','pds.parent_signature','pds.student_photo'],true)){$old=$old?'Recorded image ('.substr(hash('sha256',(string)$old),0,10).')':null;$new=$new?'Recorded image ('.substr(hash('sha256',(string)$new),0,10).')':null;}
            $out[$key]=['before'=>$old,'after'=>$new];
        }return $out;
    }
    public function latest(int $uid):?array {$q=$this->db->prepare('SELECT * FROM pds_versions WHERE user_id=? ORDER BY version_no DESC LIMIT 1');$q->execute([$uid]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
    public function annual(int $uid):array {$q=$this->db->prepare('SELECT a.*,v.version_no,v.event_type,v.captured_at FROM pds_annual_snapshots a JOIN pds_versions v ON v.id=a.version_id WHERE a.user_id=? ORDER BY a.school_year DESC');$q->execute([$uid]);return $q->fetchAll(PDO::FETCH_ASSOC);}
    private function actorLabel(?int $actor):string {
        if(!$actor)return 'Imported baseline';$q=$this->db->prepare('SELECT first_name,last_name,role FROM users WHERE id=?');$q->execute([$actor]);$r=$q->fetch(PDO::FETCH_ASSOC);return $r?mb_substr(trim($r['first_name'].' '.$r['last_name']).' ('.str_replace('_',' ',$r['role']).')',0,160):'PDS owner';
    }
    public function record(int $uid,array $state,string $event,?int $actor,bool $force=false):array {
        $state['context']=$this->context($uid);$last=$this->latest($uid);$prior=$last?json_decode($last['snapshot_json'],true,512,JSON_THROW_ON_ERROR):[];
        $changes=self::changes($prior,$state);if($last&&!$changes&&!$force)return $last;
        $year=trim((string)($state['pds']['school_year']??''));$number=$last?(int)$last['version_no']+1:1;$now=self::now();$label=$this->actorLabel($actor);
        $closed=$this->db->prepare('SELECT closed_at FROM pds_annual_snapshots WHERE user_id=? AND school_year=?');$closed->execute([$uid,$year]);if($closed->fetchColumn())throw new RuntimeException('This school-year PDS is closed. Start a later school year to save changes.');
        $snapshot=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);$diff=json_encode($changes,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);$previous=$last['content_hash']??null;
        $hash=hash('sha256',($previous??'')."\n".$uid."\n".$number."\n".$year."\n".$event."\n".$now."\n".$snapshot."\n".$diff);
        $q=$this->db->prepare('INSERT INTO pds_versions(user_id,pds_id,version_no,school_year,event_type,actor_id,actor_label,captured_at,snapshot_json,changes_json,previous_hash,content_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute([$uid,$state['pds']['id']??null,$number,$year,$event,$actor,$label,$now,$snapshot,$diff,$previous,$hash]);$id=(int)$this->db->lastInsertId();
        $q=$this->db->prepare('INSERT INTO pds_annual_snapshots(user_id,school_year,version_id,created_at,updated_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE version_id=IF(closed_at IS NULL,VALUES(version_id),version_id),updated_at=IF(closed_at IS NULL,VALUES(updated_at),updated_at),reviewed_at=IF(closed_at IS NULL,NULL,reviewed_at)');$q->execute([$uid,$year,$id,$now,$now]);
        return $this->latest($uid);
    }
    public function baseline(int $uid,array $state):void {if($state['pds']&&!$this->latest($uid))$this->record($uid,$state,'baseline',null);}
    public function versions(int $uid,string $year='',int $page=1):array {
        $where='user_id=?';$params=[$uid];if($year!==''){$where.=' AND school_year=?';$params[]=$year;}
        $q=$this->db->prepare('SELECT COUNT(*) FROM pds_versions WHERE '.$where);$q->execute($params);$total=(int)$q->fetchColumn();$page=min(max(1,$page),max(1,(int)ceil($total/10)));$offset=($page-1)*10;
        $q=$this->db->prepare('SELECT id,version_no,school_year,event_type,actor_label,captured_at,changes_json FROM pds_versions WHERE '.$where.' ORDER BY version_no DESC LIMIT 10 OFFSET '.$offset);$q->execute($params);return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/10))];
    }
    public function version(int $uid,int $version):array {
        $q=$this->db->prepare('SELECT * FROM pds_versions WHERE id=? AND user_id=?');$q->execute([$version,$uid]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('This PDS version is unavailable.');
        $hash=hash('sha256',($row['previous_hash']??'')."\n".$uid."\n".$row['version_no']."\n".$row['school_year']."\n".$row['event_type']."\n".$row['captured_at']."\n".$row['snapshot_json']."\n".$row['changes_json']);if(!hash_equals($row['content_hash'],$hash))throw new RuntimeException('This PDS version failed its integrity check.');
        $row['state']=json_decode($row['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$row['changes']=json_decode($row['changes_json'],true,512,JSON_THROW_ON_ERROR);return $row;
    }
    public static function validateAnnualReview(array $state):void {
        $p=$state['pds'];self::year($p['school_year']??'');if((int)($p['privacy_agreement']??0)!==1)throw new InvalidArgumentException('Agree to the privacy statement for this school year before completing the annual review.');
        if(empty($p['student_signature'])||empty($p['student_date_signed'])||empty($p['student_signature_name']))throw new InvalidArgumentException('Add your signature, printed name and signing date before completing the annual review.');
        $age=null;if(!empty($p['birth_date'])){$birth=DateTimeImmutable::createFromFormat('!Y-m-d',$p['birth_date'],new DateTimeZone('Asia/Manila'));$today=new DateTimeImmutable('today',new DateTimeZone('Asia/Manila'));if($birth&&$birth->format('Y-m-d')===$p['birth_date']&&$birth<=$today)$age=$birth->diff($today)->y;}elseif(isset($p['age'])&&$p['age']!==''&&ctype_digit((string)$p['age'])&&(int)$p['age']<=150)$age=(int)$p['age'];
        if($age===null)throw new InvalidArgumentException('Enter your date of birth or age so the annual signature requirements can be checked.');
        if($age<18&&(empty($p['parent_signature'])||empty($p['parent_date_signed'])||empty($p['parent_signature_name'])))throw new InvalidArgumentException('A parent or guardian signature, printed name and date are required for students below 18.');
    }
    public function reviewComplete(int $uid,string $year):bool {$q=$this->db->prepare('SELECT reviewed_at FROM pds_annual_snapshots WHERE user_id=? AND school_year=?');$q->execute([$uid,$year]);return (bool)$q->fetchColumn();}
    public function reviewed(int $uid,string $year):void {$this->db->prepare('UPDATE pds_annual_snapshots SET reviewed_at=COALESCE(reviewed_at,?) WHERE user_id=? AND school_year=? AND closed_at IS NULL')->execute([self::now(),$uid,$year]);}
    public function renew(int $uid,string $year,string $revision):void {
        self::year($year);require_once __DIR__.'/editor_store.php';$store=new PDSEditorStore($this->db);$owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();else $this->db->exec('SAVEPOINT pds_renewal');
        try{$before=$store->load($uid,true);if(!$before['pds'])throw new RuntimeException('Save your first PDS before starting a new school year.');if(!hash_equals(PDSEditorStore::revision($before),$revision))throw new RuntimeException('Your PDS changed after this page opened. Reload before starting a new school year.');
            $old=trim((string)($before['pds']['school_year']??''));if($old!==''&&strcmp($year,$old)<=0)throw new InvalidArgumentException('Choose a school year later than your current record.');
            $q=$this->db->prepare('SELECT version_id FROM pds_annual_snapshots WHERE user_id=? AND school_year=?');$q->execute([$uid,$year]);if($q->fetchColumn())throw new RuntimeException('A PDS record already exists for that school year.');
            $this->baseline($uid,$before);$this->record($uid,$before,'before_renewal',$uid);
            $this->db->prepare('UPDATE pds_annual_snapshots SET closed_at=? WHERE user_id=? AND school_year=?')->execute([self::now(),$uid,$old]);
            $this->db->prepare('UPDATE pds SET school_year=?,student_signature=NULL,parent_signature=NULL,student_signature_name=NULL,parent_signature_name=NULL,student_date_signed=NULL,parent_date_signed=NULL,privacy_agreement=0,privacy_agreement_date=NULL WHERE user_id=?')->execute([$year,$uid]);
            $this->record($uid,$store->load($uid),'new_school_year',$uid,true);if($owns)$this->db->commit();else $this->db->exec('RELEASE SAVEPOINT pds_renewal');
        }catch(Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();elseif(!$owns)$this->db->exec('ROLLBACK TO SAVEPOINT pds_renewal');throw $e;}
    }
}
