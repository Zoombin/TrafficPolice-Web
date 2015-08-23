<?php

/**
 * api 公用类
 * 
 */
require_once __DIR__ . '/jpush/autoload.php';
require_once __DIR__ . '/qiniu/autoload.php';

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

use JPush\Model as M;
use JPush\JPushClient;
use JPush\Exception\APIConnectionException;
use JPush\Exception\APIRequestException;

class api {

    /* 短信配置项 start */
    var $msgApiInfo = array(
        'accountsid' => '2fe9bed94650b2455d45e5053e3a687a',
        'token' => '3a1e0c94d52f79df733ec4c2c40cbf88'
        );
    var $appId              = 'f68832e20d7c41c981a3f479d45dd6c3';
    var $templateIdRegister = '11181';  //注册时验证码, 模板id
    var $msgExpireTime      = 10;   // 验证码过期时间, 分钟
    var $msgTotal           = 400;   // 24小时内可申请多少次验证码
    /* 短信配置项 end */

    /* jpush配置项 start */
    var $appKey = '177710617edd09da6b1c9c61';
    var $masterSecret = 'e28e0aba92dc59df05df345a';
    /* jpush配置项 end */

    /* qiniu配置项 start */
    var $qiniuAccessKey = 'zrAvv0stUaPwrAYiaSuVgvsUSgajrFDcJoIn62Vp';
    var $qiniuSecretKey = '8onamuD2Evcu6nzoozjydlRL0oybHrRuc45fy_yA';
    var $qiniuBucket    = 'trafficpolice';
    /* qiniu配置项 end */

    /* 坐标搜索配置项 start */
    var $searchRadius = 5;   // 搜索半径, 单位: 千米
    var $searchTime = 48;       // 搜索多少小时以内的记录, 单位: 小时
    /* 坐标搜索配置项 end */


    /**
     * data | array | mixed data
     * total| int   | total amount of data
     * error| int   | error code, default to 0, 0 means successful
     * msg  | string| error message
     */
    
    var $res = array(
        'data' => array(),
        'total' => 0,
        'error' => 0,
        'msg' => ''
        );

    private function _pagesize() {
        $page_size = isset($_REQUEST['size'])  ? $_REQUEST['size']  : 4; //每页的个数
        $page      = isset($_REQUEST['page'])  ? $_REQUEST['page'] : 0; //第几页
        
        $start = $page * $page_size;
        // return array('page_size' => $page_size, 'start' => $start);
        return array($start, $page_size);
    }
    
    function getUserList(){
        global $db;
        $filter = $this->_pagesize();

        $rows = $db->withTotalCount()->get('users', $filter);
        $arr = array();
        if($db->count > 0){
            foreach ($rows as $row) {
                $arr[] = $row;
            }
        }
        $this->res['data'] = $arr;
        $this->res['total'] = $db->totalCount;

        return $this->res;
    }

    /**
     * 用户注册
     * @method regUser
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-10T12:03:57+0800
     */
    function regUser(){
        global $db;
        $username = $_REQUEST['username'];
        $username = trim($username);
        $password = $_REQUEST['password'];
        $nickname = $_REQUEST['nickname'];
        $captcha  = $_REQUEST['captcha'];

        if(!$username){
            $this->res['error'] = 1;
            $this->res['msg'] = '请输入用户名';
            return $this->res;
        }
        // whether already exist
        $isExist = $this->isUserExist($username);
        if($isExist){
            $this->res['error'] = 1;
            $this->res['msg'] = '用户名已经存在';
            return $this->res;
        }
        //valid captcha
        $sMsgType = 'regUser';
        $isCaptcha = $this->_validMobileCaptcha($username, $sMsgType, $captcha);
        if($isCaptcha['error']){
            $this->res['error'] = 1;
            $this->res['msg'] = $isCaptcha['msg'];
            return $this->res;
        }

        $aNewUser = array (
            'user_name' => $username,
            'password' => $password,
            'nickname' => $nickname,
            'created_date' => $db->now(),
        );
        $id = $db->insert ('users', $aNewUser);
        $aRes = array('userid' => $id);
        if ($id) {
            $this->res['data'] = $aRes;
            $this->res['error'] = 0;
            $this->res['msg'] = '用户创建成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '用户创建失败';
        }
        return $this->res;
    }

