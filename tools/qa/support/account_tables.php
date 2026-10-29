<?php
/** Preserve real generated columns and indexes while keeping all test writes connection-local. */
function accountTemporaryTable(PDO $db,string $table):void {
 if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('Invalid fixture table.');
 $row=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_ASSOC);$sql=$row['Create Table'];
 $lines=explode("\n",$sql);$lines=array_values(array_filter($lines,static fn($line)=>!preg_match('/^\s*CONSTRAINT\b/i',$line)));$sql=implode("\n",$lines);$sql=preg_replace('/,\n\)/',"\n)",$sql);$sql=preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$sql,1);$db->exec($sql);
}
