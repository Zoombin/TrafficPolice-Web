<?php

/**
 * api 公用类
 * 
 */
class api {

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
        return array('page_size' => $page_size, 'start' => $start);
    }
    
    function getUserList(){
        /* 查询记录 */
        $sql = "SELECT * FROM `tp_users` ORDER BY user_id";

        /* 记录总数以及页数 */
        $sqlCount = "SELECT COUNT(*) FROM (" .$sql . ") temp";
        $record_count = $GLOBALS['db']->getOne($sqlCount);

        $filter = $this->_pagesize();
        $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

        $arr = array();
        while ($rows = $GLOBALS['db']->fetchRow($res)){
            $arr[] = $rows;
        }
        $this->res['data'] = $arr;
        $this->res['total'] = $record_count;

        return $this->res;
    }
}