    /**
     * 判断用户是否已经存在
     * @method isUserExist
     * @param  string    $username
     * @return boolean
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-10T12:04:18+0800
     */
    function isUserExist($username){
        global $db;
        $db->where('user_name',$username)->get('users');
        return $db->count;
    }

    /**
     * 更新用户信息
     * @method updateUser
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-10T12:04:50+0800
     */
    function updateUser(){
        global $db;
        $userid   = $_REQUEST['userid'];
        $username = $_REQUEST['username'];
        $username = trim($username);
        $password = $_REQUEST['password'];
        $nickname = $_REQUEST['nickname'];
        $avatarurl = $_REQUEST['avatarurl'];

        $aUpdateUser = array();

        if(!$userid){
            $this->res['error'] = 1;
            $this->res['msg'] = '用户id不存在';
            return $this->res;
        }

        $db->where ('user_id', $userid);
        $user = $db->getOne('users');

        if($username != $user['user_name']){
            // whether already exist
            $isExist = $this->isUserExist($username);
            if($isExist){
                $this->res['error'] = 1;
                $this->res['msg'] = '用户名已经存在';
                return $this->res;
            }else{
                $aUpdateUser['user_name'] = $username;
            }
        }

        $aUpdateUser['password'] = $password;
        $aUpdateUser['nickname'] = $nickname;
        $aUpdateUser['updated_date'] = $db->now();
        if($avatarurl)
            $aUpdateUser['avatar_url'] = $avatarurl;
        
        $db->where ('user_id', $userid);
        $id = $db->update ('users', $aUpdateUser);
        if ($db->count) {
            $this->res['msg'] = '用户更新成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '用户更新失败';
        }
        return $this->res;
    }

    /**
     * 获取手机验证码
     *
     * @method getRegCaptcha
     * @access public
     * 
     * @param   string  $_REQUEST['username']     手机号码
     * 
     * @return array
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-09T01:43:14+0800
     */
    function getRegCaptcha(){
        global $db;
        $sMsgType = 'regUser';
        $phone = $_REQUEST['username'];
        if(!$phone){// TODO... valid phone number
            $this->res['error'] = 1;
            $this->res['msg'] = '手机号不存在';
            return $this->res;
        }

        /* 24小时内的验证次数 */
        //AND mm.msg_error_info='000000'
        $sql = "SELECT count(*) AS 'cnt' FROM mobile_message mm WHERE mm.created_date >= (NOW()-INTERVAL 24 HOUR) AND mm.msg_type='$sMsgType' AND mm.msg_to='$phone'";
        $aTotal = $db->rawQuery($sql);
        $cnt = $aTotal[0]['cnt'];

        if($cnt >= $this->msgTotal){
            $this->res['error'] = 1;
            $this->res['msg'] = '该手机获取验证码已达上限，请24小时后重试。';
            return $this->res;
        }

        //推送短信
        $captcha = rand(100000,999999);
        //您的验证码为{1}，请于{2}分钟内正确输入验证码
        $aParam = array($captcha, $this->msgExpireTime);
        $param = implode(',', $aParam);
        $resMsg = $this->_sendMsg($phone, $this->templateIdRegister, $param, $sMsgType, $captcha);
        $this->res['error'] = $resMsg['error'];
        $this->res['msg'] = $resMsg['msg'];
        
        if($cnt >= 1)
            $this->res['msg'] = '该手机还可获取'.($this->msgTotal-$cnt-1).'次验证码，请尽快完成验证。';
                
        return $this->res;
    }

    /**
     * 用户登陆
     * @method loginUser
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-10T12:03:32+0800
     */
    function loginUser(){
        global $db;
        $username = $_REQUEST['username'];
        $username = trim($username);
        $password = $_REQUEST['password'];
        $aUsers = $db->where("user_name = '$username' AND password='$password'")->get('users');

        if($db->count){
            $aRes = array(
                'userid' => $aUsers[0]['user_id'],
                'username' => $aUsers[0]['user_name'],
                'nickname' => $aUsers[0]['nickname'],
                'usermoney' => $aUsers[0]['user_money'],
                );
            $this->res['data'] = $aRes;
            $this->res['error'] = 0;
            $this->res['msg'] = '登陆成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '用户名或密码错误';
        }

        return $this->res;
    }

