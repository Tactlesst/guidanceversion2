<?php
final class PDSDrawnSignature {
    public static function save($data,string $uploads,int $uid,callable $created): string {
        if(!is_string($data)||strlen($data)>2800000||!str_starts_with($data,'data:image/png;base64,'))throw new InvalidArgumentException('Choose a valid PNG signature drawing.');
        $bytes=base64_decode(substr($data,22),true);$image=$bytes!==false?@getimagesizefromstring($bytes):false;
        if(!$bytes||strlen($bytes)>2*1024*1024||!$image||($image['mime']??'')!=='image/png'||$image[0]<32||$image[1]<20||$image[0]>4096||$image[1]>2048)throw new InvalidArgumentException('The signature drawing is invalid or too large. Draw it again.');
        $directory=$uploads.'/pds_signatures';if(!is_dir($directory)&&!mkdir($directory,0775,true))throw new RuntimeException('Signature storage is unavailable.');
        $name=$uid.'_'.bin2hex(random_bytes(24)).'.png';$path=$directory.'/'.$name;
        $handle=fopen($path,'xb');if(!$handle)throw new RuntimeException('The signature could not be saved.');$created($path);
        try{if(fwrite($handle,$bytes)!==strlen($bytes))throw new RuntimeException('The signature could not be saved completely.');}finally{fclose($handle);}
        return 'uploads/pds_signatures/'.$name;
    }
}
