<?php
/** Read-only reporting adapter over the existing school records. */
class SchoolReportService {
    public const KINDS=['examinees','ses','olsat','counseling'];
    public const MISSING='Not recorded';
    private PDO $db; private array $schema=[]; private ?array $people=null; private ?array $academic=null; private array $cache=[];
    public function __construct(PDO $db){$this->db=$db;}
    public static function allowed(string $role):bool{return in_array($role,['super_admin','admin','guidance_advocate'],true);}
    public static function filters(string $kind,array $input):array {
        if(!in_array($kind,self::KINDS,true))throw new InvalidArgumentException('Choose a valid report.');
        $out=[];foreach(['school_year','date_from','date_to','grade','section','category','examination','program','account_type','qualification','result','search','verified_by','approved_by'] as $key){$v=$input[$key]??'';if(!is_string($v)||mb_strlen($v)>150)throw new InvalidArgumentException('Check the report filters.');$out[$key]=trim($v);}
        foreach(['date_from','date_to'] as $key){if($out[$key]!==''){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$out[$key]);if(!$d||$d->format('Y-m-d')!==$out[$key]||$out[$key]<'1000-01-01')throw new InvalidArgumentException('Choose valid report dates.');}}
        if($out['date_from']!==''&&$out['date_to']!==''&&$out['date_from']>$out['date_to'])throw new InvalidArgumentException('The end date must be on or after the start date.');
        if(!in_array($out['result'],['','all','passed','failed','pending','completed','absent','cancelled','unclassified','summary'],true))throw new InvalidArgumentException('Choose a valid result status.');
        if($kind==='counseling'&&$out['search']!=='')throw new InvalidArgumentException('General counseling reports do not support individual name searches.');
        $out['include_records']=$kind!=='counseling'&&in_array($input['include_records']??false,[true,1,'1','on'],true);
        if($kind==='examinees'&&$out['result']==='summary')$out['include_records']=false;
        return $out;
    }
    private function columns(string $table):array {
        if(!array_key_exists($table,$this->schema)){try{$this->schema[$table]=array_column($this->db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC),'Field');}catch(PDOException $e){if(($e->errorInfo[1]??0)!==1146)throw $e;$this->schema[$table]=[];}}
        return $this->schema[$table];
    }
    private function field(string $table,array $candidates,string $alias):string {foreach($candidates as $column)if(in_array($column,$this->columns($table),true))return '`'.$column.'` AS `'.$alias.'`';return 'NULL AS `'.$alias.'`';}
    private static function value($value):?string {if($value===null)return null;$v=trim((string)$value);return $v===''?null:$v;}
    private function people():array {
        if($this->people!==null)return $this->people;$this->people=[];
        $q=$this->db->query('SELECT id,first_name,middle_name,last_name,email,role FROM users');foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$this->people[(int)$r['id']]=['name'=>trim($r['last_name'].', '.$r['first_name'].' '.($r['middle_name']??'')),'email'=>$r['email'],'account_type'=>$r['role'],'identifier'=>null,'grade'=>null,'section'=>null];
        $q=$this->db->query('SELECT user_id,student_id,grade_level,'.$this->field('student_profiles',['section','section_name'],'section').' FROM student_profiles');foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['user_id'];if(isset($this->people[$id])){$this->people[$id]['identifier']=self::value($r['student_id']);$this->people[$id]['grade']=self::value($r['grade_level']);$this->people[$id]['section']=self::value($r['section']);}}
        return $this->people;
    }
    private function academics():array {
        if($this->academic!==null)return $this->academic;$this->academic=[];
        foreach(['pds'=>['grade_level','year_level'],'pds_highschool'=>['grade_level','grade_level_elem'],'pds_seniorhigh'=>['grade_level_shs'],'pds_college'=>['year_level']] as $table=>$grade){
            if(!$this->columns($table))continue;
            $sql='SELECT id,user_id,school_year,'.$this->field($table,$grade,'grade').','.$this->field($table,['section','section_name'],'section').','.$this->field($table,['created_at'],'recorded_at').' FROM '.$table;
            foreach($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r)$this->academic[(int)$r['user_id']][]=$r;
        }
        foreach($this->academic as &$list)usort($list,static fn($a,$b)=>strcmp((string)$b['recorded_at'],(string)$a['recorded_at'])?:((int)$b['id']<=>(int)$a['id']));unset($list);return $this->academic;
    }
    private function membership(int $userId,?string $date):array {
        $person=$this->people()[$userId]??['name'=>self::MISSING,'email'=>null,'identifier'=>null,'grade'=>null,'section'=>null];$pds=null;
        foreach($this->academics()[$userId]??[] as $row){if(!$date||!$row['recorded_at']||substr($row['recorded_at'],0,10)<=substr($date,0,10)){$pds=$row;break;}}
        return array_merge($person,['grade'=>self::value($pds['grade']??null)??$person['grade'],'section'=>self::value($pds['section']??null)??$person['section'],'school_year'=>self::value($pds['school_year']??null)]);
    }
    private function rows(string $kind):array {
        if(isset($this->cache[$kind]))return $this->cache[$kind];$rows=[];
        if($kind==='ses'){
            $rows=$this->sesRows();
        }elseif($kind==='counseling'){
            $q=$this->db->query('SELECT id,user_id,appointment_date,status,concern_type,assigned_advocate_id,nature_of_contact,is_follow_up,follow_up_needed,'.$this->field('counseling_appointments',['session_type','session_format','counseling_type'],'session_type').' FROM counseling_appointments ORDER BY appointment_date,id');
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$member=$this->membership((int)$r['user_id'],$r['appointment_date']);$format=strtolower((string)$r['session_type']);if(!in_array($format,['individual','group'],true))$format=self::MISSING;
                // Names, IDs, narratives and notes are never carried into the counseling report dataset.
                $rows[]=['id'=>(int)$r['id'],'date'=>substr((string)$r['appointment_date'],0,10),'grade'=>$member['grade'],'section'=>$member['section'],'school_year'=>$member['school_year'],'category'=>self::value($r['concern_type'])??self::MISSING,'status'=>strtolower($r['status']),'format'=>$format,'counselor'=>self::allowed($this->people()[(int)$r['assigned_advocate_id']]['account_type']??'')?($this->people()[(int)$r['assigned_advocate_id']]['name']??'Unassigned'):'Unassigned','referral'=>in_array(strtolower(trim((string)$r['nature_of_contact'])),['referral','referral_interview','referred','teacher referral','parent referral'],true),'followup'=>(int)$r['is_follow_up']===1,'followup_needed'=>(int)$r['follow_up_needed']===1];
            }
        }else{
            $extras=[$this->field('entrance_exam_appointments',['section','section_name'],'section'),$this->field('entrance_exam_appointments',['school_ability_index','sai'],'sai'),$this->field('entrance_exam_appointments',['percentile_rank','age_percentile_rank'],'percentile'),$this->field('entrance_exam_appointments',['passing_score'],'passing_score'),$this->field('entrance_exam_appointments',['completed_at','date_completed'],'completed_at')];
            $q=$this->db->query('SELECT id,user_id,preferred_date,exam_date,grade_level_applying,status,exam_score,exam_result,total_score,total_items,school_year,olsat_level,olsat_form,stanine_score,interpretation,qualified_grade,preferred_program,'.implode(',',$extras).' FROM entrance_exam_appointments ORDER BY COALESCE(exam_date,preferred_date) DESC,id DESC');
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){if($kind==='olsat'&&($r['status']!=='completed'||!self::isOLSAT($r)))continue;$date=$r['exam_date']?:$r['preferred_date'];$member=$this->membership((int)$r['user_id'],$date);$percentage=$r['total_score']!==null&&$r['total_items']>0&&$r['total_score']>=0&&$r['total_score']<=$r['total_items']?round((float)$r['total_score']/(float)$r['total_items']*100,2):null;
                $result=self::examResult($r);$exam=self::isOLSAT($r)?'OLSAT assessment':'Entrance examination';if(self::value($r['olsat_level']))$exam.=' · Level '.$r['olsat_level'];if(self::value($r['olsat_form']))$exam.=' · Form '.$r['olsat_form'];
                $rows[]=array_merge($member,['id'=>(int)$r['id'],'user_id'=>(int)$r['user_id'],'date'=>substr((string)$date,0,10),'grade'=>self::value($r['grade_level_applying'])??$member['grade'],'section'=>self::value($r['section'])??$member['section'],'school_year'=>self::value($r['school_year']),'category'=>$kind==='olsat'?(self::value($r['interpretation'])??self::MISSING):$result,'result'=>$result,'qualified'=>self::value($r['qualified_grade'])!==null,'qualification'=>self::value($r['qualified_grade'])!==null?'Qualified grade recorded':'No qualified grade recorded','status'=>$r['status'],'examination'=>$exam,'score'=>$r['total_score']!==null?(float)$r['total_score']:($r['exam_score']!==null?(float)$r['exam_score']:null),'items'=>$r['total_items']!==null?(int)$r['total_items']:null,'percentage'=>$percentage,'sai'=>$r['sai']!==null?(float)$r['sai']:null,'percentile'=>$r['percentile']!==null?(float)$r['percentile']:null,'stanine'=>$r['stanine_score'],'classification'=>self::value($r['interpretation']),'passing_score'=>$r['passing_score'],'completed_at'=>$r['completed_at'],'program'=>$r['preferred_program']]);
            }
        }
        return $this->cache[$kind]=$rows;
    }
    /** Explicit OLSAT metadata is required; a generic score or stanine is not sufficient. */
    public static function isOLSAT(array $r):bool {
        foreach(['olsat_level','olsat_form'] as $key){$value=strtolower(trim((string)($r[$key]??'')));if(in_array($value,['','n/a','na','none','unknown','-'],true))return false;}
        return true;
    }
    /** Current PDS information is read on demand; no prediction is invented or written by reporting. */
    private function sesRows():array {
        $pds=[];
        // The unified PDS is authoritative. Legacy forms are fallback records only.
        foreach(['pds','pds_highschool','pds_seniorhigh','pds_college'] as $table){
            if(!$this->columns($table))continue;
            $dates=array_values(array_intersect(['updated_at','created_at'],$this->columns($table)));$recordedField=$dates?'COALESCE('.implode(',',array_map(static fn($c)=>'`'.$c.'`',$dates)).',NULL) AS recorded_at':'NULL AS recorded_at';
            $fields=[$this->field($table,['family_income'],'income'),$recordedField,$this->field($table,['school_year'],'school_year'),$this->field($table,['grade_level','grade_level_shs','year_level','grade_level_elem'],'grade'),$this->field($table,['section','section_name'],'section')];
            foreach($this->db->query('SELECT id,user_id,'.implode(',',$fields).' FROM '.$table.' ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['user_id'];if(!isset($pds[$id]))$pds[$id]=$r;}
        }
        $rows=[];$latest=[];
        $q=$this->db->query('SELECT id,user_id,predicted_ses,confidence_score,prediction_date,is_latest,model_used FROM ses_predictions ORDER BY prediction_date DESC,id DESC');
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
            $uid=(int)$r['user_id'];$source=$pds[$uid]??null;$sameIncome=$source&&$source['recorded_at']&&$source['recorded_at']<=$r['prediction_date'];
            $member=$this->membership($uid,$r['prediction_date']);
            $row=array_merge($member,['id'=>(int)$r['id'],'user_id'=>$uid,'date'=>substr((string)$r['prediction_date'],0,10),'category'=>self::value($r['predicted_ses'])??self::MISSING,'confidence'=>$r['confidence_score']!==null&&$r['confidence_score']>=0&&$r['confidence_score']<=1?(float)$r['confidence_score']:null,'latest'=>(bool)$r['is_latest']&&!$source,'income'=>$sameIncome?self::incomeLabel($source['income']):self::MISSING,'pds_available'=>(bool)$source,'classification_source'=>'Recorded estimate; rules not verified']);
            $rows[]=$row;if($r['is_latest']&&!isset($latest[$uid]))$latest[$uid]=['row'=>$row,'at'=>$r['prediction_date']];
        }
        foreach($pds as $uid=>$r){
            $member=$this->people()[$uid]??null;if(!$member)continue;
            $recorded=self::value($r['recorded_at']);$prediction=$latest[$uid]??null;
            // A prediction predating a PDS edit must not be presented as a current classification.
            $current=$prediction&&$recorded&&$prediction['at']>=$recorded;
            $row=array_merge($member,['id'=>(int)$r['id'],'user_id'=>$uid,'grade'=>self::value($r['grade'])??$member['grade'],'section'=>self::value($r['section'])??$member['section'],'school_year'=>self::value($r['school_year']),'date'=>substr((string)($current?max($recorded,$prediction['at']):$recorded),0,10),'category'=>$current?$prediction['row']['category']:'Unclassified','confidence'=>$current?$prediction['row']['confidence']:null,'latest'=>true,'income'=>self::incomeLabel($r['income']),'pds_available'=>true,'classification_source'=>$current?'Recorded estimate; rules not verified':'Awaiting approved school rules']);
            $rows[]=$row;
        }
        usort($rows,static fn($a,$b)=>strcmp($b['date'],$a['date'])?:((int)$b['latest']<=>(int)$a['latest'])?:($b['id']<=>$a['id']));return $rows;
    }
    private static function incomeLabel($value):string {
        $value=self::value($value);if($value===null)return self::MISSING;
        $key=strtolower(preg_replace('/[₱Pp,\s]+/u','',$value));
        $labels=['below10000'=>'Below PHP 10,000','10001-13000'=>'PHP 10,001–13,000','13001-15000'=>'PHP 13,001–15,000','15001-18000'=>'PHP 15,001–18,000','18001-21000'=>'PHP 18,001–21,000','21001-24000'=>'PHP 21,001–24,000','24001-27000'=>'PHP 24,001–27,000','27001-30000'=>'PHP 27,001–30,000','above30000'=>'Above PHP 30,000'];
        return $labels[$key]??$value;
    }
    public static function examResult(array $r):string {
        $status=strtolower((string)$r['status']);if(in_array($status,['missed','absent'],true))return 'Absent';if($status==='cancelled')return 'Cancelled';if($status!=='completed')return 'Pending';
        $result=strtolower(trim((string)($r['exam_result']??'')));if(in_array($result,['passed','failed'],true))return ucfirst($result);
        if(self::value($r['qualified_grade']??null))return 'Passed';return 'Unclassified';
    }
    public function options(string $kind):array {
        $options=[];foreach(['school_year','grade','section','category','examination','program','account_type','qualification'] as $key)$options[$key]=[];
        foreach($this->rows($kind) as $row)foreach($options as $key=>$_){$value=self::value($row[$key]??null)??self::MISSING;$options[$key][$value]=$value;}
        if($kind==='ses')foreach(['Low','Middle','High','Unclassified'] as $value)$options['category'][$value]=$value;
        if($kind==='olsat')foreach(['Qualified grade recorded','No qualified grade recorded'] as $value)$options['qualification'][$value]=$value;
        foreach($options as &$values){natcasesort($values);$values=array_values($values);}unset($values);return $options;
    }
    private function matches(array $r,array $f,bool $category=true):bool {
        foreach(['school_year','grade','section','examination','program','account_type','qualification'] as $key)if($f[$key]!==''&&($r[$key]??self::MISSING)!==$f[$key]&&(($r[$key]??null)!==null||$f[$key]!==self::MISSING))return false;
        if($category&&$f['category']!==''&&($r['category']??self::MISSING)!==$f['category'])return false;
        if($f['date_from']!==''&&(!$r['date']||$r['date']<$f['date_from']))return false;if($f['date_to']!==''&&(!$r['date']||$r['date']>$f['date_to']))return false;
        if($f['search']!==''&&mb_stripos(($r['name']??'').' '.($r['identifier']??'').' '.($r['email']??'').(isset($r['examination'])?' Appointment #'.($r['id']??''):''),$f['search'])===false)return false;
        return true;
    }
    private static function group(array $rows,string $key):array {$out=[];foreach($rows as $r){$label=self::value($r[$key]??null)??self::MISSING;$out[$label]=($out[$label]??0)+1;}arsort($out);return $out;}
    private static function table(string $title,array $columns,array $rows,bool $detail=false):array{return ['title'=>$title,'columns'=>$columns,'rows'=>$rows,'detail'=>$detail];}
    private static function distribution(string $title,array $counts):array {$total=array_sum($counts);$rows=[];foreach($counts as $label=>$n)$rows[]=[$label,$n,$total?round($n/$total*100,1):null];return self::table($title,['Category','Count','Percentage (%)'],$rows);}
    private static function chart(string $title,array $counts,string $kind='bar'):array{return ['title'=>$title,'labels'=>array_keys($counts),'values'=>array_values($counts),'kind'=>$kind];}
    public function build(string $kind,array $filters,array $actor):array {
        if(!self::allowed((string)($actor['role']??'')))throw new RuntimeException('You are not authorized to access these reports.');$f=self::filters($kind,$filters);$all=$this->rows($kind);$rows=[];
        if($kind==='ses'){
            $historic=$f['school_year']!==''||$f['date_from']!==''||$f['date_to']!=='';$seen=[];
            foreach($all as $r){if(!$historic&&!$r['latest'])continue;if(!$this->matches($r,$f,false)||isset($seen[$r['user_id']]))continue;$seen[$r['user_id']]=true;if($f['category']===''||$r['category']===$f['category'])$rows[]=$r;}
        }else foreach($all as $r)if($this->matches($r,$f))$rows[]=$r;
        $cohort=$rows;
        if($kind==='examinees'&&!in_array($f['result'],['','all','summary'],true))$rows=array_values(array_filter($rows,static function($r)use($f){return $f['result']==='completed'?$r['status']==='completed':strtolower($r['result'])===$f['result'];}));
        $titles=['ses'=>'SES and Family Income Report','olsat'=>'OLSAT Assessment Report','counseling'=>'Guidance Counseling Report','examinees'=>'Examinee Report'];
        if($kind==='examinees')$titles[$kind]=['passed'=>'Passed Examinees Report','failed'=>'Failed Examinees Report','pending'=>'Pending Examinees Report','completed'=>'Completed Examinations Report','absent'=>'Absent Examinees Report','summary'=>'Examination Results Summary','cancelled'=>'Cancelled Examinations Report','unclassified'=>'Unclassified Examinations Report'][$f['result']]??'All Examinees Report';
        $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));$report=['kind'=>$kind,'title'=>$titles[$kind],'report_id'=>strtoupper(substr($kind,0,4)).'-'.$now->format('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3))),'generated_at'=>$now->format('F j, Y, g:i A').' (Philippine time)','filters'=>$f,'filter_labels'=>[],'metrics'=>[],'tables'=>[],'charts'=>[],'findings'=>[],'recommendations'=>[],'notes'=>[],'record_count'=>count($rows),'prepared_by'=>trim(($actor['first_name']??'').' '.($actor['last_name']??''))?:'Authorized staff','verified_by'=>$f['verified_by'],'approved_by'=>$f['approved_by'],'orientation'=>'portrait'];
        foreach(['school_year'=>'School year','date_from'=>'From date','date_to'=>'To date','grade'=>'Grade level','section'=>'Section','examination'=>'Examination','program'=>'Program','account_type'=>'Account type','qualification'=>'Qualification','category'=>'Category','result'=>'Result status','search'=>'Search'] as $key=>$label)if($f[$key]!==''&&!in_array($f[$key],['all'],true))$report['filter_labels'][$label]=$f[$key];if(!$report['filter_labels'])$report['filter_labels']=['Scope'=>'All available records'];
        $report['notes'][]='Grade and section use recorded assessment/PDS values where available, otherwise the current student profile. Historical enrollment snapshots are not maintained.';
        if($kind==='ses'||$kind==='counseling')$report['notes'][]='School year is the recorded PDS school year available for the record date; a calendar year is not substituted for an academic year.';
        if(count(array_filter($rows,static fn($r)=>!self::value($r['section']??null)))>0)$report['notes'][]='Section is not recorded for some or all matching records.';
        if(!$rows)$report['findings'][]='No records match the active filters.';
        if($kind==='ses')$this->sesReport($report,$rows);
        elseif($kind==='counseling')$this->counselingReport($report,$rows);
        elseif($kind==='olsat')$this->olsatReport($report,$rows);
        else $this->examineeReport($report,$rows,$cohort);
        return $report;
    }
    private function sesReport(array &$p,array $rows):void {
        $counts=['Low'=>0,'Middle'=>0,'High'=>0];foreach(self::group($rows,'category') as $k=>$v)$counts[$k]=$v;$n=count($rows);$incomeRecorded=count(array_filter($rows,static fn($r)=>$r['income']!==self::MISSING));$p['metrics']=['Records included'=>$n,'PDS records'=>count(array_filter($rows,static fn($r)=>$r['pds_available'])),'Income recorded'=>$incomeRecorded,'Income not recorded'=>$n-$incomeRecorded,'Low SES'=>$counts['Low'],'Middle SES'=>$counts['Middle'],'High SES'=>$counts['High'],'Unclassified'=>$counts['Unclassified']??0];
        $p['tables'][]=self::distribution('Recorded SES classifications',$counts);if($counts['Low']+$counts['Middle']+$counts['High']>0){$p['charts'][]=self::chart('Recorded SES classifications',$counts);$p['charts'][]=self::chart('SES percentage distribution',$counts,'pie');}$confidence=['Under 20%'=>0,'20 to under 40%'=>0,'40 to under 60%'=>0,'60 to under 80%'=>0,'80 to 100%'=>0];foreach($rows as $row)if($row['confidence']!==null)$confidence[array_keys($confidence)[min(4,(int)floor($row['confidence']*5))]]++;if(array_sum($confidence)>0){$p['charts'][]=self::chart('Prediction confidence',$confidence);$p['tables'][]=self::distribution('Prediction confidence',$confidence);}
        $incomeCounts=self::group($rows,'income');$p['tables'][]=self::distribution('Family monthly income from PDS',$incomeCounts);array_unshift($p['charts'],self::chart('Family monthly income from PDS',$incomeCounts));
        $p['notes'][]='Current family-income summaries read saved PDS records automatically across education levels. Blank income is Not recorded; no income amount, household size, SES category or confidence is guessed.';
        $p['notes'][]='School-approved SES classification cutoffs or scoring rules have not been supplied. Low/Middle/High labels are previously recorded estimates, not newly verified classifications. Unclassified records await approved rules. PDS edits invalidate older estimates in the current report. No stored prediction is overwritten.';
        $p['notes'][]='The report date is the PDS update date for unclassified current records, or the later recorded prediction date when a current estimate exists. Historical income is unavailable if the current PDS was edited after the selected prediction.';
        $breakdown=[];foreach($rows as $r){$key=implode('|',[$r['grade']??self::MISSING,$r['section']??self::MISSING,$r['school_year']??self::MISSING]);if(!isset($breakdown[$key]))$breakdown[$key]=[$r['grade']??self::MISSING,$r['section']??self::MISSING,$r['school_year']??self::MISSING,0,0,0,0,0];$index=array_search($r['category'],['Low','Middle','High','Unclassified'],true);$breakdown[$key][3+($index===false?3:$index)]++;$breakdown[$key][7]++;}
        $p['tables'][]=self::table('Grade, section and school-year breakdown',['Grade','Section','School year','Low','Middle','High','Unclassified / other','Total'],array_values($breakdown));
        if($p['filters']['include_records']){$detail=[];foreach($rows as $r)$detail[]=[$r['identifier']??self::MISSING,$r['name'],$r['grade']??self::MISSING,$r['section']??self::MISSING,$r['school_year']??self::MISSING,$r['category'],$r['confidence']!==null?round($r['confidence']*100,1):null,$r['date'],$r['income'],$r['classification_source']];$columns=['Student ID','Student name','Grade','Section','School year','SES','Recorded confidence (%)','Report date','Family monthly income','Classification source'];$p['tables'][]=self::table('Authorized student records',array_slice($columns,0,8),array_map(static fn($row)=>array_slice($row,0,8),$detail),true);$p['tables'][]=self::table('PDS income and classification source',['Student ID','Student name','Family monthly income','Classification source'],array_map(static fn($row)=>[$row[0],$row[1],$row[8],$row[9]],$detail),true);$p['export_columns']=$columns;$p['export_rows']=$detail;}
        $tableOrder=['PDS income and classification source'=>0,'Family monthly income from PDS'=>1,'Authorized student records'=>2,'Grade, section and school-year breakdown'=>3,'Recorded SES classifications'=>4,'Prediction confidence'=>5];usort($p['tables'],static fn($a,$b)=>($tableOrder[$a['title']]??9)<=>($tableOrder[$b['title']]??9));
        if($n)$p['findings'][]=$n.' students have a current PDS or a recorded estimate in the selected reporting scope. Low: '.$counts['Low'].', middle: '.$counts['Middle'].', high: '.$counts['High'].'.';
        $p['notes'][]='One record per student is selected within the active date/school-year scope. Current PDS records take precedence over older estimates; unchanged legacy estimates remain labelled as recorded estimates.';
        $p['recommendations'][]='Validate missing academic fields and keep socioeconomic information current before comparing cohorts.';$p['recommendations'][]='Use the aggregate distribution for support planning; individual support decisions require authorized review of the underlying information.';
    }
    private function olsatReport(array &$p,array $rows):void {
        $valid=array_values(array_filter(array_column($rows,'percentage'),static fn($v)=>$v!==null));$counts=['Under 20%'=>0,'20 to under 40%'=>0,'40 to under 60%'=>0,'60 to under 80%'=>0,'80 to 100%'=>0];foreach($valid as $v){$i=min(4,(int)floor($v/20));$counts[array_keys($counts)[$i]]++;}
        $p['metrics']=['Completed assessments'=>count($rows),'Scored assessments'=>count($valid),'Average raw score (%)'=>$valid?round(array_sum($valid)/count($valid),2):null,'Recorded SAI values'=>count(array_filter($rows,static fn($r)=>$r['sai']!==null)),'Qualified grade recorded'=>count(array_filter($rows,static fn($r)=>$r['qualified'])),'Highest raw score (%)'=>$valid?max($valid):null];
        $p['charts'][]=self::chart('Raw-score percentage distribution',$counts);$p['charts'][]=self::chart('Recorded classifications',self::group($rows,'classification'),'pie');$p['tables'][]=self::distribution('Raw-score percentage distribution',$counts);
        $grades=[];foreach($rows as $r){$key=$r['grade']??self::MISSING;if(!isset($grades[$key]))$grades[$key]=['n'=>0,'values'=>[]];$grades[$key]['n']++;if($r['percentage']!==null)$grades[$key]['values'][]=$r['percentage'];}$comparison=[];foreach($grades as $key=>$g)$comparison[]=[$key,$g['n'],count($g['values']),$g['values']?round(array_sum($g['values'])/count($g['values']),2):null];$p['tables'][]=self::table('Grade-level raw-score comparison',['Grade','Assessments','Scored','Average raw score (%)'],$comparison);
        $p['tables'][]=self::distribution('Recorded assessment classifications',self::group($rows,'classification'));
        if($p['filters']['include_records']){
            $detail=[];$measures=[];foreach($rows as $r){$id=$r['identifier']??'Appointment #'.$r['id'];$detail[]=[$id,$r['name'],$r['grade']??self::MISSING,$r['section']??self::MISSING,$r['date'],$r['score'],$r['percentage'],$r['classification']??self::MISSING];$measures[]=[$id,$r['date'],$r['school_year']??self::MISSING,$r['sai'],$r['percentile'],$r['stanine'],$r['examination']];}
            $p['tables'][]=self::table('Authorized assessment records',['Student ID / reference','Student name','Grade','Section','Exam date','Raw score','Raw score (%)','Recorded classification'],$detail,true);$p['tables'][]=self::table('Recorded standardized measures',['Student ID / reference','Exam date','School year','SAI','Percentile rank','Stanine','Assessment level / form'],$measures,true);
            $p['export_columns']=['Student ID / reference','Student name','Grade','Section','School year','Exam date','Raw score','Total items','Raw score (%)','SAI','Percentile rank','Stanine','Recorded classification','Assessment level / form'];$p['export_rows']=[];foreach($rows as $r)$p['export_rows'][]=[$r['identifier']??'Appointment #'.$r['id'],$r['name'],$r['grade']??self::MISSING,$r['section']??self::MISSING,$r['school_year']??self::MISSING,$r['date'],$r['score'],$r['items'],$r['percentage'],$r['sai'],$r['percentile'],$r['stanine'],$r['classification']??self::MISSING,$r['examination']];
        }
        $p['notes'][]='Only completed records with both an explicitly recorded OLSAT level and form are included. Generic entrance examinations are excluded. Scores come from released examination records, not PDS answers.';$p['notes'][]='The applicable OLSAT edition, norms and official scoring manual have not been provided. SAI and percentile rank are shown only if stored; they are not estimated from raw scores or stanines.';$p['notes'][]='Raw-score percentage is correct answers divided by total items, multiplied by 100. It is not a percentile rank. Recorded classifications are reproduced as entered and have not been verified against an edition-specific manual.';$p['notes'][]='Publisher reference: Pearson OLSAT 8 product and scoring overview, https://www.pearsonassessments.com/store/en/usd/p/100000003.html';
        if($rows)$p['findings'][]=count($valid).' of '.count($rows).' completed assessment records contain a valid raw score and total item count.';
        $p['recommendations'][]='Obtain the applicable edition/form and official scoring manual or publisher score report before interpreting standardized measures.';$p['recommendations'][]='Use grade comparisons as descriptive raw-score summaries, not as norm-referenced ability rankings.';
    }
    private function counselingReport(array &$p,array $rows):void {
        $statuses=self::group($rows,'status');$formats=self::group($rows,'format');$categories=['academic'=>0,'career'=>0,'personal'=>0,'social'=>0];foreach(self::group($rows,'category') as $key=>$n){$key=strtolower($key);$categories[$key]=($categories[$key]??0)+$n;}
        $p['metrics']=['Total sessions'=>count($rows),'Individual (recorded)'=>$formats['individual']??0,'Group (recorded)'=>$formats['group']??0,'Format not recorded'=>$formats[self::MISSING]??0,'Completed'=>$statuses['completed']??0,'Pending / confirmed'=>($statuses['pending']??0)+($statuses['confirmed']??0)+($statuses['rescheduled']??0),'Ongoing'=>$statuses['in_progress']??0,'Referrals'=>count(array_filter($rows,static fn($r)=>$r['referral'])),'Follow-up sessions'=>count(array_filter($rows,static fn($r)=>$r['followup'])),'Follow-up needed'=>count(array_filter($rows,static fn($r)=>$r['followup_needed']))];
        $p['tables'][]=self::distribution('Counseling concern categories',$categories);$p['tables'][]=self::distribution('Recorded session format',$formats);$p['tables'][]=self::distribution('Case status',$statuses);$p['tables'][]=self::distribution('Counselor workload',self::group($rows,'counselor'));
        $monthly=[];$yearly=[];foreach($rows as $r){if(!$r['date'])continue;$m=substr($r['date'],0,7);$y=substr($r['date'],0,4);$monthly[$m]=($monthly[$m]??0)+1;$yearly[$y]=($yearly[$y]??0)+1;}ksort($monthly);ksort($yearly);
        $p['tables'][]=self::table('Monthly sessions',['Month','Sessions'],array_map(static fn($key,$n)=>[$key,$n],array_keys($monthly),array_values($monthly)));$p['tables'][]=self::table('Yearly sessions',['Year','Sessions'],array_map(static fn($key,$n)=>[$key,$n],array_keys($yearly),array_values($yearly)));
        $p['charts'][]=self::chart('Counseling categories',$categories);$p['charts'][]=self::chart('Case status',$statuses,'pie');$p['charts'][]=self::chart('Monthly activity',array_slice($monthly,-12,null,true),'line');
        if($rows)$p['findings'][]=count($rows).' appointment/session records match the filters; '.($statuses['completed']??0).' are completed and '.($statuses['in_progress']??0).' are ongoing.';
        $p['notes'][]='General counseling reports contain aggregate counts only. Names, student IDs, concern narratives and session notes are excluded from every export.';$p['notes'][]='Individual/group counts use an explicit stored session format. Appointment ownership is not used to guess session format. Unknown formats are retained separately.';$p['notes'][]='Each counted session represents an appointment record; a group-attendance register is not available.';
        $p['recommendations'][]='Review pending cases and recorded follow-up needs through the existing authorized counseling workflow.';$p['recommendations'][]='Use monthly volumes to plan office capacity and improve recording of session format and academic metadata.';
    }
    private function examineeReport(array &$p,array $rows,array $cohort):void {
        $counts=array_merge(['Passed'=>0,'Failed'=>0,'Pending'=>0,'Absent'=>0,'Cancelled'=>0,'Unclassified'=>0],self::group($cohort,'result'));$classified=$counts['Passed']+$counts['Failed'];$unique=count(array_unique(array_column($cohort,'user_id')));
        $p['metrics']=['Total examinees'=>$unique,'Examination records'=>count($cohort),'Passed'=>$counts['Passed'],'Failed'=>$counts['Failed'],'Pending'=>$counts['Pending'],'Absent'=>$counts['Absent'],'Completed examinations'=>count(array_filter($cohort,static fn($r)=>$r['status']==='completed')),'Passing rate (%)'=>$classified?round($counts['Passed']/$classified*100,1):null,'Listed records'=>count($rows)];
        $p['charts'][]=self::chart('Examination outcomes',$counts);$p['charts'][]=self::chart('Classified completed results',['Passed'=>$counts['Passed'],'Failed'=>$counts['Failed']],'pie');$p['tables'][]=self::distribution('Examination result summary',$counts);
        if($p['filters']['include_records']){
            $main=[];$academic=[];$scores=[];foreach($rows as $i=>$r){$id=$r['identifier']??'Appointment #'.$r['id'];$main[]=[$i+1,$id,$r['name'],$r['percentage']!==null?$r['percentage'].'%':($r['score']!==null?(string)$r['score']:null),$r['result']];$academic[]=[$id,$r['grade']??self::MISSING,$r['section']??self::MISSING,$r['school_year']??self::MISSING,$r['examination'],$r['date'],$r['email']?:self::MISSING];$scores[]=[$id,$r['score'],$r['items'],$r['passing_score']??'Not configured',$r['percentage'],$r['completed_at']??self::MISSING];}
            $p['tables'][]=self::table('List of '.(in_array($p['filters']['result'],['passed','failed','pending','absent'],true)?$p['filters']['result'].' ':'').'examinees',['No.','Student ID / reference','Examinee name','Score','Result'],$main,true);$p['tables'][]=self::table('Academic and examination details',['Student ID / reference','Grade','Section','School year','Examination','Exam date','Email'],$academic,true);$p['tables'][]=self::table('Scores and completion details',['Student ID / reference','Total score','Total items','Passing score','Percentage (%)','Date completed'],$scores,true);
            $p['export_columns']=['Student ID / reference','Examinee name','Grade','Section','School year','Examination','Exam date','Total score','Total items','Passing score','Percentage (%)','Result','Date completed','Email'];$p['export_rows']=[];foreach($rows as $r)$p['export_rows'][]=[$r['identifier']??'Appointment #'.$r['id'],$r['name'],$r['grade']??self::MISSING,$r['section']??self::MISSING,$r['school_year']??self::MISSING,$r['examination'],$r['date'],$r['score'],$r['items'],$r['passing_score']??'Not configured',$r['percentage'],$r['result'],$r['completed_at']??self::MISSING,$r['email']?:self::MISSING];
        }
        $p['notes'][]='Examination summary covers all result statuses matching the year, examination, date, grade, section, category and search filters. The result-status selection applies to the listed records.';$p['notes'][]='Passing rate uses Passed / (Passed + Failed) among classified completed examinations. Pending, absent, cancelled and unclassified records are excluded from that denominator.';$p['notes'][]='Stored passed/failed results are authoritative for completed examinations. A recorded qualification can identify a completed pass; no unrecorded failure is inferred. Pending and absent records are never marked failed by reporting.';$p['notes'][]='The existing result-release workflow classifies completion using recorded qualification. No numeric passing mark or official examinee-number field is configured; missing values remain unavailable. Appointment references are explicitly labelled.';$p['notes'][]='Actual examination date is used when stored, otherwise the scheduled date. Date completed is not substituted with an unrelated update timestamp.';
        if($cohort)$p['findings'][]=$counts['Passed'].' passed and '.$counts['Failed'].' failed recorded completed examinations; '.$counts['Pending'].' records remain pending and '.$counts['Absent'].' are marked absent.';
        $p['recommendations'][]='Review unclassified completed records and confirm the school-approved criteria before publishing results.';$p['recommendations'][]='Complete missing examination, school-year and section data to improve filtered reporting.';
    }
}