    /**
     *  用户删除
     * @method delUser
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-11T16:03:39+0800
     */
    function delUser(){
        global $db;
        $userid = $_REQUEST['userid'];
        if(!$userid){
            $this->res['error'] = 1;
            $this->res['msg'] = '用户id不存在';
            return $this->res;
        }

        $db->where ('user_id', $userid)->delete('users');
        $this->res['msg'] = '用户删除成功';

        return $this->res;
    }

    /**
     * 验证用户输入的验证码
     *
     * @access  public
     * @param   string  $mobile     手机号码
     * @param   string  $type       发送短信的类型
     * @param   string  $mobileCode 用户输入的验证码
     * @return  array
     */
    private function _validMobileCaptcha($mobile, $type, $mobileCode){
        global $db;
        $result   = array('error' => 0, 'msg' => '验证码输入正确.');
        //验证手机验证码
        $sql = "SELECT * FROM `mobile_message` WHERE msg_type='$type' AND msg_to='$mobile' ORDER BY created_date DESC LIMIT 1";
        $mobile_info = $db->rawQuery($sql);
        if(!$db->count || empty($mobileCode)){
            $result['error'] = 1;
            $result['msg'] = '请输入验证码';
            return $result;
        }
        
        $msg_content = $mobile_info[0]['msg_content'];
        $msg_date = $mobile_info[0]['created_date'];
        $captcha = $msg_content;
        if($captcha != $mobileCode){
            $result['error'] = 1;
            $result['msg'] = '验证码输入错误';
            return $result;
        }
        $zero1=strtotime(date('Y-m-d H:i:s')); //当前时间
        $zero2=strtotime($msg_date);
        $mins=ceil(($zero1-$zero2)/60); //60s
        if($mins > $this->msgExpireTime){//多少分钟后,验证码过期
            $result['error'] = 1;
            $result['msg'] = '验证码已过期,请重新发送.';
            return $result;
        }
        return $result;
    }

    private function _sendMsg($phone, $templateId, $param, $sMsgType, $sMsgContent){
        //call api
        require_once('Ucpaas.class.php');
        $ucpass = new Ucpaas($this->msgApiInfo);

        $resMsg = $ucpass->templateSMS($this->appId,$phone,$templateId,$param);
        $oResMsg = json_decode($resMsg);
        $aMsgRes = $this->_objectToArray($oResMsg);

        $aRes = array();
        $aRes['rawResponse'] = $resMsg;
        if($aMsgRes['resp']['respCode'] != '000000'){
            $aRes['error'] = $aMsgRes['resp']['respCode'];
            $aRes['msg'] = '短信发送失败';
        }else{
            $aRes['error'] = 0;
            $aRes['msg'] = '短信发送成功';
        }

        global $db;
        //save data to DB
        $aNewMsg = array(
            'msg_to' => $phone,
            'msg_type' => $sMsgType,
            'msg_content' => $sMsgContent,
            'msg_param' => $param,
            'msg_id' => $aMsgRes['resp']['templateSMS']['smsId'],
            'msg_created_date' => $aMsgRes['resp']['templateSMS']['createDate'],
            'msg_error_info' => $aMsgRes['resp']['respCode'],
            // 'msg_failure_count' => $aMsgRes['resp']['failure'],
            'msg_response_json' => $resMsg,
            'created_date' => $db->now(),
            );
        $id = $db->insert ('mobile_message', $aNewMsg);

        return $aRes;
    }


    private function _objectToArray($array) {  
        if(is_object($array)){
            $array = (array)$array;
        }
        if(is_array($array)){
            foreach($array as $key=>$value){
                $array[$key] = $this->_objectToArray($value);
            }
        }
        return $array;
    }

    /**
     * 标记停车
     * @method markPark
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-11T16:26:37+0800
     */
    function markPark(){
        global $db;
        $userid  = $_REQUEST['userid'];
        $long    = $_REQUEST['long'];
        $lat     = $_REQUEST['lat'];
        $imgurl  = $_REQUEST['imgurl'];
        $content = $_REQUEST['content'];
        $address = $_REQUEST['address'];

        if(!$userid){
            $this->res['error'] = 1;
            $this->res['msg'] = '用户id不存在';
            return $this->res;
        }

        $aNewRec = array (
            'user_id' => $userid,
            'longitude' => $long,
            'latitude' => $lat,
            'image_url' => $imgurl,
            'content' => $content,
            'address' => $address,
            'created_date' => $db->now(),
        );
        $id = $db->insert ('mark_park', $aNewRec);
        $aRes = array('id' => $id);
        if ($id) {
            $this->res['data'] = $aRes;
            $this->res['error'] = 0;
            $this->res['msg'] = '保存成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '保存失败';
        }
        return $this->res;

    }

