<?php
// Use identical result definitions for summary counts, filters and pagination.
function assessmentResultQuery(string $type = 'all', string $search = ''): array {
    if (!in_array($type, ['all','multiple_intelligence','learning_style','learning_style_intermediate'], true)) {
        throw new InvalidArgumentException('Choose a valid assessment type.');
    }
    $parts=[];$params=[];
    $searchSql=$search!==''?' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR sp.student_id LIKE ?)':'';
    if ($type==='all'||$type==='multiple_intelligence') {
        $parts[]="SELECT u.id AS user_id,u.first_name,u.last_name,u.email,sp.student_id,sp.department,sp.grade_level,
            mis.id AS mi_id,mis.completed_at AS mi_date,NULL AS ls_id,NULL AS ls_date,
            NULL AS ls_level,mis.completed_at AS sort_date,'multiple_intelligence' AS assessment_type
            FROM multiple_intelligence_survey mis
            JOIN student_profiles sp ON sp.student_id=mis.student_id
            JOIN users u ON u.id=sp.user_id WHERE 1=1".$searchSql;
        if($search!=='')$params=array_merge($params,array_fill(0,4,'%'.$search.'%'));
    }
    foreach (['learning_style'=>'learning_style_inventory','learning_style_intermediate'=>'learning_style_inventory_intermediate'] as $assessmentType=>$table) {
        if($type!=='all'&&$type!==$assessmentType)continue;
        $level=$assessmentType==='learning_style_intermediate'?'intermediate':'standard';
        $parts[]="SELECT u.id AS user_id,u.first_name,u.last_name,u.email,sp.student_id,sp.department,sp.grade_level,
            NULL AS mi_id,NULL AS mi_date,lsi.id AS ls_id,lsi.created_at AS ls_date,
            '$level' AS ls_level,lsi.created_at AS sort_date,'$assessmentType' AS assessment_type
            FROM $table lsi JOIN users u ON u.id=lsi.user_id
            LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE 1=1".$searchSql;
        if($search!=='')$params=array_merge($params,array_fill(0,4,'%'.$search.'%'));
    }
    return [implode(' UNION ALL ',$parts),$params];
}
