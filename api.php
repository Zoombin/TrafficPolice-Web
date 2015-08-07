<?php

require_once ('./submodules/php-mysqli-database-class/MysqliDb.php');

$db = new MysqliDb ('localhost:3306', 'root', 'Dsh12345', 'trafficpolice');
$ip = $_SERVER['SERVER_ADDR'];
if ($ip == '112.124.98.9') {
	$db = new MysqliDb ('localhost:3306', 'root', 'Dsh12345', 'trafficpolice');
}

$users = $db->get('user');
var_dump($users);
if ($db->count > 0) {
    foreach ($users as $user) { 
        print_r ($user);
    }
}

?>