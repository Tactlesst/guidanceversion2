<?php
// Public read-only loader. Built-in and superadmin-published releases share one history.
try{require_once __DIR__.'/../../config/database.php';require_once __DIR__.'/../../classes/system/ReleaseNotes.php';return (new ReleaseNotes((new Database)->getConnection()))->all();}catch(Throwable $e){return require __DIR__.'/builtin_releases.php';}
