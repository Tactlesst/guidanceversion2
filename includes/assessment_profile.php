<?php
require_once __DIR__.'/pds_address.php';
final class AssessmentProfile {
    public static function load(PDO $db,string $type,int $id): array {
        if($id<1||!in_array($type,['mi','ls','ls_intermediate'],true))throw new InvalidArgumentException('Choose a valid assessment.');
        if($type==='mi'){$query="SELECT a.*,u.id AS profile_user_id,u.first_name,u.middle_name,u.last_name,sp.student_id,sp.department,sp.grade_level FROM multiple_intelligence_survey a JOIN users u ON u.id=a.user_id LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE a.id=?";}
        else{$table=$type==='ls_intermediate'?'learning_style_inventory_intermediate':'learning_style_inventory';$query="SELECT a.*,u.id AS profile_user_id,u.first_name,u.middle_name,u.last_name,sp.student_id,sp.department,sp.grade_level FROM $table a JOIN users u ON u.id=a.user_id LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE a.id=?";}
        $q=$db->prepare($query);$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Assessment not found.');
        $snapshot=json_decode($row['profile_snapshot']??'',true);if(is_array($snapshot))foreach(['first_name','middle_name','last_name','student_id','department','grade_level'] as $key)$row[$key]=$snapshot[$key]??'';
        $scores=[];
        if($type==='mi'){
            $names=['Naturalist Intelligence','Musical Intelligence','Logical-Mathematical Intelligence','Existential Intelligence','Interpersonal Intelligence','Bodily-Kinesthetic Intelligence','Linguistic Intelligence','Intrapersonal Intelligence','Spatial Intelligence'];$total=0;foreach($names as $i=>$name)$total+=(float)($row['section'.($i+1).'_score']??0);
            foreach($names as $i=>$name){$score=(float)($row['section'.($i+1).'_score']??0);$scores[]=['name'=>$name,'score'=>$total>0?(int)round($score/$total*100):0,'dominant'=>$score>=2];}
        }else{
            $total=(float)$row['visual_score']+(float)$row['auditory_score']+(float)$row['kinesthetic_score'];
            foreach(['Visual'=>'visual_score','Auditory'=>'auditory_score','Kinesthetic'=>'kinesthetic_score'] as $name=>$column)$scores[]=['name'=>$name,'score'=>$total>0?(int)round((float)$row[$column]/$total*100):0,'dominant'=>strcasecmp(trim((string)($row['learning_style']??'')),$name)===0];
        }
        $stamp=$row['completed_at']??'';
        return ['success'=>true,'assessment_type'=>$type,'assessment_id'=>$id,'school_year'=>$row['school_year']??'','profile_frozen'=>is_array($snapshot),'user_id'=>(int)$row['profile_user_id'],'student'=>trim(implode(' ',array_filter([$row['first_name']??'',$row['middle_name']??'',$row['last_name']??'']))),'student_id'=>$row['student_id']??'','submitted'=>$stamp?date('F j, Y \a\t g:i A',strtotime($stamp)):'Not recorded','submitted_date'=>$stamp?substr($stamp,0,10):'','level'=>$type==='ls_intermediate'?'intermediate':($type==='ls'?'standard':null),'department'=>$row['department']??'','grade_level'=>$row['grade_level']??'','dominant_style'=>$type!=='mi'?($row['learning_style']??''):'','scores'=>$scores];
    }
    public static function learner(PDO $db,array $assessment,bool $canReadPds): array {
        $data=[];$year=$assessment['school_year']??'';
        if($canReadPds&&$year!==''){
            $q=$db->prepare('SELECT * FROM pds WHERE user_id=? AND school_year=? LIMIT 1');$q->execute([$assessment['user_id'],$year]);$data=$q->fetch(PDO::FETCH_ASSOC)?:[];
            if(!$data){$q=$db->prepare('SELECT v.snapshot_json FROM pds_annual_snapshots a JOIN pds_versions v ON v.id=a.version_id WHERE a.user_id=? AND a.school_year=?');$q->execute([$assessment['user_id'],$year]);$saved=json_decode($q->fetchColumn()?:'',true);$data=$saved['pds']??[];}
        }
        $name=$assessment['student'];
        $result=['name'=>$name?:$assessment['student'],'student_id'=>$assessment['student_id'],'grade'=>$assessment['grade_level'],'school_year'=>$year,'birth_date'=>$data['birth_date']??'','gender'=>$data['gender']??'','address'=>PDSAddress::display($data['home_address']??($data['address']??''),false),'father'=>trim(implode(' ',array_filter([$data['father_given_name']??'',$data['father_middle_name']??'',$data['father_surname']??'']))),'father_occupation'=>$data['father_occupation']??'','mother'=>trim(implode(' ',array_filter([$data['mother_given_name']??'',$data['mother_middle_name']??'',$data['mother_surname']??'']))),'mother_occupation'=>$data['mother_occupation']??'','family_income'=>$data['family_income']??'','brothers'=>$data['brothers_count']??'','sisters'=>$data['sisters_count']??'','birth_order'=>strtolower((string)($data['birth_order']??''))==='others'?($data['birth_order_others']??''):($data['birth_order']??''),'emergency_name'=>$data['emergency_contact_name']??'','emergency_number'=>$data['emergency_contact_number']??'','intelligences'=>''];
        $mi=$assessment['assessment_type']==='mi'?$assessment:null;
        if(!$mi&&$year!==''){$q=$db->prepare('SELECT id FROM multiple_intelligence_survey WHERE user_id=? AND school_year=? ORDER BY completed_at DESC,id DESC LIMIT 1');$q->execute([$assessment['user_id'],$year]);$miId=$q->fetchColumn();if($miId)$mi=self::load($db,'mi',(int)$miId);}
        if($mi)$result['intelligences']=implode(', ',array_map(static fn($row)=>str_replace(' Intelligence','',$row['name']),array_filter($mi['scores'],static fn($row)=>$row['dominant'])));
        return $result;
    }
}
