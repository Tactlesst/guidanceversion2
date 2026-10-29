<?php
final class PDSEditorSchema {
    public const ADDED_COLUMNS=[
        'brothers_count'=>'SMALLINT UNSIGNED NULL','sisters_count'=>'SMALLINT UNSIGNED NULL',
        'stepbrothers_count'=>'SMALLINT UNSIGNED NULL','stepsisters_count'=>'SMALLINT UNSIGNED NULL','adopted_count'=>'SMALLINT UNSIGNED NULL',
        'father_degree'=>'VARCHAR(150) NULL','mother_degree'=>'VARCHAR(150) NULL',
        'student_signature_name'=>'VARCHAR(200) NULL','parent_signature_name'=>'VARCHAR(200) NULL'
    ];
    public const FIELDS='school_year first_name middle_name last_name suffix nickname gender birth_date birth_place age civil_status religion nationality citizenship citizenship_others grade_level strand course year_level semester student_type home_address city_street city_purok city_barangay contact_number email emergency_contact_name emergency_relationship emergency_contact_number father_surname father_given_name father_middle_name father_contact father_occupation father_location father_type father_status father_education father_postgrad father_specialization father_degree mother_surname mother_given_name mother_middle_name mother_contact mother_occupation mother_location mother_type mother_status mother_education mother_postgrad mother_specialization mother_degree guardian_name guardian_address guardian_contact guardian_relationship guardian_relation guardian_relation_others parents_marital child_residing child_residing_others birth_order birth_order_others relatives_at_home relatives_others total_relatives_at_home brothers_count sisters_count stepbrothers_count stepsisters_count adopted_count family_income residence_type languages_spoken financial_support financial_support_others leisure_activities leisure_activities_others special_talents preschool_school preschool_awards preschool_year gradeschool_school gradeschool_awards gradeschool_year highschool_school highschool_awards highschool_year seniorhigh_school seniorhigh_awards seniorhigh_year height weight physical_condition health_problem health_problem_details last_doctor_visit doctor_visit_reason general_condition baptism_status baptism_date baptism_church communion_status communion_date communion_church confirmation_status confirmation_date confirmation_church student_signature_name student_date_signed parent_signature_name parent_date_signed privacy_agreement';
    public const MULTIPLE=['father_postgrad','mother_postgrad','relatives_at_home','financial_support','leisure_activities','languages_spoken'];
    public const RELATED=[
        'siblings'=>['table'=>'pds_siblings','fields'=>['sibling_name'=>255,'sibling_age'=>150,'sibling_school'=>255,'sibling_status'=>100,'sibling_occupation'=>255]],
        'organizations'=>['table'=>'pds_organizations','fields'=>['school_year'=>50,'organization_name'=>255,'designation'=>255]],
        'tests'=>['table'=>'pds_test_results','fields'=>['test_name'=>255,'test_date'=>0,'test_rating'=>100]]
    ];
    public static function choices(): array {
        $education=['Some Elementary','Elementary','Some High School','High School','Some College','College Degree','Postgraduate studies','Others'];
        return [
            'gender'=>['Male','Female'],'student_type'=>['New','Transferee','Returnee','Old'],
            'father_location'=>['Overseas','Local'],'mother_location'=>['Overseas','Local'],
            'father_type'=>['Private','Government'],'mother_type'=>['Private','Government'],
            'father_status'=>['Employed','Unemployed','Deceased','Retired','Disabled'],'mother_status'=>['Employed','Unemployed','Deceased','Retired','Disabled'],
            'father_education'=>$education,'mother_education'=>$education,'father_postgrad'=>['MA/MS','PhD'],'mother_postgrad'=>['MA/MS','PhD'],
            'parents_marital'=>['Sacramental Marriage','Civil Marriage','Legally Separated','Annulled','Unmarried'],
            'child_residing'=>['Both Parents','Father Only','Mother Only','Others'],
            'birth_order'=>['1st','2nd','3rd','4th','5th','6th','7th','Others'],
            'relatives_at_home'=>['Grandparents','Uncle','Aunt','Cousins','Others'],
            'family_income'=>['Below P10,000','P 10,001 - P13,000','P 13,001 - P15,000','P 15,001 - P18,000','P 18,001 - P21,000','P 21,001 - P24,000','P 24,001 - P27,000','P 27,001 - P30,000','Above P30,000'],
            'residence_type'=>['Rented','Owned'],'financial_support'=>['Parents','Grandparents','Others'],
            'leisure_activities'=>['Listening to radio','Watching TV','Picnic','Others'],
            'guardian_relation'=>['Sister/Brother','Aunt/Uncle','Landlord/Landlady','Grandparents','Others'],
            'physical_condition'=>['Good','Fair','Poor'],'health_problem'=>['Yes','No'],
            'general_condition'=>['Good Condition','Under Medication','With Special Care / Attention'],
            'baptism_status'=>['Yes','No'],'communion_status'=>['Yes','No'],'confirmation_status'=>['Yes','No']
        ];
    }
    public static function text($value): string {return is_array($value)?implode(', ',array_map([self::class,'text'],$value)):trim((string)($value??''));}
    public static function tokens($value): array {$value=self::text($value);$json=json_decode($value,true);return is_array($json)?array_map('strval',$json):array_values(array_filter(array_map('trim',preg_split('/[,;|]+/u',$value))));}
}