    /**
     * 获得当前用户的停车信息, 是否在停车中
     * @method getPark
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-15T16:51:29+0800
     */
    function getPark(){
        global $db;
        $userid  = $_REQUEST['userid'];
        if(!$userid){
            $this->res['error'] = 1;
            $this->res['msg'] = '用户id不存在';
            return $this->res;
        }

        $sql = "SELECT * FROM `mark_park` WHERE user_id = '$userid' ORDER BY created_date DESC LIMIT 1";
        $aInfo = $db->rawQuery($sql);
        if($aInfo[0])
            $this->res['data'] = $aInfo[0];
        return $this->res;
    }

    /**
     * 取消停车
     * @method cancelPark
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-15T16:51:47+0800
     */
    function cancelPark(){
        global $db;
        $userid  = $_REQUEST['userid'];
        if(!$userid){
            $this->res['error'] = 1;
            $this->res['msg'] = '用户id不存在';
            return $this->res;
        }

        $aUpdate = array(
        'isactive' => 0,
        );
    
        $db->where ('user_id', $userid);
        $id = $db->update ('mark_park', $aUpdate);

        if ($id) {
            $this->res['error'] = 0;
            $this->res['msg'] = '取消停车成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '取消停车失败';
        }

        return $this->res;
    }

    /**
     * 标记发现交警的地点, 并向附近用户推送提示信息
     * @method markPolice
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-11T16:30:19+0800
     */
    function markPolice(){
        global $db;
        $userid  = $_REQUEST['userid'];
        $long    = $_REQUEST['long'];
        $lat     = $_REQUEST['lat'];
        $imgurl  = $_REQUEST['imgurl'];
        $content = $_REQUEST['content'];
        $address = $_REQUEST['address'];

        if(!$userid){
            $this->res['error'] = 1;
            $this->res['msg'] = '用户id不存在';
            return $this->res;
        }

        $aNewRec = array (
            'user_id' => $userid,
            'longitude' => $long,
            'latitude' => $lat,
            'image_url' => $imgurl,
            'content' => $content,
            'address' => $address,
            'created_date' => $db->now(),
        );
        $id = $db->insert ('mark_trafficpolice', $aNewRec);
        
        //获得附近停车用户
        $aUsers = $this->_getNearbyUsers($long, $lat);
        
        $sPushMsg = '您当前' . $this->searchRadius . '千米的范围内有交警，请注意！';
        if(count($aUsers)){
            $sMsgType = 'markPolice';
            //去除重复用户
            $aPushAlias = array();
            $aPushUsersInfo = array();
            foreach ($aUsers as $user) {
                if(!in_array($user['user_name'], $aPushAlias)){
                    $aPushAlias[] = $user['user_name'];
                    $aPushUsersInfo[] = $user;
                }
            }

            // 使用jpush 推送消息,
            // Options: 第一个参数为sendno,纯粹用来作为 API 调用标识，API 返回时被原样返回，以方便 API 调用方匹配请求与返回。
            //Options: 第二个参数为time_to_live,0 表示不保留离线消息，只有推送当前在线的用户可以收到。默认 86400 （1 天），最长 10 天
            $client = new JPushClient($this->appKey, $this->masterSecret);
            $response = $client->push()->setPlatform(M\all)
                ->setAudience(M\audience(M\alias($aPushAlias)))
                ->setNotification(M\notification($sPushMsg))
                ->setOptions(M\options($id, 0))
                ->send();
            $aPusRes = $this->_objectToArray($response);

            if($response->isOk == 1){
                $i = 0;
                foreach ($aPushUsersInfo as $user) {
                    //推送成功, 标记用户收到推送
                    $aNewLog = array(
                        'mt_id' => $id,
                        'user_id' => $user['user_id'],
                        'push_content' => $sPushMsg,
                        'push_param' => $response->json,
                        'created_date' => $db->now(),
                        );
                    $db->insert('mark_trafficpolice_received', $aNewLog);
                }
            }

        }

        $aRes = array('id' => $id);
        if ($id) {
            $this->res['data'] = $aRes;
            $this->res['error'] = 0;
            $this->res['msg'] = '保存成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '保存失败';
        }
        return $this->res;
    }

