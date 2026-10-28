<?php
final class EmailConfiguration {
    public static function load():array {
        $app=require __DIR__.'/../config/app.php';$path=__DIR__.'/../config/email.local.php';$local=is_file($path)?require $path:[];
        $config=array_merge(['enabled'=>false,'smtp_host'=>'','smtp_port'=>587,'smtp_secure'=>'tls','smtp_auth'=>true,'smtp_username'=>'','smtp_password'=>'','from_email'=>'','from_name'=>'SRCB Guidance Office','is_html'=>true,'charset'=>'UTF-8','base_url'=>$app['base_url']],is_array($local)?$local:[]);
        foreach(['smtp_host'=>'GUIDANCE_SMTP_HOST','smtp_username'=>'GUIDANCE_SMTP_USERNAME','smtp_password'=>'GUIDANCE_SMTP_PASSWORD','from_email'=>'GUIDANCE_MAIL_FROM'] as $key=>$env){$value=getenv($env);if($value!==false&&$value!=='')$config[$key]=$value;}
        return $config;
    }
    public static function ready(?array $c=null):bool {
        $c=$c??self::load();return !empty($c['enabled'])&&!empty($c['smtp_host'])&&filter_var($c['from_email']??'',FILTER_VALIDATE_EMAIL)&&(!($c['smtp_auth']??true)||(!empty($c['smtp_username'])&&!empty($c['smtp_password'])))&&is_file(__DIR__.'/../PHPMailer/src/PHPMailer.php');
    }
    public static function masked($email):string {if(!filter_var($email,FILTER_VALIDATE_EMAIL))return 'Not configured';[$local,$domain]=explode('@',$email,2);return mb_substr($local,0,1).'•••@'.$domain;}
    public static function save(array $post):void {
        $old=self::load();$host=trim((string)($post['smtp_host']??''));$port=filter_var($post['smtp_port']??0,FILTER_VALIDATE_INT);$secure=$post['smtp_secure']??'';$from=trim((string)($post['from_email']??''));$username=trim((string)($post['smtp_username']??''));$name=trim((string)($post['from_name']??''));$password=(string)($post['smtp_password']??'');
        if(!preg_match('/^[a-zA-Z0-9.-]{1,253}$/D',$host)||!$port||$port<1||$port>65535||!in_array($secure,['tls','ssl'],true)||!filter_var($from,FILTER_VALIDATE_EMAIL)||strlen($username)>255||mb_strlen($name)>150||strlen($password)>1024)throw new InvalidArgumentException('Check the mail server, port, encryption, sender and account settings.');
        $config=['enabled'=>!empty($post['mail_enabled']),'smtp_host'=>$host,'smtp_port'=>$port,'smtp_secure'=>$secure,'smtp_auth'=>true,'smtp_username'=>$username,'smtp_password'=>$password!==''?$password:$old['smtp_password'],'from_email'=>$from,'from_name'=>$name?:'SRCB Guidance Office'];
        if($config['enabled']&&($username===''||$config['smtp_password']===''))throw new InvalidArgumentException('Enter the mail account and its app password before enabling email.');
        $path=__DIR__.'/../config/email.local.php';$temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';$data="<?php\n// Private SMTP settings. Protected from web access by config/.htaccess.\nreturn ".var_export($config,true).";\n";
        if(file_put_contents($temp,$data,LOCK_EX)!==strlen($data))throw new RuntimeException('Mail settings could not be saved.');@chmod($temp,0600);if(!rename($temp,$path)){@unlink($temp);throw new RuntimeException('Mail settings could not be saved.');}
    }
}
