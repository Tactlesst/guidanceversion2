<?php
require_once __DIR__.'/pds_paper.php';
/** Read-only screen view. Missing answers are never converted into a recorded None/No. */
final class PDSDigitalView {
 public static function text($value):string{return PDSPaperDocument::text($value);}
 public static function e($value):string{return htmlspecialchars(self::text($value),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
 public static function answer($value,bool $date=false):string{
  $text=self::text($value);if($text===''||$text==='0000-00-00')return '<span class="pdv-missing">Not provided</span>';
  if($date){$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',substr($text,0,10));if($parsed&&$parsed->format('Y-m-d')===substr($text,0,10))$text=$parsed->format('M j, Y');}
  $none=in_array(mb_strtolower(trim($text)),['none','n/a','not applicable'],true);
  return '<span'.($none?' class="pdv-explicit"':'').'>'.self::e($text).'</span>';
 }
 public static function groups():array{return [
  'School information'=>['school_year'=>'School year','education_level'=>'Education level','grade_level'=>'Grade / year level','student_type'=>'Student type','course'=>'Program / course','year_level'=>'Year','semester'=>'Semester','strand'=>'Strand'],
  'Personal information'=>['last_name'=>'Last name','first_name'=>'Given name','middle_name'=>'Middle name','suffix'=>'Suffix','nickname'=>'Nickname','gender'=>'Gender','birth_date'=>'Date of birth','birth_place'=>'Place of birth','age'=>'Recorded age','civil_status'=>'Civil status','religion'=>'Religion','nationality'=>'Nationality','citizenship'=>'Citizenship','citizenship_others'=>'Other citizenship'],
  'Contact information'=>['home_address'=>'Home address','city_street'=>'Street','city_purok'=>'Purok','city_barangay'=>'Barangay','contact_number'=>'Telephone / mobile number','email'=>'Email'],
  'Emergency contact'=>['emergency_contact_name'=>'Contact person','emergency_relationship'=>'Relationship','emergency_contact_number'=>'Contact number'],
  'Household information'=>['parents_marital'=>'Parents’ marital status','child_residing'=>'Residing with','child_residing_others'=>'Other person residing with','birth_order'=>'Birth order','birth_order_others'=>'Other birth order','brothers_count'=>'Brothers','sisters_count'=>'Sisters','stepbrothers_count'=>'Stepbrothers','stepsisters_count'=>'Stepsisters','adopted_count'=>'Adopted siblings','relatives_at_home'=>'Other relatives at home','relatives_others'=>'Other relatives','total_relatives_at_home'=>'Number of relatives at home','family_income'=>'Family monthly income'],
  'Home & support'=>['residence_type'=>'Residence','languages_spoken'=>'Languages spoken at home','financial_support'=>'Source of financial support','financial_support_others'=>'Other financial support','leisure_activities'=>'Family leisure activities','leisure_activities_others'=>'Other leisure activities','special_talents'=>'Special talents'],
  'Guardian information'=>['guardian_name'=>'Guardian’s name','guardian_address'=>'Guardian’s address','guardian_relation'=>'Relationship','guardian_relation_others'=>'Other relationship','guardian_relationship'=>'Other saved relationship','guardian_contact'=>'Contact number'],
  'Health information'=>['height'=>'Height','weight'=>'Weight','physical_condition'=>'Physical condition','health_problem'=>'Physical handicap / health problem','health_problem_details'=>'Health problem details','last_doctor_visit'=>'Last doctor visit','doctor_visit_reason'=>'Reason for doctor visit','general_condition'=>'General condition'],
  'Signature details'=>['student_signature_name'=>'Student printed name','student_date_signed'=>'Student date signed','parent_signature_name'=>'Parent / guardian printed name','parent_date_signed'=>'Parent / guardian date signed']
 ];}
 public static function schoolFields(array $data):array{
  $fields=self::groups()['School information'];
  $level=mb_strtolower(preg_replace('/[\s_-]+/u','',self::text($data['education_level']??'')));
  $college=in_array($level,['highered','highereducation','college'],true);
  $senior=in_array($level,['seniorhigh','seniorhighschool','shs'],true);
  $basic=in_array($level,['highschool','juniorhigh','juniorhighschool','elementary','gradeschool','kinder','kindergarten'],true);
  if(!$college&&!$senior&&!$basic){
   $grade=mb_strtolower(self::text($data['grade_level']??$data['profile_grade']??''));
   $college=str_contains($grade,'college')||(bool)preg_match('/^[1-9][0-9]*(?:st|nd|rd|th)?\s+year\b/u',$grade);
   $senior=(bool)preg_match('/^grade\s*(?:11|12)(?:\s|$)/u',$grade);
   $basic=(bool)preg_match('/^(?:grade\s*(?:[1-9]|10)(?:\s|$)|kindergarten|kinder|nursery|prep)/u',$grade);
  }
  if($college)unset($fields['strand']);
  elseif($senior)unset($fields['course'],$fields['year_level'],$fields['semester']);
  elseif($basic)unset($fields['course'],$fields['year_level'],$fields['semester'],$fields['strand']);
  return $fields;
 }
 private static function fields(array $data,array $labels):string{$html='<dl class="pdv-fields">';foreach($labels as $field=>$label){$value=$data[$field]??null;if($field==='education_level')$value=['highered'=>'Higher education','seniorhigh'=>'Senior high school','highschool'=>'Junior high school','elementary'=>'Elementary','kinder'=>'Kindergarten'][$value]??$value;$html.='<div data-pdv-field="'.self::e($field).'"'.(in_array($field,['home_address','guardian_address','health_problem_details','special_talents'],true)?' class="pdv-wide-field"':'').'><dt>'.self::e($label).'</dt><dd>'.self::answer($value,in_array($field,['birth_date','last_doctor_visit','baptism_date','communion_date','confirmation_date','student_date_signed','parent_date_signed'],true)).'</dd></div>';}$html.='</dl>';return $html;}
 private static function block(string $title,array $data,array $labels):string{return '<section class="pdv-subsection"><h3>'.self::e($title).'</h3>'.self::fields($data,$labels).'</section>';}
 private static function table(string $title,array $rows,array $columns):string{
  $html='<section class="pdv-subsection"><h3>'.self::e($title).'</h3>';
  if(!$rows)return $html.'<p class="pdv-empty"><strong>Not provided</strong><span>No details have been entered in this section.</span></p></section>';
  $html.='<div class="pdv-table-wrap"><table class="pdv-table" data-cw-no-cards><thead><tr>';foreach($columns as $label)$html.='<th scope="col">'.self::e($label).'</th>';$html.='</tr></thead><tbody>';
  foreach($rows as $row){$html.='<tr>';foreach($columns as $field=>$_)$html.='<td>'.self::answer($row[$field]??null,$field==='test_date').'</td>';$html.='</tr>';}
  return $html.'</tbody></table></div></section>';
 }
 private static function media(string $title,$value,string $base,string $kind='signature'):string{
  $src=PDSPaperDocument::media($value,$base,$kind);$html='<div class="pdv-media"><h4>'.self::e($title).'</h4>';
  if($src)$html.='<img src="'.self::e($src).'" alt="'.self::e($title).'" loading="lazy">';
  elseif(self::text($value)!=='')$html.='<p class="pdv-missing">A saved reference exists, but its image is unavailable.</p>';
  else $html.='<p class="pdv-missing">Not provided</p>';
  return $html.'</div>';
 }
 public static function render(array $data,array $related,string $base,string $updateURL,string $paperURL,bool $readOnly=false):string{
  $groups=self::groups();$groups['School information']=self::schoolFields($data);$name=trim(implode(' ',array_filter([$data['account_first_name']??$data['first_name']??'',$data['account_middle_name']??$data['middle_name']??'',$data['account_last_name']??$data['last_name']??''],static fn($v)=>self::text($v)!=='')));
  $initial=mb_strtoupper(mb_substr($data['account_first_name']??$data['first_name']??'S',0,1).mb_substr($data['account_last_name']??$data['last_name']??'',0,1));$photo=PDSPaperDocument::media($data['student_photo']??'',$base,'photo');$updated=self::text($data['updated_at']??'');$updatedTime=$updated!==''?strtotime($updated):false;
  $titles=['Personal & school','Family & home','Education','Organizations','Health & tests','Consent & signatures'];$hints=['Your saved personal, academic and contact details.','Parents, household, siblings and your home environment.','Your recorded school history and awards.','Clubs, organizations and roles.','Saved health information and test records.','Privacy agreement and saved signature details.'];
  ob_start();?><link rel="stylesheet" href="<?=self::e($base)?>assets/css/pds-digital.css?v=20261028-1"><section class="pdv-root" id="pdsDigitalView" aria-labelledby="pdvTitle"><header class="pdv-heading"><div><p class="pdv-eyebrow">STUDENT PORTAL · DIGITAL PDS</p><h1 id="pdvTitle">My personal data sheet</h1><p><?=$readOnly?'Read-only archived data; values remain as they were saved in this version.':'A readable view of the information you have saved.'?></p></div><div class="pdv-actions"><?php if(!$readOnly):?><a href="<?=self::e($updateURL)?>" class="pdv-button pdv-primary"><i class="fas fa-pen" aria-hidden="true"></i> Update information</a><?php endif;?><a href="<?=self::e($paperURL)?>" class="pdv-button"><i class="far fa-file-lines" aria-hidden="true"></i> View / Print paper PDS</a></div></header>
  <section class="pdv-identity" aria-label="Student account"><div class="pdv-avatar" aria-hidden="true"><?php if($photo):?><img src="<?=self::e($photo)?>" alt=""><?php else:?><?=self::e($initial)?><?php endif;?></div><div><span class="pdv-saved">Saved personal data sheet</span><h2><?=self::e($name?:'Student')?></h2><p>Student ID: <?=self::answer($data['student_id']??null)?></p></div><div class="pdv-updated"><span>Last saved</span><strong><?=$updated!==''?self::e(date('M j, Y · g:i A',strtotime($updated))):'Not recorded'?></strong></div></section>
  <p class="pdv-key"><span class="pdv-key-dot" aria-hidden="true"></span><strong>Not provided</strong> means unanswered. <strong>None / Not applicable</strong> is shown only when recorded as an answer.</p><div class="pdv-controls"><p class="pdv-eyebrow">YOUR SAVED INFORMATION</p><div><button type="button" data-pdv-expand>Expand all</button><button type="button" data-pdv-collapse>Collapse all</button></div></div>
  <?php foreach($titles as $index=>$title):?><details class="pdv-section" data-pdv-section <?=$index===0?'open':''?>><summary><span class="pdv-section-number"><?=str_pad((string)($index+1),2,'0',STR_PAD_LEFT)?></span><span><strong><?=self::e($title)?></strong><small><?=self::e($hints[$index])?></small></span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary><div class="pdv-section-body">
  <?php if($index===0){foreach(['School information','Personal information','Contact information','Emergency contact'] as $g)echo self::block($g,$data,$groups[$g]);echo self::block('Sacraments',$data,['baptism_status'=>'Baptism received','baptism_date'=>'Baptism date','baptism_church'=>'Baptism church','communion_status'=>'First communion received','communion_date'=>'First communion date','communion_church'=>'First communion church','confirmation_status'=>'Confirmation received','confirmation_date'=>'Confirmation date','confirmation_church'=>'Confirmation church']);}
  elseif($index===1){foreach(['father'=>'Father','mother'=>'Mother'] as $parent=>$label){$fields=[];foreach(['surname'=>'Surname','given_name'=>'Given name','middle_name'=>'Middle name','contact'=>'Contact number','occupation'=>'Occupation','location'=>'Employment location','type'=>'Employment type','status'=>'Employment status','education'=>'Educational attainment','degree'=>'College degree','postgrad'=>'Postgraduate studies','specialization'=>'Specialization'] as $key=>$value)$fields[$parent.'_'.$key]=$value;echo self::block($label.'’s information',$data,$fields);}foreach(['Household information','Home & support','Guardian information'] as $g)echo self::block($g,$data,$groups[$g]);if(self::text($data['sibling_type']??'')!=='')echo self::block('Other saved sibling information',$data,['sibling_type'=>'Recorded sibling information']);echo self::table('Siblings · oldest to youngest',$related['pds_siblings']??[],['sibling_name'=>'Name','sibling_age'=>'Age','sibling_school'=>'School attended','sibling_status'=>'Status','sibling_occupation'=>'Occupation']);}
  elseif($index===2){foreach(['preschool'=>'Preschool','gradeschool'=>'Grade school','highschool'=>'High school','seniorhigh'=>'Senior high school'] as $key=>$label)echo self::block($label,$data,[$key.'_school'=>'School / address',$key.'_awards'=>'Awards / honors',$key.'_year'=>'School year attended']);}
  elseif($index===3)echo self::table('Organizational affiliations',$related['pds_organizations']??[],['school_year'=>'School year','organization_name'=>'Organization / club','designation'=>'Designation']);
  elseif($index===4){echo self::block('Health information',$data,$groups['Health information']);echo self::table('Test results',$related['pds_test_results']??[],['test_name'=>'Test taken','test_date'=>'Date taken','test_rating'=>'Rating']);}
  else{?><div class="pdv-consent-state"><i class="fas fa-shield-halved" aria-hidden="true"></i><div><strong>Privacy agreement</strong><p data-pdv-field="privacy_agreement"><?php if((string)($data['privacy_agreement']??'')==='1'):?>Agreed<?php if(self::text($data['privacy_agreement_date']??'')!==''):?> · <?=self::answer($data['privacy_agreement_date'],true)?><?php endif;?><?php elseif((string)($data['privacy_agreement']??'')==='0'):?>Not recorded as agreed<?php else:?><?=self::answer(null)?><?php endif;?></p></div></div><?php echo self::block('Signature details',$data,$groups['Signature details']);?><div class="pdv-media-grid"><?=self::media('Student signature',$data['student_signature']??null,$base)?><?=self::media('Parent / guardian signature',$data['parent_signature']??null,$base)?></div><p class="pdv-signature-note">Printed names are shown separately from signature images.</p><?php }
  ?><?php if(!$readOnly):?><footer class="pdv-section-footer"><a href="<?=self::e($updateURL.(str_contains($updateURL,'?')?'&':'?').'pds_step='.$index)?>">Edit this section <i class="fas fa-arrow-right" aria-hidden="true"></i></a></footer><?php endif;?></div></details><?php endforeach;?>
  <footer class="pdv-footer">Use View / Print paper PDS for the school’s printable form. Unanswered fields stay blank in that form.</footer></section><script>(()=>{const root=document.getElementById('pdsDigitalView');if(!root)return;const sections=[...root.querySelectorAll('[data-pdv-section]')];root.querySelector('[data-pdv-expand]').addEventListener('click',()=>sections.forEach(section=>section.open=true));root.querySelector('[data-pdv-collapse]').addEventListener('click',()=>sections.forEach(section=>section.open=false));})();</script><?php return ob_get_clean();
 }
}
