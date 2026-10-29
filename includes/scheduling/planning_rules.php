<?php
function validatePlannedSchedule(array $input, ?string $today=null): ?string {
    $zone=new DateTimeZone('Asia/Manila');$today=$today??(new DateTimeImmutable('now',$zone))->format('Y-m-d');
    $dates=[];
    foreach(['start_date','end_date'] as $field){
        $value=$input[$field]??'';if(!is_string($value))return 'Choose valid start and end dates.';
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,$zone);
        if(!$date||$date->format('Y-m-d')!==$value)return 'Choose valid start and end dates.';$dates[$field]=$value;
    }
    if($dates['start_date']<=$today||$dates['end_date']<=$today)return 'Schedules must start tomorrow or later. Today and past dates are for viewing only.';
    if($dates['end_date']<$dates['start_date'])return 'The end date must be on or after the start date.';
    return null;
}
function validateScheduleWrite(array $input, ?PDO $db=null): ?string {
    if($message=validatePlannedSchedule($input))return $message;
    $title=$input['title']??'';
    if(!is_string($title)||trim(strip_tags($title))===''||mb_strlen($title)>200)return 'Enter an event title of 1 to 200 characters.';
    if(!in_array($input['event_type']??'event',['event','pds_period','entrance_exam','counseling'],true))return 'Choose a valid event type.';
    foreach(['start_time','end_time'] as $field){$value=$input[$field]??($field==='start_time'?'08:00':'17:00');if(!is_string($value)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$value))return 'Choose valid start and end times.';}
    if($input['end_date'].' '.($input['end_time']??'17:00')<=$input['start_date'].' '.($input['start_time']??'08:00'))return 'The end date and time must be after the start date and time.';
    if($db){require_once __DIR__.'/../../classes/scheduling/SchoolClosure.php';if($closure=(new SchoolClosure($db))->forRange($input['start_date'],$input['end_date']))return 'School closed: '.$closure['reason'].'. Choose dates outside the closure.';}
    return validateScheduleClosureRange($input,$db?static function($date)use($db){require_once __DIR__.'/../../classes/scheduling/Holiday.php';return (new Holiday($db))->isHoliday($date);}:static fn($date)=>false);
}

function validateScheduleClosureRange(array $input, callable $isHoliday): ?string {
    $start=DateTimeImmutable::createFromFormat('!Y-m-d',$input['start_date']??'');
    $end=DateTimeImmutable::createFromFormat('!Y-m-d',$input['end_date']??'');
    if(!$start||!$end)return 'Choose valid start and end dates.';
    for($day=$start;$day<=$end;$day=$day->modify('+1 day')){
        $date=$day->format('Y-m-d');
        if($day->format('w')==='0')return 'Events cannot be scheduled on Sundays ('.$day->format('M j, Y').'). Choose a date range that excludes Sundays and holidays.';
        if($isHoliday($date))return 'Events cannot be scheduled on holidays ('.$day->format('M j, Y').'). Choose a date range that excludes Sundays and holidays.';
    }
    return null;
}
