<?php
/** PSGC hierarchy and two addresses stored in the existing home_address TEXT column. */
final class PDSAddress {
    private static ?array $records=null;
    public static function records(): array {
        if(self::$records===null){$raw=json_decode(file_get_contents(__DIR__.'/../../assets/data/psgc.json'),true,512,JSON_THROW_ON_ERROR);self::$records=array_column($raw['records'],null,'code');}
        return self::$records;
    }
    private static function ancestry(string $code): array {
        $records=self::records();$out=[];$seen=[];
        while(isset($records[$code])&&!isset($seen[$code])){$seen[$code]=true;$row=$records[$code];$out[]=$row;$code=$row['parent'];}
        return $out;
    }
    public static function context(string $code): array {
        $out=['region'=>'','province'=>'__none__'];foreach(self::ancestry($code) as $row){if($row['type']==='region')$out['region']=$row['code'];if(in_array($row['type'],['province','special_geographic_area'],true))$out['province']=$row['code'];}return $out;
    }
    public static function choices(string $level,array $scope=[]): array {
        $rows=[];$records=self::records();
        foreach($records as $row){$type=$row['type'];
            if($level==='region'&&$type==='region')$rows[]=['code'=>$row['code'],'name'=>$row['name']];
            elseif($level==='province'&&in_array($type,['province','special_geographic_area'],true)&&$row['parent']===($scope['region']??''))$rows[]=['code'=>$row['code'],'name'=>$row['name'].($type==='special_geographic_area'?' (geographic area)':'')];
            elseif($level==='city'&&!in_array($type,['region','province','special_geographic_area','barangay'],true)){
                $context=self::context($row['code']);if($context['region']!==($scope['region']??'')||$context['province']!==($scope['province']??''))continue;
                $name=$row['name'];if($type==='submunicipality')$name=($records[$row['parent']]['name']??'').' — '.$name;
                $rows[]=['code'=>$row['code'],'name'=>$name];
            }elseif($level==='barangay'&&$type==='barangay'){
                $city=$scope['city']??'';
                if($row['parent']===$city)$rows[]=['code'=>$row['code'],'name'=>$row['name']];
                elseif($city!==''&&in_array($city,array_column(self::ancestry($row['parent']),'code'),true))$rows[]=['code'=>$row['code'],'name'=>($records[$row['parent']]['name']??'').' — '.$row['name']];
            }
        }
        usort($rows,static fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
        if($level==='province'&&!empty($scope['region'])&&self::choices('city',['region'=>$scope['region'],'province'=>'__none__']))array_unshift($rows,['code'=>'__none__','name'=>'No province — cities directly under region']);
        return $rows;
    }
    public static function decode($value): array {
        $text=is_string($value)?$value:'';$data=json_decode($text,true);
        return is_array($data)&&($data['pds_addresses']??null)===1&&is_array($data['residential']??null)&&is_array($data['permanent']??null)?$data:['pds_addresses'=>1,'residential'=>['mode'=>'keep','legacy'=>$text],'permanent'=>['mode'=>'keep','legacy'=>''],'same'=>false];
    }
    public static function line(array $address,bool $includeRegion=true,bool $labelPurok=false,bool $includePurok=true): string {
        if(($address['mode']??'keep')==='keep')return is_scalar($address['legacy']??null)?(string)$address['legacy']:'';
        $parts=[];foreach(['street','purok','barangay_name','city_name','province_name','region_name'] as $field){
            if($field==='region_name'&&!$includeRegion)continue;
            if($field==='purok'&&!$includePurok)continue;
            if(!is_scalar($address[$field]??null)||trim((string)$address[$field])==='')continue;
            $value=trim((string)$address[$field]);
            if($field==='purok'&&$labelPurok&&!preg_match('/^(?:purok|sitio)\b/iu',$value))$value='Purok/Sitio '.$value;
            $parts[]=$value;
        }return implode(', ',$parts);
    }
    public static function display($value,bool $both=true): string {
        $decoded=json_decode((string)$value,true);if(!is_array($decoded)||($decoded['pds_addresses']??null)!==1)return (string)($value??'');
        if(!is_array($decoded['residential']??null)||!is_array($decoded['permanent']??null))return (string)$value;
        $res=self::line($decoded['residential'],false,false,false);$per=self::line($decoded['permanent'],false,false,false);
        $normalize=static fn($text)=>mb_strtolower(preg_replace('/\s+/u',' ',trim($text)));
        if(!$both||$per===''||$normalize($res)===$normalize($per))return $res;
        return 'Residential: '.$res."\nPermanent: ".$per;
    }
    private static function scalar($value,string $label,int $max=255): string {
        if(!is_string($value)||mb_strlen($value)>$max)throw new InvalidArgumentException('Check the '.$label.'.');return trim($value);
    }
    private static function validate(array $entry,string $label): array {
        if(($entry['mode']??'')==='keep')return ['mode'=>'keep','legacy'=>self::scalar($entry['legacy']??'',$label.' saved address',2000)];
        if(($entry['mode']??'')!=='structured')throw new InvalidArgumentException('Choose how to enter the '.$label.' address.');
        $address=['mode'=>'structured'];foreach(['region','province','city','barangay','street','purok'] as $key)$address[$key]=self::scalar($entry[$key]??'',$label.' '.$key);
        foreach(['region','province','city','barangay'] as $key){$scope=['region'=>$address['region'],'province'=>$address['province'],'city'=>$address['city']];$choice=array_column(self::choices($key,$scope),'name','code');if($address[$key]===''||!isset($choice[$address[$key]]))throw new InvalidArgumentException('Select a valid '.$key.' for the '.$label.' address.');$address[$key.'_name']=$address[$key]==='__none__'?'':$choice[$address[$key]];}
        return $address;
    }
    public static function apply(array $post): array {
        if(($post['_pds_addresses']??'')!=='1')return $post;
        $input=$post['addresses']??null;if(!is_array($input)||!is_array($input['residential']??null))throw new InvalidArgumentException('The address fields were not received completely.');
        $res=self::validate($input['residential'],'residential');$same=($input['same']??'')==='1';
        if(!$same&&!is_array($input['permanent']??null))throw new InvalidArgumentException('The permanent address fields were not received completely.');
        $per=$same?$res:self::validate($input['permanent'],'permanent');
        $post['home_address']=json_encode(['pds_addresses'=>1,'residential'=>$res,'permanent'=>$per,'same'=>$same],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if($res['mode']==='structured'){$post['city_street']=$res['street'];$post['city_purok']=$res['purok'];$post['city_barangay']=$res['barangay_name'];}
        return $post;
    }
}

