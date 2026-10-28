<?php
final class LearnerProfilePaper {
    public static function e($value): string {return htmlspecialchars(trim((string)($value??'')),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function field(string $label,$value='',string $class=''): string {return '<span class="lp-field '.self::e($class).'"><span class="lp-label">'.self::e($label).'</span><span class="lp-value">'.(trim((string)$value)!==''?self::e($value):'&nbsp;').'</span></span>';}
    public static function date($value): string {if(!$value||$value==='0000-00-00')return '';return date('M j, Y',strtotime((string)$value));}
    public static function render(array $assessment,array $learner,string $base='/guidanceversion2/'): string {
        if($assessment['assessment_type']==='mi')return self::intelligence($assessment,$base);
        $e=[self::class,'e'];$field=[self::class,'field'];$scoreText=implode(' · ',array_map(static fn($row)=>$row['name'].' '.$row['score'].'%', $assessment['scores']));
        ob_start();?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Learner's profile</title><link rel="stylesheet" href="<?=$e($base)?>assets/css/learner-profile-paper.css?v=20261028-1"></head><body><main class="lp-sheet" data-assessment-paper>
<header class="lp-school"><img src="<?=$e($base)?>assets/images/srcblogo.png" alt="SRCB school seal"><p>St. Rita's College of Balingasag</p><strong>GUIDANCE AND COUNSELING OFFICE</strong><span>Balingasag, Misamis Oriental</span></header>
<h1>LEARNER’S PROFILE</h1>
<div class="lp-row lp-identifiers"><?=$field('Student ID:',$learner['student_id'])?><?=$field('Grade / Year:',$learner['grade'])?><?=$field('School Year:',$learner['school_year'])?></div>
<div class="lp-row"><?=$field('Name:',$learner['name'],'lp-wide')?><?=$field('Date of Birth:',self::date($learner['birth_date']))?><?=$field('Gender:',$learner['gender'],'lp-small')?></div>
<div class="lp-row"><?=$field('Home Address:',$learner['address'])?></div>
<div class="lp-row"><?=$field('Distance of Home to School (km):')?></div>
<div class="lp-row"><?=$field('Name of Father:',$learner['father'],'lp-wide')?><?=$field('Occupation:',$learner['father_occupation'])?></div>
<div class="lp-row"><?=$field('Name of Mother:',$learner['mother'],'lp-wide')?><?=$field('Occupation:',$learner['mother_occupation'])?></div>
<div class="lp-row lp-family"><?=$field('Annual Family Income:',$learner['family_income'])?><span class="lp-siblings-label">Number of Siblings:</span><?=$field('(M)',$learner['brothers'],'lp-count')?><?=$field('(F)',$learner['sisters'],'lp-count')?><?=$field('Sibling Position:',$learner['birth_order'])?></div>
<div class="lp-row"><?=$field('How many siblings who are in school?')?><?=$field('How many are not?')?></div>
<div class="lp-row"><?=$field('Risk factors manifested:')?><?=$field('Remarks:')?></div>
<div class="lp-row"><?=$field('Intelligences Possessed:',$learner['intelligences'])?></div>
<?php if($assessment['assessment_type']!=='mi'):?><div class="lp-row"><?=$field('Learning Style:',$scoreText)?></div><div class="lp-row"><?=$field('Recorded Preference:',$assessment['dominant_style'])?><?=$field('Inventory:',$assessment['level']==='intermediate'?'Intermediate':'Standard')?></div><?php endif;?>
<div class="lp-row"><?=$field('Personality:')?><?=$field('Remarks:')?></div>
<div class="lp-row"><?=$field('Interests:')?></div>
<div class="lp-row"><?=$field('Reading Level:')?><?=$field('Numeracy Level:')?></div>
<div class="lp-row"><?=$field('Neighbor Schoolmates:')?></div><div class="lp-writing-line" aria-hidden="true"></div>
<div class="lp-row"><?=$field('Person/s to Contact in case of emergency:',$learner['emergency_name'],'lp-wide')?><?=$field('Contact Number(s):',$learner['emergency_number'])?></div>
<section class="lp-map"><h2>Map of Residence (From School to Home):</h2><div aria-label="Blank space for drawing the residence map"></div></section>
<footer class="lp-footer"><span>Assessment submitted: <?=self::date($assessment['submitted_date'])?></span><span>Blank fields may be completed by hand.</span></footer>
</main></body></html>
<?php return ob_get_clean();
    }
    private static function intelligence(array $assessment,string $base): string {
        $e=[self::class,'e'];ob_start();?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Intelligence profile</title><link rel="stylesheet" href="<?=$e($base)?>assets/css/learner-profile-paper.css?v=20261028-1"></head><body><main class="lp-sheet" data-assessment-paper><header class="lp-school"><img src="<?=$e($base)?>assets/images/srcblogo.png" alt="SRCB school seal"><p>St. Rita's College of Balingasag</p><strong>GUIDANCE AND COUNSELING OFFICE</strong><span>Balingasag, Misamis Oriental</span></header><h1>INTELLIGENCE PROFILE</h1><div class="lp-row"><?=self::field('Name:',$assessment['student'])?></div><div class="lp-row"><?=self::field('Student ID:',$assessment['student_id'])?><?=self::field('Grade / Year:',$assessment['grade_level'])?></div><div class="lp-row"><?=self::field('Submitted:',$assessment['submitted'])?></div><table class="lp-score-table"><thead><tr><th>Intelligence area</th><th>Percentage</th><th>Recorded result</th></tr></thead><tbody><?php foreach($assessment['scores'] as $score):?><tr><td><?=$e($score['name'])?></td><td><?=$e($score['score'])?>%</td><td><?=$score['dominant']?'Dominant':''?></td></tr><?php endforeach;?></tbody></table><p class="lp-score-note">Percentages are rounded.</p></main></body></html>
<?php return ob_get_clean();
    }
}
