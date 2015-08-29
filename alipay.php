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
$order_sn = str_replace($_GET['subject'], '', $_GET['out_trade_no']);
$order_sn = trim($order_sn);

$restock_id = $_GET['subject'];

$kv = array ();
foreach ( $_POST as $key => $value ) {
    $kv [] = "$key=$value";
}
$s = join ( "&", $kv );
 
$sPath = '/tmp/test_log.txt';
file_put_contents ( $sPath, $s, FILE_APPEND );

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
if (md5($sign) != $_GET['sign']) {
    return false;
}
/* 进货单 在线支付 */
if($order_sn == 'restock_pay'){
    order_restock_paid($restock_id, $_GET['total_fee']);
    return true;
}
/* 检查支付的金额是否相符 */
if (!check_money($order_sn, $_GET['total_fee'])) {
    return false;
}
if ($_GET['trade_status'] == 'WAIT_SELLER_SEND_GOODS') {
    /* 改变订单状态 */
    order_paid($order_sn, 2);

    return true;
} elseif ($_GET['trade_status'] == 'TRADE_FINISHED') {
    /* 改变订单状态 */
    order_paid($order_sn);

    return true;
} elseif ($_GET['trade_status'] == 'TRADE_SUCCESS') {
    /* 改变订单状态 */
    order_paid($order_sn, 2);

    return true;
} else {
    return false;
}





?>