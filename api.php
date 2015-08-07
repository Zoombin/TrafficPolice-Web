<?php

require_once ('./submodules/php-mysqli-database-class/MysqliDb.php');

$db = new MysqliDb ('localhost', 'root', 'Dsh12345', 'trafficpolice');
$ip = $_SERVER['SERVER_ADDR'];
if ($ip == '112.124.98.9') {
	$db = new MysqliDb ('localhost', 'root', 'Dsh12345', 'trafficpolice');
}

$users = $db->get('user');
if ($db->count > 0) {
    foreach ($users as $user) { 
        print_r ($user);
    }
}

?>