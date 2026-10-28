<?php
final class AssessmentProfile {
    public static function load(PDO $db,string $type,int $id): array {
        if($id<1||!in_array($type,['mi','ls','ls_intermediate'],true))throw new InvalidArgumentException('Choose a valid assessment.');
        if($type==='mi'){$query="SELECT a.*,u.id AS profile_user_id,u.first_name,u.middle_name,u.last_name,sp.student_id,sp.department,sp.grade_level FROM multiple_intelligence_survey a JOIN student_profiles sp ON sp.student_id=a.student_id JOIN users u ON u.id=sp.user_id WHERE a.id=?";}
        else{$table=$type==='ls_intermediate'?'learning_style_inventory_intermediate':'learning_style_inventory';$query="SELECT a.*,u.id AS profile_user_id,u.first_name,u.middle_name,u.last_name,sp.student_id,sp.department,sp.grade_level FROM $table a JOIN users u ON u.id=a.user_id LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE a.id=?";}
        $q=$db->prepare($query);$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Assessment not found.');
        $scores=[];
        if($type==='mi'){
            $names=['Naturalist Intelligence','Musical Intelligence','Logical-Mathematical Intelligence','Existential Intelligence','Interpersonal Intelligence','Bodily-Kinesthetic Intelligence','Linguistic Intelligence','Intrapersonal Intelligence','Spatial Intelligence'];$total=0;foreach($names as $i=>$name)$total+=(float)($row['section'.($i+1).'_score']??0);
            foreach($names as $i=>$name){$score=(float)($row['section'.($i+1).'_score']??0);$scores[]=['name'=>$name,'score'=>$total>0?(int)round($score/$total*100):0,'dominant'=>$score>=2];}
        }else{
            $total=(float)$row['visual_score']+(float)$row['auditory_score']+(float)$row['kinesthetic_score'];
            foreach(['Visual'=>'visual_score','Auditory'=>'auditory_score','Kinesthetic'=>'kinesthetic_score'] as $name=>$column)$scores[]=['name'=>$name,'score'=>$total>0?(int)round((float)$row[$column]/$total*100):0,'dominant'=>strcasecmp(trim((string)($row['learning_style']??'')),$name)===0];
        }
        $stamp=$type==='mi'?($row['completed_at']??''):($row['created_at']??'');
        return ['success'=>true,'assessment_type'=>$type,'assessment_id'=>$id,'user_id'=>(int)$row['profile_user_id'],'student'=>trim(implode(' ',array_filter([$row['first_name']??'',$row['middle_name']??'',$row['last_name']??'']))),'student_id'=>$row['student_id']??'','submitted'=>$stamp?date('F j, Y \a\t g:i A',strtotime($stamp)):'Not recorded','submitted_date'=>$stamp?substr($stamp,0,10):'','level'=>$type==='ls_intermediate'?'intermediate':($type==='ls'?'standard':null),'department'=>$row['department']??'','grade_level'=>$row['grade_level']??'','dominant_style'=>$type!=='mi'?($row['learning_style']??''):'','scores'=>$scores];
    }
    public static function learner(PDO $db,array $assessment,bool $canReadPds): array {
        $q=$db->prepare('SELECT first_name,middle_name,last_name FROM users WHERE id=?');$q->execute([$assessment['user_id']]);$user=$q->fetch(PDO::FETCH_ASSOC)?:[];
        $data=[];
        if($canReadPds){$q=$db->prepare('SELECT * FROM pds WHERE user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1');$q->execute([$assessment['user_id']]);$data=$q->fetch(PDO::FETCH_ASSOC)?:[];}
        $name=trim(implode(' ',array_filter([$user['first_name']??'',$user['middle_name']??'',$user['last_name']??''])));
        $result=['name'=>$name?:$assessment['student'],'student_id'=>$assessment['student_id'],'grade'=>$assessment['grade_level'],'school_year'=>$data['school_year']??'','birth_date'=>$data['birth_date']??'','gender'=>$data['gender']??'','address'=>$data['home_address']??($data['address']??''),'father'=>trim(implode(' ',array_filter([$data['father_given_name']??'',$data['father_middle_name']??'',$data['father_surname']??'']))),'father_occupation'=>$data['father_occupation']??'','mother'=>trim(implode(' ',array_filter([$data['mother_given_name']??'',$data['mother_middle_name']??'',$data['mother_surname']??'']))),'mother_occupation'=>$data['mother_occupation']??'','family_income'=>$data['family_income']??'','brothers'=>$data['brothers_count']??'','sisters'=>$data['sisters_count']??'','birth_order'=>strtolower((string)($data['birth_order']??''))==='others'?($data['birth_order_others']??''):($data['birth_order']??''),'emergency_name'=>$data['emergency_contact_name']??'','emergency_number'=>$data['emergency_contact_number']??'','intelligences'=>''];
        $mi=$assessment['assessment_type']==='mi'?$assessment:null;
        if(!$mi&&$assessment['student_id']!==''){$q=$db->prepare('SELECT id FROM multiple_intelligence_survey WHERE student_id=? ORDER BY completed_at DESC,id DESC LIMIT 1');$q->execute([$assessment['student_id']]);$miId=$q->fetchColumn();if($miId)$mi=self::load($db,'mi',(int)$miId);}
        if($mi)$result['intelligences']=implode(', ',array_map(static fn($row)=>str_replace(' Intelligence','',$row['name']),array_filter($mi['scores'],static fn($row)=>$row['dominant'])));
        return $result;
    }
}
