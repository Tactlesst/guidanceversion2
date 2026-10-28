<?php
/** Explicit public visibility; notification audiences remain independent. */
class AnnouncementPublication {
    private PDO $db;
    private string $today;
    public function __construct(PDO $db, ?string $today=null) {
        $this->db=$db;
        $this->today=$today??(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    }
    public static function expiry($value): ?string {
        if($value===null||$value==='')return null;
        if(!is_string($value))throw new InvalidArgumentException('Choose a valid expiry date.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$date||$date->format('Y-m-d')!==$value||$value<'1000-01-01')throw new InvalidArgumentException('Choose a valid expiry date.');
        return $value;
    }
    public static function theme($value): string {
        if(!is_string($value)||!in_array($value,['classic','blue-gold','teal-guidance','purple-academic','coral-events'],true))throw new InvalidArgumentException('Choose a valid announcement template.');
        return $value;
    }
    private function write(callable $operation) {
        // Serialize pin changes across admin sessions so only one notice stays pinned.
        $lock='announcements_publication_'.substr(hash('sha256',(string)$this->db->query('SELECT DATABASE()')->fetchColumn()),0,24);
        $q=$this->db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);
        if((int)$q->fetchColumn()!==1)throw new RuntimeException('Another announcement is being updated. Please try again.');
        try {
            $this->db->beginTransaction();
            $result=$operation();
            $this->db->commit();
            return $result;
        } catch(Throwable $error) {
            if($this->db->inTransaction())$this->db->rollBack();
            throw $error;
        } finally {
            $q=$this->db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);
        }
    }
    private function clearOtherPins(int $except=0): void {
        $q=$this->db->prepare('UPDATE announcements SET is_pinned=0,updated_at=updated_at WHERE is_pinned=1 AND id<>?');$q->execute([$except]);
    }
    /** A new announcement notifies its audience in one INSERT SELECT. Editing a featured notice does not resend it. */
    public function save(array $data,?int $id=null,bool $notify=true): array {
        $title=trim($data['title']??'');$message=trim($data['message']??'');$location=trim($data['location']??'');
        if($title===''||$message==='')throw new InvalidArgumentException('Title and message are required.');
        if(mb_strlen($title)>255||mb_strlen($location)>255)throw new InvalidArgumentException('Title and location must each be 255 characters or fewer.');
        $target=$data['target_audience']??'all_students';$type=$data['event_type']??'general';
        if(!in_array($target,['all_students','all_examinees','all_users'],true)||!in_array($type,['general','academic','guidance'],true))throw new InvalidArgumentException('Choose a valid category and audience.');
        $public=!empty($data['show_on_homepage']);$active=(int)($data['is_active']??1);$pinned=$public&&$active&&!empty($data['is_pinned']);$expires=self::expiry($data['expires_on']??null);$theme=self::theme($data['template_theme']??'classic');$updateTheme=array_key_exists('template_theme',$data)?$theme:null;
        return $this->write(function()use($data,$id,$notify,$title,$message,$location,$target,$type,$public,$active,$pinned,$expires,$theme,$updateTheme){
            if($id!==null){$q=$this->db->prepare('SELECT id FROM announcements WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('This announcement no longer exists.');}
            if($pinned)$this->clearOtherPins($id??0);
            if($id===null){
                $q=$this->db->prepare('INSERT INTO announcements(title,message,event_type,target_audience,event_date,event_time,location,is_active,created_by,show_on_homepage,is_pinned,expires_on,template_theme,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                $q->execute([$title,$message,$type,$target,($data['event_date']??'')?:null,($data['event_time']??'')?:null,$location,$active,(int)$data['created_by'],(int)$public,(int)$pinned,$expires,$theme]);
                $savedId=(int)$this->db->lastInsertId();
            }else{
                $q=$this->db->prepare('UPDATE announcements SET title=?,message=?,target_audience=?,is_active=?,show_on_homepage=?,is_pinned=?,expires_on=?,template_theme=COALESCE(?,template_theme),updated_at=NOW() WHERE id=?');
                $q->execute([$title,$message,$target,$active,(int)$public,(int)$pinned,$expires,$updateTheme,$id]);$savedId=$id;
            }
            $count=0;
            if($notify&&$id===null){
                $filter="role IN ('student','examinee') AND (archived=0 OR archived IS NULL)";$params=[$title,$message,$savedId];
                if($target!=='all_users'){$filter.=' AND role=?';$params[]=$target==='all_students'?'student':'examinee';}
                $q=$this->db->prepare("INSERT INTO notifications(user_id,title,message,type,related_table,related_id,created_at) SELECT id,?,?,'info','announcements',?,NOW() FROM users WHERE ".$filter);$q->execute($params);$count=$q->rowCount();
            }
            return ['id'=>$savedId,'notification_count'=>$count];
        });
    }
    /** Change display settings without changing the message, audience or sent notifications. */
    public function visibility(int $id,bool $public,bool $pinned,?string $expires,?string $theme=null): void {
        $expires=self::expiry($expires);$pinned=$public&&$pinned;if($theme!==null)$theme=self::theme($theme);
        $this->write(function()use($id,$public,$pinned,$expires,$theme){
            $q=$this->db->prepare('SELECT id FROM announcements WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('This announcement no longer exists.');
            if($pinned)$this->clearOtherPins($id);
            $q=$this->db->prepare('UPDATE announcements SET show_on_homepage=?,is_pinned=?,expires_on=?,template_theme=COALESCE(?,template_theme),updated_at=NOW() WHERE id=?');$q->execute([(int)$public,(int)$pinned,$expires,$theme,$id]);
        });
    }
    public function featured(): ?array {
        $q=$this->db->prepare('SELECT id,title,message,event_type,event_date,event_time,location,created_at,updated_at,expires_on,is_pinned,template_theme FROM announcements WHERE show_on_homepage=1 AND is_active=1 AND is_pinned=1 AND (expires_on IS NULL OR expires_on>=?) ORDER BY updated_at DESC,id DESC LIMIT 1');$q->execute([$this->today]);
        return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function latest(?int $featuredId=null): array {
        $q=$this->db->prepare('SELECT id,title,message,event_type,event_date,event_time,location,created_at,updated_at,expires_on,is_pinned,template_theme FROM announcements WHERE show_on_homepage=1 AND is_active=1 AND (expires_on IS NULL OR expires_on>=?) AND id<>? ORDER BY created_at DESC,id DESC LIMIT 6');$q->execute([$this->today,$featuredId??0]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
