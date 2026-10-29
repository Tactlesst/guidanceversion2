<?php
/** Whole-school closures are separate from holidays and calendar activities. */
class SchoolClosure {
    private $db;
    public function __construct(PDO $db){$this->db=$db;}
    public function getAll(): array {
        return $this->db->query('SELECT id,reason,start_date,end_date FROM school_closures WHERE is_active=1 ORDER BY start_date,id')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function forDate(string $date) {
        $q=$this->db->prepare('SELECT id,reason,start_date,end_date FROM school_closures WHERE is_active=1 AND start_date<=? AND end_date>=? ORDER BY id DESC LIMIT 1');$q->execute([$date,$date]);return $q->fetch(PDO::FETCH_ASSOC)?:false;
    }
    public function forRange(string $start,string $end) {
        $q=$this->db->prepare('SELECT id,reason,start_date,end_date FROM school_closures WHERE is_active=1 AND start_date<=? AND end_date>=? ORDER BY start_date LIMIT 1');$q->execute([$end,$start]);return $q->fetch(PDO::FETCH_ASSOC)?:false;
    }
    public function calendar(string $start,string $end): array {
        $q=$this->db->prepare('SELECT id,reason,start_date,end_date FROM school_closures WHERE is_active=1 AND start_date<=? AND end_date>=? ORDER BY start_date,id');$q->execute([$end,$start]);$days=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $closure){for($date=max($start,$closure['start_date']);$date<=min($end,$closure['end_date']);$date=date('Y-m-d',strtotime($date.' +1 day')))$days[$date]=$closure;}
        return $days;
    }
    public function create(array $input,int $userId): int {
        $reason=$input['closure_reason']??'';
        if(!is_string($reason)||trim($reason)===''||mb_strlen($reason)>100)throw new InvalidArgumentException('Enter a closure reason of 1 to 100 characters.');
        $dates=[];
        foreach(['closure_start','closure_end'] as $field){$date=$input[$field]??'';$parsed=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Choose valid closure dates.');$dates[$field]=$parsed;}
        $start=$dates['closure_start'];$end=$dates['closure_end'];
        if($start->format('Y-m-d')<=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d'))throw new InvalidArgumentException('School closures must start tomorrow or later.');
        if($end<$start)throw new InvalidArgumentException('The end date must be on or after the start date.');
        if($start->diff($end)->days>365)throw new InvalidArgumentException('Choose a closure of one year or less.');
        if($this->forRange($start->format('Y-m-d'),$end->format('Y-m-d')))throw new InvalidArgumentException('These dates already include a school closure. Reopen that closure first if you need to change it.');
        $q=$this->db->prepare('INSERT INTO school_closures (reason,start_date,end_date,created_by) VALUES (?,?,?,?)');$q->execute([trim($reason),$start->format('Y-m-d'),$end->format('Y-m-d'),$userId]);return (int)$this->db->lastInsertId();
    }
    public function reopen($id,int $userId): bool {
        if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new InvalidArgumentException('Choose a valid school closure.');
        $q=$this->db->prepare('UPDATE school_closures SET is_active=0,reopened_by=?,reopened_at=CURRENT_TIMESTAMP WHERE id=? AND is_active=1');$q->execute([$userId,(int)$id]);return $q->rowCount()>0;
    }
}
