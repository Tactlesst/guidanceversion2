<?php
require_once __DIR__.'/OfficePolicy.php';
require_once __DIR__.'/NotificationService.php';
require_once __DIR__.'/StaffActivity.php';
require_once __DIR__.'/../includes/reporting/SchoolReportService.php';
/** One approval authorizes one generated output, for an exact owner/format/scope. */
final class ApprovalRequestBlocked extends RuntimeException {
 public int $retryAfter;public ?int $pendingId;
 public function __construct(string $message,int $retryAfter=0,?int $pendingId=null){parent::__construct($message);$this->retryAfter=$retryAfter;$this->pendingId=$pendingId;}
}
final class ReportApproval {
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    private static function sorted($v){if(!is_array($v))return $v;if(array_keys($v)!==range(0,count($v)-1))ksort($v);foreach($v as &$x)$x=self::sorted($x);return $v;}
    public static function scope(string $resource,array $input):array {
        if(in_array($resource,SchoolReportService::KINDS,true))return SchoolReportService::filters($resource,$input);
        if($resource==='pds_version'){
            if(!is_numeric($input['version']??null)||(int)$input['version']<1||!is_numeric($input['user_id']??null)||(int)$input['user_id']<1||!in_array($input['paper']??'', ['a4','long','letter'],true))throw new InvalidArgumentException('Choose a saved PDS version and paper size.');
            return ['version'=>(int)$input['version'],'user_id'=>(int)$input['user_id'],'paper'=>$input['paper']];
        }
        if($resource==='pds'){if(!is_numeric($input['id']??null)||(int)$input['id']<1||!in_array($input['paper']??'', ['a4','long','letter'],true))throw new InvalidArgumentException('Choose a valid PDS and paper size.');return ['id'=>(int)$input['id'],'paper'=>$input['paper']];}
        if($resource==='assessment'){
            if(!in_array($input['type']??'', ['mi','ls','ls_intermediate'],true)||!is_numeric($input['id']??null)||(int)$input['id']<1)throw new InvalidArgumentException('Choose a valid assessment to print.');
            return ['type'=>$input['type'],'id'=>(int)$input['id']];
        }
        if($resource==='student_list'){
            if(!is_array($input['columns']??[])||!is_array($input['visible']??[])||count($input['visible']??[])>12)throw new InvalidArgumentException('Check the selected columns.');
            $out=[];foreach(['department','strand','program','grade','q','kind','title','grouping','groupLabel'] as $k){$v=$input[$k]??'';if(!is_string($v)||mb_strlen($v)>150)throw new InvalidArgumentException('Check the student list options.');$out[$k]=trim($v);}
            if(!in_array($out['kind'],['custom','attendance','acknowledgment','records'],true)||!in_array($out['grouping'],['program','combined'],true))throw new InvalidArgumentException('Choose a valid list layout.');
            $out['signatures']=!empty($input['signatures']);$out['columns']=[];
            foreach($input['columns']??[] as $v){if(!is_string($v)||mb_strlen($v)>40||count($out['columns'])>=3)throw new InvalidArgumentException('Check blank column headings.');$out['columns'][]=$v;}
            $out['visible']=[];foreach($input['visible']??[] as $k=>$v){if(!is_string($k)||strlen($k)>40||!is_bool($v))throw new InvalidArgumentException('Check selected columns.');$out['visible'][$k]=$v;}return $out;
        }
        throw new InvalidArgumentException('Choose a supported report or printable list.');
    }
    public static function key(string $resource,string $format,array $scope):string {
        if(!in_array($format,['preview','print','pdf','xlsx','csv'],true))throw new InvalidArgumentException('Choose an output format.');
        if(in_array($resource,['assessment','student_list','pds','pds_version'],true)&&$format!=='print')throw new InvalidArgumentException('This document supports printing only.');
        return hash('sha256',json_encode([$resource,$format,self::sorted(self::scope($resource,$scope))],JSON_THROW_ON_ERROR));
    }
    public function find(int $id,int $requester):?array {
        $q=$this->db->prepare('SELECT * FROM report_approvals WHERE id=? AND requester_id=?');$q->execute([$id,$requester]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function approved(int $uid,string $resource,string $format,array $scope):?array {
        $hash=self::key($resource,$format,$scope);$q=$this->db->prepare("SELECT * FROM report_approvals WHERE requester_id=? AND scope_hash=? AND status='approved' ORDER BY id LIMIT 1");$q->execute([$uid,$hash]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function request(int $uid,string $resource,string $format,array $scope):array {
        $actor=OfficePolicy::actor($this->db,$uid);if($actor['role']!=='guidance_advocate')throw new RuntimeException('Only advocates request report approval.');
        $scope=self::scope($resource,$scope);$hash=self::key($resource,$format,$scope);$lock='report_request_'.substr(hash('sha256',(string)$uid),0,40);
        $q=$this->db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if(!(int)$q->fetchColumn())throw new RuntimeException('Another request is being saved. Try again.');
        try{$this->db->beginTransaction();
            $q=$this->db->prepare("SELECT * FROM report_approvals WHERE requester_id=? AND status='pending' ORDER BY id LIMIT 1 FOR UPDATE");$q->execute([$uid]);$pending=$q->fetch(PDO::FETCH_ASSOC);
            if($pending){if(hash_equals($pending['scope_hash'],$hash)){$this->db->commit();return $pending;}throw new ApprovalRequestBlocked('Request #'.$pending['id'].' is still awaiting the counselor/admin. Wait for a decision before sending another request.',0,(int)$pending['id']);}
            $q=$this->db->prepare("SELECT GREATEST(0,5-TIMESTAMPDIFF(SECOND,decided_at,NOW())) FROM report_approvals WHERE requester_id=? AND status='rejected' ORDER BY decided_at DESC,id DESC LIMIT 1");$q->execute([$uid]);$wait=(int)$q->fetchColumn();if($wait>0)throw new ApprovalRequestBlocked('Your previous request was rejected. Please wait '.$wait.' seconds before sending another request.',$wait);
            $q=$this->db->prepare("SELECT * FROM report_approvals WHERE requester_id=? AND scope_hash=? AND status IN ('pending','approved') ORDER BY id DESC LIMIT 1 FOR UPDATE");$q->execute([$uid,$hash]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row){$q=$this->db->prepare("INSERT INTO report_approvals(requester_id,resource,output_format,scope_json,scope_hash,status) VALUES(?,?,?,?,?,'pending')");$q->execute([$uid,$resource,$format,json_encode($scope,JSON_THROW_ON_ERROR),$hash]);$id=(int)$this->db->lastInsertId();
                (new NotificationService($this->db))->staff(['admin'],'Report approval requested','A guidance advocate requested permission for one report print/export. Review it in Report approvals.','report_approvals',$id,'report_approval:'.$id,false);
                if(!(new StaffActivity($this->db))->record($uid,'approvals','request_report_approval'))throw new RuntimeException('The request could not be recorded.');$row=$this->find($id,$uid);
            }
            $this->db->commit();return $row;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}finally{$this->db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
    }
    public function decide(int $admin,int $id,string $decision,string $note=''):void {
        $actor=OfficePolicy::actor($this->db,$admin);if(!in_array($actor['role'],['admin','super_admin'],true))throw new RuntimeException('Only the counselor/admin can decide requests.');
        if(!in_array($decision,['approved','rejected','revoked'],true)||mb_strlen($note)>500)throw new InvalidArgumentException('Check the approval decision.');
        try{$this->db->beginTransaction();$q=$this->db->prepare('SELECT * FROM report_approvals WHERE id=? FOR UPDATE');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||($decision==='revoked'?$row['status']!=='approved':$row['status']!=='pending'))throw new RuntimeException('This request was already decided or used. Refresh the list.');
            $q=$this->db->prepare('UPDATE report_approvals SET status=?,decided_by=?,decided_at=NOW(),decision_note=? WHERE id=?');$q->execute([$decision,$admin,trim($note),$id]);
            if(!(new NotificationService($this->db))->notify($row['requester_id'],'Report request '.$decision,'Your report request was '.$decision.'. Open Report approvals to view the decision.','info','report_approvals',$id,false,null,null,'approval:'.$id.':'.$decision))throw new RuntimeException('The decision notification could not be recorded.');
            if(!(new StaffActivity($this->db))->record($admin,'approvals','decide_report_approval'))throw new RuntimeException('The decision could not be recorded.');$this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function consume(int $uid,int $id,string $resource,string $format,array $scope):void {
        $actor=OfficePolicy::actor($this->db,$uid);if($actor['role']!=='guidance_advocate')throw new RuntimeException('Approval belongs to an advocate.');
        $hash=self::key($resource,$format,$scope);
        try{$this->db->beginTransaction();$q=$this->db->prepare("UPDATE report_approvals SET status='used',used_at=NOW() WHERE id=? AND requester_id=? AND status='approved' AND scope_hash=? AND resource=? AND output_format=?");$q->execute([$id,$uid,$hash,$resource,$format]);
            if($q->rowCount()!==1)throw new RuntimeException('Approval is missing, already used, revoked, or for different report options. Request a new approval.');
            if(!(new StaffActivity($this->db))->record($uid,'approvals','use_report_approval'))throw new RuntimeException('The approved output could not be recorded.');$this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function state(int $uid):array {
        $actor=OfficePolicy::actor($this->db,$uid);if($actor['role']!=='guidance_advocate')throw new RuntimeException('Only advocates can view their request status.');
        $q=$this->db->prepare("SELECT id,resource,output_format,status,requested_at,decided_at FROM report_approvals WHERE requester_id=? AND status IN ('pending','approved') ORDER BY id DESC");$q->execute([$uid]);$active=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$this->db->prepare("SELECT id,resource,output_format,status,requested_at,decided_at FROM report_approvals WHERE requester_id=? AND decided_at>=DATE_SUB(NOW(),INTERVAL 1 DAY) ORDER BY decided_at DESC,id DESC LIMIT 50");$q->execute([$uid]);$recent=$q->fetchAll(PDO::FETCH_ASSOC);
        $pending=array_values(array_filter($active,static fn($r)=>$r['status']==='pending'));
        $q=$this->db->prepare("SELECT GREATEST(0,5-TIMESTAMPDIFF(SECOND,decided_at,NOW())) FROM report_approvals WHERE requester_id=? AND status='rejected' ORDER BY decided_at DESC,id DESC LIMIT 1");$q->execute([$uid]);$retry=(int)$q->fetchColumn();
        return ['pending'=>$pending?end($pending):null,'pending_count'=>count($pending),'retry_after'=>$retry,'active'=>$active,'recent'=>$recent];
    }
    public function listing(int $uid,string $role,int $page=1):array {
        if(!in_array($role,['admin','super_admin','guidance_advocate'],true))throw new RuntimeException('Access denied.');
        $where=$role==='guidance_advocate'?'WHERE r.requester_id=?':'';$params=$where?[$uid]:[];$q=$this->db->prepare('SELECT COUNT(*) FROM report_approvals r '.$where);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/10));$page=min(max(1,$page),$pages);
        $q=$this->db->prepare('SELECT r.*,u.first_name,u.last_name,a.first_name AS admin_first,a.last_name AS admin_last FROM report_approvals r JOIN users u ON u.id=r.requester_id LEFT JOIN users a ON a.id=r.decided_by '.$where.' ORDER BY r.id DESC LIMIT 10 OFFSET '.(($page-1)*10));$q->execute($params);
        return ['rows'=>$q->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'pages'=>$pages,'page'=>$page];
    }
}
