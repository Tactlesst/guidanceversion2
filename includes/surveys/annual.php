<?php
require_once __DIR__.'/assignment.php';
/** School year comes only from the student's saved PDS; no calendar-year guessing. */
final class SurveyAnnual {
    public static function table(string $kind):string {
        $map=['mi'=>'multiple_intelligence_survey','ls'=>'learning_style_inventory','ls_intermediate'=>'learning_style_inventory_intermediate'];if(!isset($map[$kind]))throw new InvalidArgumentException('Choose a valid survey.');return $map[$kind];
    }
    public static function validYear($year):bool{return is_string($year)&&preg_match('/^(20\d{2})-(20\d{2})$/D',$year,$m)&&(int)$m[2]===(int)$m[1]+1;}
    public static function year(PDO $db,int $uid,bool $lock=false):string {
        $q=$db->prepare('SELECT school_year FROM pds WHERE user_id=? LIMIT 1'.($lock?' FOR UPDATE':''));$q->execute([$uid]);$year=$q->fetchColumn();return self::validYear($year)?$year:'';
    }
    public static function record(PDO $db,int $uid,string $kind,string $year,bool $lock=false):?array {
        $q=$db->prepare('SELECT * FROM '.self::table($kind).' WHERE user_id=? AND school_year=? LIMIT 1'.($lock?' FOR UPDATE':''));$q->execute([$uid,$year]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public static function context(PDO $db,int $uid,bool $lock=false):array {
        $q=$db->prepare('SELECT u.id AS user_id,u.role,u.is_active,COALESCE(u.archived,0) AS archived,u.first_name,u.middle_name,u.last_name,u.email,sp.student_id,sp.department,sp.grade_level,sp.program,sp.strand FROM users u JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=?'.($lock?' FOR UPDATE':''));$q->execute([$uid]);return $q->fetch(PDO::FETCH_ASSOC)?:[];
    }
    public static function writable(PDO $db,int $uid,string $kind,$requestedYear):array {
        // The PDS row lock serializes survey saves with annual PDS renewal.
        $year=self::year($db,$uid,true);if($year==='')throw new RuntimeException('Save a valid school year in your PDS before taking the yearly survey.');
        if(!is_string($requestedYear)||!hash_equals($year,$requestedYear))throw new RuntimeException('Your PDS school year changed. Reload the survey before submitting. Previous years are read-only.');
        $q=$db->prepare('SELECT closed_at FROM pds_annual_snapshots WHERE user_id=? AND school_year=?');$q->execute([$uid,$year]);if($q->fetchColumn())throw new RuntimeException('This school year is closed. Start your new PDS year before taking the survey.');
        $context=self::context($db,$uid,true);if(!$context||$context['role']!=='student'||!(int)$context['is_active']||(int)$context['archived']||(studentSurveyAssignment($context)['kind']??'')!==$kind)throw new RuntimeException('This survey is not assigned to your current student profile.');
        unset($context['role'],$context['is_active'],$context['archived']);return ['year'=>$year,'context'=>$context];
    }
    public static function history(PDO $db,int $uid,string $year='',int $page=1):array {
        $parts=[];foreach(['mi','ls','ls_intermediate'] as $kind)$parts[]="SELECT id,'$kind' AS kind,school_year,completed_at FROM ".self::table($kind).' WHERE user_id=?';
        $sql=' FROM ('.implode(' UNION ALL ',$parts).') yearly_surveys';$params=[$uid,$uid,$uid];if($year!==''){$sql.=' WHERE school_year=?';$params[]=$year==='legacy'?'':$year;}
        $q=$db->prepare('SELECT COUNT(*)'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/10));$page=min($pages,max(1,$page));$q=$db->prepare('SELECT *'.$sql.' ORDER BY school_year DESC,completed_at DESC,id DESC LIMIT 10 OFFSET '.(($page-1)*10));$q->execute($params);
        $years=$db->prepare('SELECT DISTINCT school_year FROM ('.implode(' UNION ALL ',$parts).') yearly_surveys ORDER BY school_year DESC');$years->execute([$uid,$uid,$uid]);return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages,'years'=>$years->fetchAll(PDO::FETCH_COLUMN)];
    }
    public static function notice(string $year):string {
        $e=static fn($v)=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        return '<link rel="stylesheet" href="../assets/css/survey-annual.css?v=20261029-1"><aside class="sa-year-bar"><div><strong>'.($year!==''?'PDS school year '.$e($year):'A PDS school year is needed').'</strong><span>Each school year keeps its own results. Previous years stay read-only.</span></div><a href="layout.php?page=survey_history">View survey history</a></aside>';
    }
}
