<?php
function pdsWizardTempTable(PDO $db,string $table):void{
 $definitions=[];foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC) as $column)$definitions[]='`'.$column['Field'].'` '.$column['Type'].($column['Field']==='id'?' NOT NULL AUTO_INCREMENT PRIMARY KEY':' NULL');
 $db->exec('CREATE TEMPORARY TABLE `'.$table.'` ('.implode(',',$definitions).') ENGINE=InnoDB');
}
