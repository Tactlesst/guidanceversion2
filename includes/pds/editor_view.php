<?php
require_once __DIR__.'/editor_schema.php';
final class PDSEditorView {
    public static array $data=[];
    public static array $options=[];
    public const CONDITIONALS=['religion_other'=>'religion','child_residing_others'=>'child_residing','birth_order_others'=>'birth_order','relatives_others'=>'relatives_at_home','financial_support_others'=>'financial_support','leisure_activities_others'=>'leisure_activities','guardian_relation_others'=>'guardian_relation','citizenship_others'=>'citizenship','father_education_other'=>'father_education','mother_education_other'=>'mother_education','health_problem_details'=>'health_problem'];
    public static function e($value): string {return htmlspecialchars(PDSEditorSchema::text($value),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function field(string $name,string $label,string $type='text',?array $options=null): void {
        $value=PDSEditorSchema::text(self::$data[$name]??'');$id='pe-'.$name;
        if($options===null&&$type==='text'&&array_key_exists($name,self::$options))$options=self::$options[$name];
        $conditional='';if(isset(self::CONDITIONALS[$name])){$source=self::CONDITIONALS[$name];$allowed=$name==='health_problem_details'?['yes']:['other','others'];$active=false;foreach(PDSEditorSchema::tokens(self::$data[$source]??'') as $token)if(in_array(mb_strtolower($token),$allowed,true))$active=true;$conditional=' data-other-for="'.$source.'" data-other-values="'.implode('|',$allowed).'"'.($active?'':' hidden');}
        echo '<label class="pe-field" for="'.$id.'"'.$conditional.'><span>'.self::e($label).'</span>';
        if($options!==null){echo '<select id="'.$id.'" name="'.$name.'"><option value="">Select / leave blank</option>';
            foreach($options as $option)echo '<option value="'.self::e($option).'"'.($option===$value?' selected':'').'>'.self::e($option).'</option>';
            if($value!==''&&!in_array($value,$options,true))echo '<option selected value="'.self::e($value).'">Recorded: '.self::e($value).'</option>';echo '</select>';
            if(!$options)echo '<small>No active choices are configured for your department. Your saved answer is kept.</small>';
        }elseif($type==='textarea')echo '<textarea id="'.$id.'" name="'.$name.'" rows="2">'.self::e($value).'</textarea>';
        else {if($type==='date'&&$value==='0000-00-00')$value='';echo '<input id="'.$id.'" type="'.$type.'" name="'.$name.'" value="'.self::e($value).'"'.($type==='number'?' min="0" max="'.($name==='age'?'150':'9999').'" step="1"':'').'>';}
        echo '</label>';
    }
    public static function choice(string $name,string $label): void {self::field($name,$label,'text',PDSEditorSchema::choices()[$name]);}
    public static function multiple(string $name,string $label): void {
        $selected=PDSEditorSchema::tokens(self::$data[$name]??'');$options=PDSEditorSchema::choices()[$name];
        echo '<fieldset class="pe-choices"><legend>'.self::e($label).'</legend><input type="hidden" name="'.$name.'[]" value="">';
        foreach(array_unique(array_merge($options,$selected)) as $index=>$option)echo '<label><input type="checkbox" name="'.$name.'[]" value="'.self::e($option).'"'.(in_array($option,$selected,true)?' checked':'').'><span>'.self::e((in_array($option,$options,true)?'':'Recorded: ').$option).'</span></label>';
        echo '</fieldset>';
    }
    public static function related(string $group,array $rows): void {
        $labels=match($group){'siblings'=>['sibling_name'=>'Name','sibling_age'=>'Age','sibling_school'=>'School attended','sibling_status'=>'Status','sibling_occupation'=>'Occupation'],'organizations'=>['school_year'=>'School year','organization_name'=>'Organization / club','designation'=>'Designation'],'tests'=>['test_name'=>'Test taken','test_date'=>'Date taken','test_rating'=>'Rating']};
        $minimum=match($group){'siblings'=>0,'organizations'=>4,'tests'=>4};
        echo '<div class="pe-related" data-related="'.$group.'" data-next-index="'.max($minimum,count($rows)).'"><div class="pe-related-rows">';
        for($index=0;$index<max($minimum,count($rows));$index++)self::row($group,$index,$rows[$index]??[],$labels);
        echo '</div>';if($group==='siblings')echo '<p class="pe-help" data-related-empty'.($rows?' hidden':'').'>No siblings added yet. Choose Add sibling if you want to enter one.</p>';
        echo '<button type="button" class="pe-add-row" data-add-row>Add '.match($group){'siblings'=>'sibling','organizations'=>'organization','tests'=>'test result'}.'</button><template>';
        self::row($group,'__INDEX__',[],$labels);echo '</template><p class="pe-help">'.($group==='siblings'?'Only the siblings you add will appear here. ':'Leave unused rows blank. ').'To delete a saved row, choose Remove and then save your PDS.</p></div>';
    }
    private static function row(string $group,$index,array $row,array $labels): void {
        echo '<div class="pe-related-row"><input type="hidden" data-row-id name="'.$group.'['.$index.'][id]" value="'.self::e($row['id']??0).'">';
        foreach($labels as $field=>$label){
            $type=$field==='test_date'?'date':($field==='sibling_age'?'number':'text');$name=$group.'['.$index.']['.$field.']';$value=PDSEditorSchema::text($row[$field]??'');
            echo '<label><span>'.self::e($label).'</span>';
            if($group==='organizations'&&$field==='school_year'){
                $options=self::$options['school_year']??[];echo '<select name="'.self::e($name).'"><option value="">Select / leave blank</option>';
                foreach($options as $option)echo '<option value="'.self::e($option).'"'.($option===$value?' selected':'').'>'.self::e($option).'</option>';
                if($value!==''&&!in_array($value,$options,true))echo '<option selected value="'.self::e($value).'">Recorded: '.self::e($value).'</option>';
                echo '</select>';
            }else echo '<input type="'.$type.'" name="'.self::e($name).'" value="'.self::e($value).'"'.($type==='number'?' min="0" max="150" step="1"':'').'>';
            echo '</label>';
        }
        echo '<button type="button" data-remove-row aria-label="Remove '.self::e(match($group){'siblings'=>'sibling','organizations'=>'organization','tests'=>'test result'}).' row">Remove</button></div>';
    }
    public static function media(string $field,string $label,array $state,string $base): void {
        $url=PDSPaperDocument::media($state[$field]??'',$base,$field==='student_photo'?'photo':'signature');
        echo '<div class="pe-media"><label class="pe-field"><span>'.self::e($label).'</span><input type="file" name="'.$field.'" accept="image/jpeg,image/png,image/webp" data-image-upload><small>JPG, PNG or WebP, up to 2 MB. Leave empty to keep the saved image.</small></label>';
        if($url)echo '<img src="'.self::e($url).'" alt="Saved '.self::e(strtolower($label)).'" data-image-preview>';
        else echo '<img alt="Selected '.self::e(strtolower($label)).' preview" data-image-preview hidden>';
        if(!empty($state[$field])){if(!$url)echo '<p>A saved image reference exists, but the image is unavailable.</p>';echo '<label class="pe-remove-media"><input type="checkbox" name="remove_'.$field.'" value="1"> Remove saved '.self::e(strtolower($label)).'</label>';}
        echo '</div>';
    }
}
