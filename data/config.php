<?php
// database host
$ip = $_SERVER['SERVER_ADDR'];
if (isProductionServer($ip)) {
        //$db_host   = "dushuhu1.mysql.rds.aliyuncs.com:3306";
        $db_host   = "localhost:3306";
        $db_name   = "traffic";
        $db_user   = "root";
        $db_pass   = "Dsh12345";
} else {
        $db_host   = "localhost:3306";
        $db_name   = "traffic";
        $db_user   = "root";
        $db_pass   = "";
}

// table prefix
$prefix    = "tp_";

$timezone    = "Asia/Chongqing";

$cookie_path    = "/";

$cookie_domain    = "";

$session = "1440";


function isProductionServer($ip) {
	return $ip == '115.29.174.220';
}


define('EC_CHARSET','utf-8');


define('API_TIME', '2014-12-22 23:48:43');

?>