    function getNearbyUsers(){
        $long    = $_REQUEST['long'];
        $lat     = $_REQUEST['lat'];

        $aUsers = $this->_getNearbyUsers($long, $lat);

        $this->res['data'] = $aUsers;
        $this->res['total'] = count($aUsers);
        return $this->res;
    }
    /**
     * 获得当前坐标(经纬度)附近的用户
     * @method _getNearbyUsers
     * @param  float |  $long | 经度
     * @param  float |  $lat  | 纬度
     * @return array
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-11T16:34:24+0800
     */
    private function _getNearbyUsers($long, $lat){
        global $db;
        $range = $this->_getRange($long, $lat);
        // $searchTime = $this->searchTime;
        //AND mp.created_date >= DATE_SUB(NOW(),INTERVAL $searchTime hour)
        $sql = "SELECT mp.*,u.nickname,u.user_name FROM `mark_park` mp LEFT JOIN users u ON u.user_id=mp.user_id WHERE mp.longitude >= $range[minLong] AND mp.longitude <= $range[maxLong] AND mp.latitude >= $range[minLat] AND mp.latitude <= $range[maxLat] AND mp.isactive=1 ORDER BY mp.created_date DESC";

        $aTotal = $db->rawQuery($sql);
        return $aTotal;
    }
    /* 获得当前坐标附近的最大和最小坐标 */
    private function _getRange($lon, $lat){
        $raidus = $this->searchRadius * 1000;
        //计算纬度
        $degree = (24901 * 1609) / 360.0;
        $dpmLat = 1 / $degree; 
        $radiusLat = $dpmLat * $raidus;
        $minLat = $lat - $radiusLat; //得到最小纬度
        $maxLat = $lat + $radiusLat; //得到最大纬度     
        //计算经度
        $mpdLng = $degree * cos($lat * (PI / 180));
        $dpmLng = 1 / $mpdLng;
        $radiusLng = $dpmLng * $raidus;
        $minLng = $lon - $radiusLng;  //得到最小经度
        $maxLng = $lon + $radiusLng;  //得到最大经度
        //范围
        $range = array(
            'minLat' => $minLat,
            'maxLat' => $maxLat,
            'minLong' => $minLng,
            'maxLong' => $maxLng
        );
        return $range;
    }

    /**
     * 获得用户发出的推送列表
     * @method getUserPushList
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-15T17:47:18+0800
     */
    function getUserPushList(){
        global $db;
        $userid = $_REQUEST['userid'];
        
        $sql = "SELECT * FROM `mark_trafficpolice` WHERE user_id = '$userid' ORDER BY created_date DESC";
        $aList = $db->withTotalCount()->rawQuery($sql);

        if($db->totalCount)
            $this->res['data'] = $aList;
        $this->res['total'] = $db->totalCount;

        return $this->res;
    }

    /**
     * 当前用户收到的推送列表
     * @method getUserNotiList
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-13T13:25:41+0800
     */
    function getUserNotiList(){
        global $db;
        $userid = $_REQUEST['userid'];

        $sql = "SELECT mtr.id,mtr.user_id,mt.latitude,mt.longitude,mt.image_url,mt.content,mtr.created_date,mt.address FROM `mark_trafficpolice_received` mtr LEFT JOIN mark_trafficpolice mt ON mtr.mt_id=mt.id WHERE mtr.user_id='$userid' ORDER BY mtr.created_date DESC";

        $aList = $db->withTotalCount()->rawQuery($sql);

        if($db->totalCount)
            $this->res['data'] = $aList;
        $this->res['total'] = $db->totalCount;

        return $this->res;
    }

    /**
     * 在推送列表中, 用户可以选择一条进行评论
     * @method setNotiLike
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-13T13:35:33+0800
     */
    function setNotiLike(){
        global $db;
        $mtr_id              = $_REQUEST['id'];

        $aNew = array(
            'mtr_id' => $mtr_id,
            'created_date' => $db->now(),
            );
        
        $id = $db->insert ('mark_trafficpolice_like', $aNew);
        if ($id) {
            $this->res['msg'] = '操作成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '操作失败';
        }
        return $this->res;
    }

