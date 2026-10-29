<?php
/** School-defined assessment assignment, shared by navigation and server handlers. */
function studentSurveyAssignment(array $profile):?array {
    $department=strtolower(trim((string)($profile['department']??'')));$grade=strtolower(trim((string)($profile['grade_level']??'')));$kind=null;
    if(str_contains($department,'kinder')||str_contains($grade,'kinder'))return null;
    // Grade bands take precedence over broad department labels.
    if(preg_match('/^(?:grade|gr\.?)\s*(\d{1,2})\b/i',$grade,$match)){
        $number=(int)$match[1];
        $kind=$number>=1&&$number<=3?'ls':($number>=4&&$number<=10?'ls_intermediate':($number>=11&&$number<=12?'mi':null));
    }elseif(str_contains($grade,'college')||str_contains($department,'higher')||str_contains($department,'college')||str_contains($department,'tertiary'))$kind='mi';
    $map=[
        'mi'=>['kind'=>'mi','title'=>'Multiple Intelligence Survey','nav'=>'Intelligence survey','page'=>'multiple_intelligence_survey','results'=>'survey_thankyou','level'=>'Grades 11–12 and College'],
        'ls'=>['kind'=>'ls','title'=>'Learning Style','nav'=>'Learning style survey','page'=>'learning_style_inventory','results'=>'learning_style_inventory','level'=>'Grades 1–3'],
        'ls_intermediate'=>['kind'=>'ls_intermediate','title'=>'Learning Style (Intermediate)','nav'=>'Learning style survey','page'=>'learning_style_inventory_intermediate','results'=>'learning_style_inventory_intermediate','level'=>'Grades 4–10']
    ];return $map[$kind]??null;
}
