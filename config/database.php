<?php
$appConfig=require __DIR__.'/app.php';date_default_timezone_set($appConfig['timezone']);
class Database {
    private $conn=null;
    public static function settings():array{return (require __DIR__.'/app.php')['database'];}
    public function getConnection():PDO {
        if($this->conn instanceof PDO)return $this->conn;
        $c=self::settings();
        $this->conn=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['name'].';charset=utf8mb4',$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->conn->exec("SET time_zone = '+08:00'");
        return $this->conn;
    }
}
