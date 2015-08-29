<?php
/**
 * alipay 支付宝
 * 支付成功后回调页面
 */

$alipay_key = 'tbl5nic1fcs85i3rbr4yf8p4vf6xr7ws';


if (!empty($_POST)) {
    foreach($_POST as $key => $data) {
        $_GET[$key] = $data;
    }
}
$seller_email = rawurldecode($_GET['seller_email']);

/* 检查数字签名是否正确 */
ksort($_GET);
reset($_GET);

$sign = '';
foreach ($_GET AS $key=>$val) {
    if ($key != 'sign' && $key != 'sign_type' && $key != 'code') {
        $sign .= "$key=$val&";
    }
}

$sign = substr($sign, 0, -1) . $alipay_key;
//$sign = substr($sign, 0, -1) . ALIPAY_AUTH;

$kv = array ();
foreach ( $_GET as $key => $value ) {
    $kv [] = "$key=$value";
}
$s = join ( "&", $kv );
$s .= '&mdrsign='.md5($sign);
$s .= '&localsign='.($sign);
$s .= "\n";
$sPath = '/tmp/test_log.txt';
// file_put_contents ( $sPath, $s, FILE_APPEND );
if (md5($sign) != $_GET['sign']) {
    // return false;
}

if ($_GET['trade_status'] == 'WAIT_SELLER_SEND_GOODS') {
    /* 改变订单状态 */
    order_paid();

    return true;
} elseif ($_GET['trade_status'] == 'TRADE_FINISHED') {
    /* 改变订单状态 */
    order_paid();

    return true;
} elseif ($_GET['trade_status'] == 'TRADE_SUCCESS') {
    /* 改变订单状态 */
    order_paid();

    return true;
} else {
    return false;
}


function order_paid(){
    require_once ('./submodules/php-mysqli-database-class/MysqliDb.php');
    require('./includes/config.php');
    $db = new MysqliDb ($db_host, $db_user, $db_pass, $db_name);
    $payid = $_GET['out_trade_no'];
    $aPayId = explode('_', $payid);
    $mtrid = $aPayId[1];
    $params = json_encode($_GET);

    //验证是否已经支付过
    $db->where("mtr_id = '$mtrid'")->get('mark_trafficpolice_reward');
    
    if($db->count == 0){
        $aNew = array(
            'mtr_id' => $mtrid,
            'pay_id' => $payid,
            'pay_success' => 1,
            'pay_money' => $_GET['total_fee'],
            'pay_date' => $_GET['gmt_payment'],
            'pay_params' => $params,
            'created_date' => $db->now(),
            );
        $id = $db->insert ('mark_trafficpolice_reward', $aNew);

        //给用户增加余额
        $mtrid = 20;
        $sql = "SELECT mt.user_id,u.user_money FROM `mark_trafficpolice` mt
            LEFT JOIN mark_trafficpolice_received mtr ON mt.id=mtr.mt_id
            LEFT JOIN users u ON u.user_id=mt.user_id
            WHERE mtr.id= '$mtrid'";
        $aUser = $db->rawQuery($sql);

        if($db->count){
            $aUpdate = array(
                'user_money' => $aUser[0]['user_money'] + $_GET['total_fee'],
                'updated_date' => $db->now(),
                );
            $db->where('user_id', $aUser[0]['user_id']);
            $db->update('users', $aUpdate);
        }
    }else{
        echo "already rewarded";
    }
    
}


?>