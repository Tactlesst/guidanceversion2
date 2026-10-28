<?php
require_once __DIR__.'/EmailConfiguration.php';
final class EmailTransport {
    private array $config;
    public function __construct(?array $config=null){$this->config=$config??EmailConfiguration::load();}
    public function send(string $email,string $subject,string $html,string $messageKey):void {
        $c=$this->config;if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Recipient address is invalid.');
        foreach(['Exception','SMTP','PHPMailer'] as $class)require_once __DIR__.'/../PHPMailer/src/'.$class.'.php';
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=$c['smtp_host'];$mail->Port=(int)$c['smtp_port'];$mail->SMTPAuth=(bool)$c['smtp_auth'];$mail->Username=$c['smtp_username'];$mail->Password=$c['smtp_password'];$mail->Timeout=12;$mail->Timelimit=20;$mail->SMTPDebug=0;
        $mail->SMTPSecure=($c['smtp_secure']??'tls')==='ssl'?'ssl':(($c['smtp_secure']??'tls')==='tls'?'tls':'');
        $mail->setFrom($c['from_email'],$c['from_name']);$mail->addAddress($email);$mail->CharSet='UTF-8';$mail->isHTML(true);$mail->Subject=str_replace(["\r","\n"],' ',$subject);$mail->Body=$html;$mail->AltBody=html_entity_decode(strip_tags(str_replace(['</p>','<br>','<br/>','<br />'],"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8');$domain=substr(strrchr($c['from_email'],'@'),1);$mail->MessageID='<'.hash('sha256',$messageKey).'@'.$domain.'>';
        if(str_contains($html,'cid:srcb-school-seal'))$mail->addEmbeddedImage(__DIR__.'/../assets/images/srcblogo.png','srcb-school-seal','school-seal.png');
        try{$mail->send();}catch(Throwable $e){$error=strtolower($mail->ErrorInfo.' '.$e->getMessage());throw new RuntimeException(str_contains($error,'auth')?'Mail server authentication failed. Check the account and app password.':(str_contains($error,'connect')?'Mail server connection failed. Check the server, port and encryption.':'The mail server did not confirm delivery. Review mail settings before retrying.'));}
    }
}
