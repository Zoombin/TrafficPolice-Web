<?php

define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'includes/cls_api.php');

//提供给手机客户端的接口
$action = $_REQUEST['action'];

$api = new api();

$res = array();
if($action){
    if (method_exists($api, $action)) {
        header('Content-Type: application/json');
        $res = $api->$action();
    }else{
        $res['error'] = 1;
        $res['msg'] = 'function ' . $action . ' doesn\'t exist.';
    }
}else{
    $res['error'] = 1;
    $res['msg'] = 'invalid parameter.';
}
echo json_encode($res);

?>