    function setNotiNoPolice(){
        global $db;
        $mtr_id  = $_REQUEST['id'];
        $long    = $_REQUEST['long'];
        $lat     = $_REQUEST['lat'];
        $address = $_REQUEST['address'];

        $aNew = array(
            'mtr_id' => $mtr_id,
            'longitude' => $long,
            'latitude' => $lat,
            'address' => $address,
            'created_date' => $db->now(),
            );
        
        $id = $db->insert ('mark_trafficpolice_nopolice', $aNew);
        if ($id) {
            $this->res['msg'] = '操作成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '操作失败';
        }
        return $this->res;
    }

    /**
     * 在线打赏
     * @method setNotiReward
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-18T12:05:34+0800
     */
    function setNotiReward(){
        global $db;
        $mtr_id              = $_REQUEST['id'];
        $pay_id              = $_REQUEST['uid'];

        $aNew = array(
            'mtr_id' => $mtr_id,
            'pay_id' => $pay_id,
            'created_date' => $db->now(),
            );
        
        $id = $db->insert ('mark_trafficpolice_reward', $aNew);
        if ($id) {
            $this->res['msg'] = '操作成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '操作失败';
        }
        return $this->res;
    }

    /* 在线支付成功后, 更新相关信息 */
    function updateNotireward(){

    }

    /**
     * 获得某个推送的详细信息以及点赞 等的历史记录
     * @method getNotiInfo
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-18T12:14:43+0800
     */
    function getNotiInfo(){
        global $db;
        $id = $_REQUEST['id'];
        $aData = array();
        //推送的详细信息
        $sql = "SELECT mtr.id, mtr.mt_id, mtr.user_id, mt.latitude, mt.longitude, mt.image_url, mt.content, mtr.created_date, mt.address
            , CASE WHEN (( SELECT COUNT(*) FROM mark_trafficpolice_nopolice mtn WHERE mtn.mtr_id = mtr.id ) > 0 ) THEN 1 ELSE 0 END AS 'isnopolice'
            , CASE WHEN (( SELECT COUNT(*) FROM mark_trafficpolice_like mtl WHERE mtl.mtr_id = mtr.id ) > 0 ) THEN 1 ELSE 0 END AS 'islike'
            , CASE WHEN (( SELECT COUNT(*) FROM mark_trafficpolice_reward reward WHERE reward.mtr_id = mtr.id AND reward.pay_success = 1 ) > 0 ) THEN 1 ELSE 0 END AS 'isreward'
            , ( SELECT COUNT(*) FROM mark_trafficpolice_nopolice mtn WHERE mtn.mtr_id IN ( SELECT t.id FROM mark_trafficpolice_received t WHERE t.mt_id = mtr.mt_id )) AS 'total_nopolice'
            , ( SELECT COUNT(*) FROM mark_trafficpolice_like mtl WHERE mtl.mtr_id IN ( SELECT t.id FROM mark_trafficpolice_received t WHERE t.mt_id = mtr.mt_id )) AS 'total_like'
            , ( SELECT COUNT(*) FROM mark_trafficpolice_reward reward WHERE reward.pay_success = 1 AND reward.mtr_id IN ( SELECT t.id FROM mark_trafficpolice_received t WHERE t.mt_id = mtr.mt_id )) AS 'total_reward' 
            FROM `mark_trafficpolice_received` mtr 
            LEFT JOIN mark_trafficpolice mt ON mtr.mt_id = mt.id 
            WHERE mtr.id= '$id' 
            ORDER BY mtr.created_date DESC"; 

        $aList = $db->rawQuery($sql);
        $aData['info'] = $aList[0];

        //获得历史记录
        $mt_id = $aList[0]['mt_id'];
        $sql = "SELECT t.*,u.user_name,u.nickname FROM
            (SELECT mtl.id,mtr.user_id,mtl.created_date, 1 AS 'type','' AS 'nopolice_address','' AS 'pay_money' FROM mark_trafficpolice_like mtl
            INNER JOIN mark_trafficpolice_received mtr ON mtl.mtr_id=mtr.id
            WHERE mtr.mt_id='$mt_id'
            UNION
            SELECT mtn.id,mtr.user_id,mtn.created_date, 2 AS 'type',mtn.address AS 'nopolice_address','' AS 'pay_money' FROM mark_trafficpolice_nopolice mtn
            INNER JOIN mark_trafficpolice_received mtr ON mtn.mtr_id=mtr.id
            WHERE mtr.mt_id='$mt_id'
            UNION
            SELECT reward.id,mtr.user_id,reward.created_date, 3 AS 'type','' AS 'nopolice_address',reward.pay_money FROM mark_trafficpolice_reward reward
            INNER JOIN mark_trafficpolice_received mtr ON reward.mtr_id=mtr.id
            WHERE reward.pay_success=1 AND mtr.mt_id='$mt_id') t
            LEFT JOIN users u ON t.user_id = u.user_id
            ORDER BY t.created_date DESC";
        
        $aList = $db->rawQuery($sql);
        $aData['history'] = $aList;

        $this->res['data'] = $aData;
        $this->res['total'] = $db->totalCount;

        return $this->res;
    }

