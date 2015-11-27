<?php
// database host
$ip = $_SERVER['SERVER_ADDR'];
if (isProductionServer($ip)) {
        $db_host   = "120.55.188.135";
        $db_name   = "trafficpolice";
        $db_user   = "root";
        $db_pass   = "NuoChe15!!";
} else {
        $db_host   = "120.55.188.135";
        $db_name   = "trafficpolice";
        $db_user   = "root";
        $db_pass   = "NuoChe15!!";
}

function isProductionServer($ip) {
	return $ip == '120.55.188.135';
}

?>