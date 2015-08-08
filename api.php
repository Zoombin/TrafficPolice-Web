<?php
// error_reporting(E_ALL);
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING)); 

require_once ('./submodules/php-mysqli-database-class/MysqliDb.php');
require_once('./includes/cls_api.php');

// 初始化数据库类
$db = new MysqliDb ('localhost', 'root', 'Dsh12345', 'trafficpolice');
$ip = $_SERVER['SERVER_ADDR'];
if ($ip == '112.124.98.9') {
	$db = new MysqliDb ('localhost', 'root', 'Dsh12345', 'trafficpolice');
}

//提供给手机客户端的接口
$action = $_REQUEST['action'];

$api = new api($db);

$res = array();
if($action){
    if (method_exists($api, $action)) {
        $res = $api->$action();
    }else{
        $res['error'] = 1;
        $res['msg'] = 'function ' . $action . ' doesn\'t exist.';
    }
}else{
    $res['error'] = 1;
    $res['msg'] = 'invalid parameter.';
}
// print('<pre>');
// print_r($res);
// print('</pre>');
header('Content-Type: application/json;charset=utf-8');
echo json_encode($res);

?>