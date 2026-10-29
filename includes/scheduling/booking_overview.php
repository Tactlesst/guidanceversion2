<?php
/** Calendar counts use the same capacity statuses as each service's booking rules. */
function scheduleBookingOverview(PDO $db, string $start, string $end, array $counselingLimits): array {
    $days=[];
    for($date=$start;$date<=$end;$date=date('Y-m-d',strtotime($date.' +1 day'))){
        $days[$date]=[
            'counseling'=>['booked'=>0,'used'=>0,'limit'=>$counselingLimits[$date]??4,'custom'=>isset($counselingLimits[$date])],
            'entrance_exam'=>['booked'=>0,'used'=>0,'limit'=>null,'custom'=>false]
        ];
    }
    $stmt=$db->prepare('SELECT date,max_appointments FROM entrance_exam_daily_limits WHERE date BETWEEN ? AND ?');
    $stmt->execute([$start,$end]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$days[$row['date']]['entrance_exam']['limit']=(int)$row['max_appointments'];$days[$row['date']]['entrance_exam']['custom']=true;}
    foreach([
        ['counseling','counseling_appointments','appointment_date',"'confirmed','pending','in_progress','completed','missed'"],
        ['entrance_exam','entrance_exam_appointments','preferred_date',"'pending','confirmed','rescheduled','awaiting_results'"]
    ] as [$service,$table,$column,$capacityStatuses]){
        $stmt=$db->prepare("SELECT $column AS booking_date,COUNT(*) AS booked,SUM(CASE WHEN status IN ($capacityStatuses) THEN 1 ELSE 0 END) AS used FROM $table WHERE $column BETWEEN ? AND ? AND status <> 'cancelled' GROUP BY $column");
        $stmt->execute([$start,$end]);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$days[$row['booking_date']][$service]['booked']=(int)$row['booked'];$days[$row['booking_date']][$service]['used']=(int)$row['used'];}
    }
    return $days;
}

function validateScheduleBookingLimit(array $input, string $minimumDate): array {
    $service=$input['booking_service']??'counseling';
    if(!is_string($service)||!in_array($service,['counseling','entrance_exam'],true))throw new InvalidArgumentException('Choose a valid booking service.');
    $date=$input['limit_date']??'';
    $parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
    if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Choose a valid booking-limit date.');
    if($date<$minimumDate)throw new InvalidArgumentException('Booking limits can be changed for tomorrow onward. Today and past dates are for viewing.');
    $maximum=$service==='entrance_exam'?15:20;
    $limit=$input['max_appointments']??null;
    if(isset($input['set_daily_limit'])&&(!is_scalar($limit)||!preg_match('/^[1-9][0-9]*$/D',(string)$limit)||(int)$limit>$maximum))throw new InvalidArgumentException('Choose a daily limit from 1 to '.$maximum.'.');
    return [$service,$date,(int)$limit];
}