    function getQiNiuUploadToken(){
        $auth = new Auth($this->qiniuAccessKey, $this->qiniuSecretKey);
        $token = $auth->uploadToken($this->qiniuBucket);
        $this->res['data'] = array('token' => $token);
        return $this->res;
    }

    /**
     * 获得最新的一条公告
     * @method getAnnouncement
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-19T15:03:26+0800
     */
    function getAnnouncement(){
        global $db;
        $db->orderBy("`id`","desc");
        $announcement = $db->withTotalCount()->getOne('announcement');

        if($db->totalCount)
            $this->res['data'] = $announcement;
        $this->res['total'] = $db->totalCount;

        return $this->res;
    }

    /**
     * 申请提现
     * @method applyWithdraw
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-19T15:40:48+0800
     */
    function applyWithdraw(){
        global $db;
        $userid        = $_REQUEST['userid'];
        $money         = $_REQUEST['money'];
        $alipayaccount = $_REQUEST['alipayaccount'];
        
        $db->where('user_id', $userid);
        $user = $db->getOne('users');

        if(!$user){
            $this->res['error'] = 1;
            $this->res['msg'] = '用户id不存在';
            return $this->res;
        }

        $usermoney = $user['user_money'];
        if($money > $usermoney){
            $this->res['error'] = 1;
            $this->res['msg'] = '余额不足';
            return $this->res;
        }

        $aUpdate = array(
            'user_money' => $usermoney - $money,
            'updated_date' => $db->now(),
            );
        $db->where('user_id', $userid);
        $db->update('users', $aUpdate);

        $aNewLog = array(
            'user_id' => $userid,
            'withdraw_money' => $money,
            'alipay_account' => $alipayaccount,
            'status' => 1,
            'created_date' => $db->now(),
            );
        $id = $db->insert('withdraw_cash', $aNewLog);
        if ($id) {
            $this->res['error'] = 0;
            $this->res['msg'] = '申请成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '申请失败';
        }

        return $this->res;
    }

    /**
     * 获得用户流水记录
     * @method getMoneyLog
     * @return [type]
     *
     * @author wesley zhang <wesley_zh@qq.com>
     * @since  2015-08-19T16:36:12+0800
     */
    function getMoneyLog(){
        global $db;
        $userid = $_REQUEST['userid'];

        $sql = "SELECT * FROM
            (SELECT reward.pay_money AS 'money',1 AS 'type',reward.created_date,'' AS 'status','' AS 'alipay_account' FROM mark_trafficpolice_reward reward
            WHERE reward.pay_success=1 AND reward.mtr_id IN (
            SELECT id FROM mark_trafficpolice_received
            WHERE mt_id IN (
            SELECT id FROM mark_trafficpolice WHERE user_id = '$userid'
            )
            )
            UNION
            SELECT wc.withdraw_money AS 'money',2 AS 'type',wc.created_date,wc.`status`,wc.alipay_account FROM withdraw_cash wc
            WHERE wc.user_id='$userid') t
            ORDER BY t.created_date DESC";

        $aList = $db->withTotalCount()->rawQuery($sql);
        if($db->totalCount)
            $this->res['data'] = $aList;
        $this->res['total'] = $db->totalCount;
        return $this->res;
    }

}