<?php
// database host
$ip = $_SERVER['SERVER_ADDR'];
if (isProductionServer($ip)) {
        $db_host   = "localhost";
        $db_name   = "trafficpolice";
        $db_user   = "root";
        $db_pass   = "NuoChe15!!";
} else {
        $db_host   = "localhost";
        $db_name   = "trafficpolice";
        $db_user   = "root";
        $db_pass   = "";
}

function isProductionServer($ip) {
	return $ip == '120.55.188.135';
}

?>