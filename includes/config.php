<?php
// database host
$ip = $_SERVER['SERVER_ADDR'];
if (isProductionServer($ip)) {
        //$db_host   = "dushuhu1.mysql.rds.aliyuncs.com:3306";
        $db_host   = "localhost:3306";
        $db_name   = "trafficpolice";
        $db_user   = "root";
        $db_pass   = "Dsh12345";
} else {
        $db_host   = "localhost:3306";
        $db_name   = "trafficpolice";
        $db_user   = "root";
        $db_pass   = "";
}

function isProductionServer($ip) {
	return $ip == '112.124.98.9';
}

?>