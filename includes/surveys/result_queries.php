<?php
require_once __DIR__.'/annual.php';
// Summary counts, filters and rows share the same annual result definitions.
function assessmentResultQuery(string $type='all',string $search='',string $schoolYear=''):array {
    $kinds=['multiple_intelligence'=>'mi','learning_style'=>'ls','learning_style_intermediate'=>'ls_intermediate'];if($type!=='all'&&!isset($kinds[$type]))throw new InvalidArgumentException('Choose a valid assessment type.');
    if($schoolYear!==''&&$schoolYear!=='legacy'&&!SurveyAnnual::validYear($schoolYear))throw new InvalidArgumentException('Choose a valid school year.');
    $parts=[];
    foreach($kinds as $label=>$kind){if($type!=='all'&&$type!==$label)continue;$table=SurveyAnnual::table($kind);$fields=[];
        foreach(['first_name'=>'u.first_name','last_name'=>'u.last_name','email'=>'u.email','student_id'=>'sp.student_id','department'=>'sp.department','grade_level'=>'sp.grade_level'] as $key=>$fallback){$json="CASE WHEN JSON_VALID(a.profile_snapshot) THEN a.profile_snapshot ELSE '{}' END";$fields[]="CASE WHEN a.profile_snapshot IS NOT NULL THEN CASE WHEN JSON_TYPE(JSON_EXTRACT($json,'$.$key'))='NULL' THEN '' ELSE COALESCE(JSON_UNQUOTE(JSON_EXTRACT($json,'$.$key')),'') END ELSE $fallback END AS $key";}
        $ids=$kind==='mi'?"a.id AS mi_id,a.completed_at AS mi_date,NULL AS ls_id,NULL AS ls_date,NULL AS ls_level":"NULL AS mi_id,NULL AS mi_date,a.id AS ls_id,a.completed_at AS ls_date,'".($kind==='ls'?'standard':'intermediate')."' AS ls_level";
        $parts[]='SELECT u.id AS user_id,'.implode(',',$fields).",$ids,a.school_year,a.completed_at AS sort_date,'$label' AS assessment_type FROM $table a JOIN users u ON u.id=a.user_id LEFT JOIN student_profiles sp ON sp.user_id=u.id";
    }
    $sql='SELECT * FROM ('.implode(' UNION ALL ',$parts).') annual_results';$where=[];$params=[];
    if($schoolYear!==''){$where[]='school_year=?';$params[]=$schoolYear==='legacy'?'':$schoolYear;}
    if($search!==''){$where[]='(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_id LIKE ?)';$params=array_merge($params,array_fill(0,4,'%'.$search.'%'));}
    if($where)$sql.=' WHERE '.implode(' AND ',$where);return [$sql,$params];
}
