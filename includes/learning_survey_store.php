<?php
require_once __DIR__.'/survey_annual.php';require_once __DIR__.'/../classes/SubmissionNotifications.php';
final class LearningSurveyStore {
    public static function table(string $kind):string {if(!in_array($kind,['ls','ls_intermediate'],true))throw new InvalidArgumentException('Choose a valid survey.');return $kind==='ls'?'learning_style_inventory':'learning_style_inventory_intermediate';}
    public static function revision(?array $record):string{return hash('sha256',json_encode($record,JSON_INVALID_UTF8_SUBSTITUTE));}
    public static function save(PDO $db,int $uid,string $kind,array $post,string $revision):void {
        $table=self::table($kind);$per=$kind==='ls'?4:10;$scores=[0,0,0];$answers=[];
        for($i=1;$i<=$per*3;$i++){$raw=$post['q'.$i]??null;if(!is_string($raw)||!in_array($raw,$kind==='ls'?['yes','no']:['1','2','3'],true))throw new InvalidArgumentException('Answer every statement before submitting.');$answers[$i]=$raw;$scores[(int)floor(($i-1)/$per)]+=$kind==='ls'?($raw==='yes'?1:0):(int)$raw;}
        if(($post['confirm_answers']??'')!=='1')throw new InvalidArgumentException('Confirm that you reviewed your answers before submitting.');
        $max=max($scores);$style=count(array_filter($scores,static fn($score)=>$score===$max))===1?['Visual','Auditory','Kinesthetic'][array_search($max,$scores,true)]:'Multi-Sensory';
        $owns=!$db->inTransaction();if($owns)$db->beginTransaction();else $db->exec('SAVEPOINT learning_survey_save');
        try{
            $annual=SurveyAnnual::writable($db,$uid,$kind,$post['_survey_year']??null);
            $q=$db->prepare("SELECT * FROM $table WHERE user_id=? AND school_year=? FOR UPDATE");$q->execute([$uid,$annual['year']]);$record=$q->fetch(PDO::FETCH_ASSOC)?:null;
            if(!hash_equals(self::revision($record),$revision))throw new RuntimeException('Your results changed after you opened this survey. Reload before saving.');
            $values=['profile_snapshot'=>json_encode($annual['context'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'visual_score'=>$scores[0],'auditory_score'=>$scores[1],'kinesthetic_score'=>$scores[2],'learning_style'=>$style];
            if($kind==='ls')$values['answers_json']=json_encode($answers,JSON_THROW_ON_ERROR);
            if($kind==='ls_intermediate')foreach($answers as $number=>$answer){$group=['one','two','three'][(int)floor(($number-1)/10)];$values['section_'.$group.'_q'.(($number-1)%10+1)]=(int)$answer;}
            $same=$record!==null;if($record)foreach($values as $field=>$value)if((string)($record[$field]??'')!==(string)$value)$same=false;
            if($same){if($owns)$db->commit();else $db->exec('RELEASE SAVEPOINT learning_survey_save');return;}
            if($record){$q=$db->prepare("UPDATE $table SET ".implode(',',array_map(static fn($field)=>$field.'=?',array_keys($values))).',completed_at=NOW() WHERE user_id=? AND school_year=?');$q->execute([...array_values($values),$uid,$annual['year']]);}
            else{$values['user_id']=$uid;$values['school_year']=$annual['year'];$q=$db->prepare("INSERT INTO $table (".implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')');$q->execute(array_values($values));}
            $resultId=$record?(int)$record['id']:(int)$db->lastInsertId();SubmissionNotifications::survey($db,$uid,$kind,$resultId,$annual['year'],(bool)$record);
            if($owns)$db->commit();else $db->exec('RELEASE SAVEPOINT learning_survey_save');
        }catch(Throwable $e){if($owns&&$db->inTransaction())$db->rollBack();elseif(!$owns)$db->exec('ROLLBACK TO SAVEPOINT learning_survey_save');throw $e;}
    }
}
