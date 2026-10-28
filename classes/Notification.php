<?php
class Notification {
    private PDO $conn;
    public $id,$user_id,$title,$message,$type,$is_read,$related_table,$related_id;
    public const TYPES=['info','success','warning','error','appointment_confirmed','appointment_cancelled','appointment_rescheduled','appointment_completed','appointment_missed','exam_missed','advocate_assignment','appointment_cancelled_by_student'];
    public function __construct(PDO $db){$this->conn=$db;}
    public static function text($value):string{return trim(html_entity_decode(strip_tags((string)($value??'')),ENT_QUOTES|ENT_HTML5,'UTF-8'));}
    public static function key(?string $key):?string {if($key===null||$key==='')return null;return strlen($key)>191?hash('sha256',$key):$key;}
    public function create($user_id=null,$title=null,$message=null,$type='info',$related_table=null,$related_id=null):bool{return $user_id!==null?$this->createNotification($user_id,$title,$message,$type,$related_table,$related_id):$this->createNotification($this->user_id,$this->title,$this->message,$this->type?:'info',$this->related_table,$this->related_id);}
    public function createNotification($user_id,$title,$message,$type='info',$related_table=null,$related_id=null,$dedupe_key=null):bool {
        try{$q=$this->conn->prepare('INSERT INTO notifications(user_id,title,message,type,related_table,related_id,is_read,dedupe_key,created_at) VALUES(?,?,?,?,?,?,0,?,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');return $q->execute([(int)$user_id,mb_substr(self::text($title),0,100),self::text($message),in_array($type,self::TYPES,true)?$type:'info',$related_table,$related_id,self::key($dedupe_key)]);}catch(Throwable $e){error_log('A notification could not be saved.');return false;}
    }
    public function getUnreadCount($user_id):int{$q=$this->conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND COALESCE(is_read,0)=0');$q->execute([(int)$user_id]);return (int)$q->fetchColumn();}
    public function getByUserId($user_id,$limit=10):PDOStatement{$limit=min(50,max(1,(int)$limit));$q=$this->conn->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT '.$limit);$q->execute([(int)$user_id]);return $q;}
}
