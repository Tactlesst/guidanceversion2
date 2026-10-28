<?php
require_once __DIR__.'/survey_annual.php';require_once __DIR__.'/../classes/SubmissionNotifications.php';
/** Save real checked statements without inferring answers from aggregate scores. */
final class MISurveyStore {
    public static function revision(?array $record):string {return hash('sha256',json_encode($record,JSON_INVALID_UTF8_SUBSTITUTE));}
    public static function responses(?array $record,array $sections):array {
        $out=[];foreach($sections as $number=>$section){$out[$number]=[];foreach(explode(',',(string)($record['section'.$number.'_responses']??'')) as $value)if(ctype_digit($value)&&(int)$value>=1&&(int)$value<=count($section['questions']))$out[$number][]=(int)$value;$out[$number]=array_values(array_unique($out[$number]));}return $out;
    }
    public static function save(PDO $db,string $studentId,array $sections,array $post,string $revision,int $uid):void {
        $responses=[];$values=[];$fields=[];$total=0;
        foreach($sections as $number=>$section){
            $raw=$post['section'.$number]??[];if(!is_array($raw)||count($raw)>count($section['questions']))throw new InvalidArgumentException('Check the selected statements and try again.');
            $answers=[];foreach($raw as $value){if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1||(int)$value>count($section['questions']))throw new InvalidArgumentException('A selected statement is invalid. Reload the survey and try again.');$answers[]=(int)$value;}
            $answers=array_values(array_unique($answers));sort($answers);$responses[$number]=$answers;$total+=count($answers);
            if(($post['reviewed'.$number]??'')!=='1')throw new InvalidArgumentException('Review each section before submitting, including any section where no statements apply.');
            $fields[]='section'.$number.'_score';$values[]=count($answers);$fields[]='section'.$number.'_responses';$values[]=implode(',',$answers);
        }
        if(!$total)throw new InvalidArgumentException('Select at least one statement that describes you before submitting.');
        if(($post['confirm_answers']??'')!=='1')throw new InvalidArgumentException('Confirm that you reviewed your answers before submitting.');
        $owns=!$db->inTransaction();if($owns)$db->beginTransaction();else $db->exec('SAVEPOINT mi_survey_save');
        try{
            $annual=SurveyAnnual::writable($db,$uid,'mi',$post['_survey_year']??null);if((string)($annual['context']['student_id']??'')!==$studentId)throw new RuntimeException('Your student ID changed. Reload the survey before submitting.');$context=json_encode($annual['context'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $q=$db->prepare('SELECT * FROM multiple_intelligence_survey WHERE user_id=? AND school_year=? FOR UPDATE');$q->execute([$uid,$annual['year']]);$existing=$q->fetch(PDO::FETCH_ASSOC)?:null;
            if(!hash_equals(self::revision($existing),$revision))throw new RuntimeException('Your saved survey changed after this form was opened. Reload it before saving.');
            $same=$existing!==null;if($existing){foreach($fields as $i=>$field)if((string)($existing[$field]??'')!==(string)$values[$i])$same=false;if((string)($existing['profile_snapshot']??'')!==$context||(string)$existing['student_id']!==$studentId)$same=false;}
            if($same){if($owns)$db->commit();else $db->exec('RELEASE SAVEPOINT mi_survey_save');return;}
            if($existing){$q=$db->prepare('UPDATE multiple_intelligence_survey SET '.implode(',',array_map(static fn($field)=>$field.'=?',$fields)).',student_id=?,profile_snapshot=?,completed_at=NOW() WHERE user_id=? AND school_year=?');$q->execute([...$values,$studentId,$context,$uid,$annual['year']]);}
            else{$q=$db->prepare('INSERT INTO multiple_intelligence_survey (student_id,user_id,school_year,profile_snapshot,'.implode(',',$fields).') VALUES ('.implode(',',array_fill(0,count($values)+4,'?')).')');$q->execute([$studentId,$uid,$annual['year'],$context,...$values]);}
            $resultId=$existing?(int)$existing['id']:(int)$db->lastInsertId();SubmissionNotifications::survey($db,$uid,'mi',$resultId,$annual['year'],(bool)$existing);
            if($owns)$db->commit();else $db->exec('RELEASE SAVEPOINT mi_survey_save');
        }catch(Throwable $e){if($owns&&$db->inTransaction())$db->rollBack();elseif(!$owns)$db->exec('ROLLBACK TO SAVEPOINT mi_survey_save');throw $e;}
    }
}
