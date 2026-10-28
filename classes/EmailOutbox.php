<?php
final class EmailOutbox {
    public static function enqueue(PDO $db,int $uid,string $email,string $subject,string $body,?string $key=null,?string $after=null,?int $notification=null):int {
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return 0;
        $q=$db->prepare("INSERT INTO email_outbox(user_id,notification_id,to_email,subject,body_html,status,dedupe_key,send_after,attempts,created_at) VALUES(?,?,?,?,?,'pending',?,?,0,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");$q->execute([$uid,$notification,$email,mb_substr(str_replace(["\r","\n"],' ',$subject),0,255),$body,$key,$after]);return (int)$db->lastInsertId();
    }
    public static function counts(PDO $db):array {$out=['pending'=>0,'processing'=>0,'sent'=>0,'failed'=>0];foreach($db->query('SELECT status,COUNT(*) AS total FROM email_outbox GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row)if(isset($out[$row['status']]))$out[$row['status']]=(int)$row['total'];return $out;}
}
