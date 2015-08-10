<?php

/**
 * api 公用类
 * 
 */
class api {

    /* 短信配置项 start */
    var $msgApiInfo = array(
        'accountsid' => '2fe9bed94650b2455d45e5053e3a687a',
        'token' => '3a1e0c94d52f79df733ec4c2c40cbf88'
        );
    var $appId = 'f68832e20d7c41c981a3f479d45dd6c3';
    var $templateId = '11181';
    var $appTitle = '交警来了';
    var $msgExpireTime = 10;   // 验证码过期时间, 分钟
    var $msgTotal = 400;   // 24小时内可申请多少次验证码
    /* 短信配置项 end */

    /**
     * data | array | mixed data
     * total| int   | total amount of data
     * error| int   | error code, default to 0, 0 means successful
     * msg  | string| error message
     */
    
    var $res = array(
        'data' => '',
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
        $captcha = $_REQUEST['captcha'];

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
        if ($id) {
            $this->res['data'] = $id;
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
        $userid = $_REQUEST['userid'];
        $username = $_REQUEST['username'];
        $username = trim($username);
        $password = $_REQUEST['password'];
        $nickname = $_REQUEST['nickname'];

        $aUpateUser = array();

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
                $aUpateUser['user_name'] = $username;
            }
        }

        $aUpateUser['password'] = $password;
        $aUpateUser['nickname'] = $nickname;
        $aUpateUser['updated_date'] = $db->now();
        
        $db->where ('user_id', $userid);
        $id = $db->update ('users', $aUpateUser);
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

        //call api
        require_once('Ucpaas.class.php');
        $captcha = rand(100000,999999);
        // $aParam = array($this->appTitle, $captcha, $this->msgExpireTime);
        $aParam = array($captcha, $this->msgExpireTime);
        $param = implode(',', $aParam);
        $ucpass = new Ucpaas($this->msgApiInfo);
        // $param = "交警来了,1256,3";
        //您注册{1}的验证码为{2}，请于{3}分钟内正确输入验证码

        $resMsg = $ucpass->templateSMS($this->appId,$phone,$this->templateId,$param);
        $oResMsg = json_decode($resMsg);
        $aMsgRes = $this->_objectToArray($oResMsg);

        if($aMsgRes['resp']['respCode'] != '000000'){
            $this->res['error'] = $aMsgRes['resp']['respCode'];
            $this->res['msg'] = '短信发送失败';
        }else{
            $this->res['error'] = 0;
            $this->res['msg'] = '短信发送成功';
        }
        if($cnt >= 1)
            $this->res['msg'] = '该手机还可获取'.($this->msgTotal-$cnt-1).'次验证码，请尽快完成验证。';

        //save data to DB
        $aNewMsg = array(
            'msg_to' => $phone,
            'msg_type' => $sMsgType,
            'msg_content' => $captcha,
            'msg_content' => $captcha,
            'msg_param' => $param,
            'msg_id' => $aMsgRes['resp']['templateSMS']['smsId'],
            'msg_created_date' => $aMsgRes['resp']['templateSMS']['createDate'],
            'msg_error_info' => $aMsgRes['resp']['respCode'],
            // 'msg_failure_count' => $aMsgRes['resp']['failure'],
            'msg_response_json' => $resMsg,
            'created_date' => $db->now(),
            );
        $id = $db->insert ('mobile_message', $aNewMsg);
        
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
        $db->where("user_name = '$username' AND password='$password'")->get('users');
        if($db->count){
            $this->res['error'] = 0;
            $this->res['msg'] = '登陆成功';
        }else{
            $this->res['error'] = 1;
            $this->res['msg'] = '用户名或密码错误';
        }

        return $this->res;
    }

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

}