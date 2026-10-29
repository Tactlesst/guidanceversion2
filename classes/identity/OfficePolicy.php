<?php
/** Assistant permissions shared by the portal and standalone controllers. */
final class OfficePolicy {
    public static function actor(PDO $db,int $id):array {
        $q=$db->prepare('SELECT id,role,is_active,archived FROM users WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r||!(int)$r['is_active']||(int)$r['archived'])throw new RuntimeException('Please sign in with an active account.');
        return $r;
    }
    public static function ownSession(PDO $db,int $uid,int $id):array {
        $q=$db->prepare('SELECT id,user_id,assigned_advocate_id,status FROM counseling_appointments WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r||(int)$r['assigned_advocate_id']!==$uid)throw new RuntimeException('You can work only on counseling sessions assigned to you.');return $r;
    }
    public static function enforce(PDO $db,array $actor,string $page,array $post,array $get=[]):void {
        if($actor['role']!=='guidance_advocate')return;
        if(in_array($page,['daily_limits','system_settings','user_management','academic_settings','backup_restore','system_logs','fill_pds','view_pds','counseling_history'],true))throw new RuntimeException('This action is reserved for the guidance counselor/admin.');
        if($page==='pds_entry')return;
        if(!$post)return;
        if($page==='student_records'&&isset($post['update_student_profile']))return;
        if($page==='release_results')return;
        if($page==='pds_history'&&($post['history_action']??'')==='print')return;
        if(in_array($page,['profile','notifications','approvals'],true))return;
        if(in_array($page,['reports','examinee_reports','ses_analytics','olsat_reports','counseling_reports','passed_examinees_report'],true)&&($post['report_action']??'')==='filters')return;
        if($page==='schedules'&&isset($post['create_schedule'])&&count(array_intersect(array_keys($post),['update_schedule','delete_schedule','set_daily_limit','remove_daily_limit','create_holiday','delete_holiday','create_school_closure','reopen_school_closure']))===0)return;
        if($page==='manage_announcements'&&($post['action']??'')==='create_announcement'){
            if(($post['target_audience']??'all_students')!=='all_students'||!empty($post['show_on_homepage'])||!empty($post['is_pinned']))throw new RuntimeException('Advocates can post student-only portal announcements. Public notices need the counselor/admin.');return;
        }
        if($page==='manage_counseling'){
            $id=filter_var($post['appointment_id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)throw new RuntimeException('Choose a valid counseling appointment.');
            if(isset($post['assign_counselor'])){
                if((int)($post['assigned_advocate_id']??0)!==(int)$actor['id'])throw new RuntimeException('You may assign an unassigned appointment only to yourself.');
                $q=$db->prepare('SELECT assigned_advocate_id,status FROM counseling_appointments WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
                if(!$r||!empty($r['assigned_advocate_id'])||!in_array($r['status'],['pending','confirmed'],true))throw new RuntimeException('This appointment is no longer available to claim.');return;
            }
            $r=self::ownSession($db,(int)$actor['id'],$id);
            $keys=array_intersect(array_keys($post),['start_session','complete_appointment','mark_missed','schedule_followup','save_counseling_form']);
            if(count($keys)!==1||isset($post['cancel_appointment'])||isset($post['reschedule_appointment']))throw new RuntimeException('Ask the guidance counselor/admin to change or cancel an existing appointment.');
            if(isset($post['save_counseling_form'])&&!empty($post['is_edit']))throw new RuntimeException('Existing counseling forms can be edited only by the guidance counselor/admin.');
            if(isset($post['start_session'])&&!in_array($r['status'],['pending','confirmed'],true))throw new RuntimeException('Only a scheduled session can be started.');
            if(isset($post['complete_appointment'])&&$r['status']!=='in_progress')throw new RuntimeException('Start your assigned session before completing it.');
            return;
        }
        throw new RuntimeException('This record is read-only for Guidance Advocates. Ask the guidance counselor/admin to make changes.');
    }
}
