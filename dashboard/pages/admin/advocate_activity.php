<?php
if(!defined('IN_LAYOUT')||!in_array($role,['admin','super_admin'],true)){http_response_code(403);return;}
echo '<link rel="stylesheet" href="../assets/css/staff-activity.css?v=20261028-1">';
$advocateActivityOnly=true;
require __DIR__.'/../../../includes/staff_activity_panel.php';
