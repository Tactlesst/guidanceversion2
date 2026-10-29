<?php
require_once __DIR__.'/Notification.php';require_once __DIR__.'/EmailOutbox.php';require_once __DIR__.'/MailTemplate.php';
class NotificationService {
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    // Persist immediately; SMTP runs only in the worker, never during a submission.
    public function notify($user_id,$title,$message,$type='info',$related_table=null,$related_id=null,$send_email=false,$email_subject=null,$email_body_html=null,$dedupe_key=null,$send_after=null,$to_email_override=null):bool {
        $owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();else $this->db->exec('SAVEPOINT notification_save');
        try{$q=$this->db->prepare('SELECT email FROM users WHERE id=? AND is_active=1 AND COALESCE(archived,0)=0');$q->execute([(int)$user_id]);$user=$q->fetch(PDO::FETCH_ASSOC);if(!$user){if($owns)$this->db->commit();else $this->db->exec('RELEASE SAVEPOINT notification_save');return true;}
            $created=(new Notification($this->db))->createNotification($user_id,$title,$message,$type,$related_table,$related_id,$dedupe_key);if(!$created)throw new RuntimeException('The notification could not be recorded.');$notification=(int)$this->db->lastInsertId();
            if($send_email){$email=$to_email_override?:($user['email']??'');$key='notification:'.$notification;EmailOutbox::enqueue($this->db,(int)$user_id,(string)$email,(string)($email_subject?:$title),(string)($email_body_html?:$this->defaultEmailBody($title,$message,$related_table)),$key,$send_after,$notification);}
            if($owns)$this->db->commit();else $this->db->exec('RELEASE SAVEPOINT notification_save');return true;
        }catch(Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();elseif(!$owns)$this->db->exec('ROLLBACK TO SAVEPOINT notification_save');error_log('Notification delivery could not be queued.');return false;}
    }
    public function staff(array $roles,string $title,string $message,string $relatedTable,int $relatedId,string $event,bool $email=true):void {
        $roles=array_values(array_intersect($roles,['admin','guidance_advocate']));if(!$roles)return;$marks=implode(',',array_fill(0,count($roles),'?'));
        $q=$this->db->prepare("INSERT INTO notifications(user_id,title,message,type,related_table,related_id,is_read,dedupe_key,created_at) SELECT id,?,?,'info',?,?,0,?,NOW() FROM users WHERE role IN ($marks) AND is_active=1 AND COALESCE(archived,0)=0 ON DUPLICATE KEY UPDATE id=notifications.id");$q->execute([mb_substr(Notification::text($title),0,100),Notification::text($message),$relatedTable,$relatedId,Notification::key($event),...$roles]);
        if($email){$body=MailTemplate::render($title,$message,$relatedTable);$q=$this->db->prepare("INSERT INTO email_outbox(user_id,notification_id,to_email,subject,body_html,status,dedupe_key,attempts,created_at) SELECT u.id,n.id,u.email,?,?,'pending',CONCAT('notification:',n.id),0,NOW() FROM notifications n JOIN users u ON u.id=n.user_id WHERE n.dedupe_key=? AND u.role IN ($marks) AND u.is_active=1 AND COALESCE(u.archived,0)=0 AND u.email REGEXP '^[^[:space:]@]+@[^[:space:]@]+[.][^[:space:]@]+$' ON DUPLICATE KEY UPDATE id=email_outbox.id");$q->execute([$title,$body,Notification::key($event),...$roles]);}
    }
    public function defaultEmailBody($title,$message,?string $relatedTable=null):string{return MailTemplate::render(Notification::text($title),Notification::text($message),$relatedTable);}
}
