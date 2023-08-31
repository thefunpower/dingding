<?php 
/*
    Copyright (c) 2023-2033, All rights reserved.
    This is  a library, use is under MIT license.
*/

/**
* 内部应用access_token
* https://open.dingtalk.com/
* https://open.dingtalk.com/document/orgapp/obtain-a-sub-department-id-list-v2
*/ 
function get_ding_token($dd_app_key,$dd_app_secret){
    global $ding_talk,$ding_talk_token;
    $key = "ding_token:".$dd_app_key.$dd_app_secret; 
    $c = new Darabonba\OpenApi\Models\Config([]);
    $c->protocol = "https";
    $c->regionId = "central";
    $ding_talk =  new AlibabaCloud\SDK\Dingtalk\Voauth2_1_0\Dingtalk($c);  
    if(function_exists('cache')){
        $ding_talk_token = cache($key);
        if($ding_talk_token){
            return $ding_talk_token;    
        }        
    } 
    $getAccessTokenRequest = new AlibabaCloud\SDK\Dingtalk\Voauth2_1_0\Models\GetAccessTokenRequest([
        "appKey"    => $dd_app_key,
        "appSecret" => $dd_app_secret
    ]); 
    $res             = $ding_talk->getAccessToken($getAccessTokenRequest);
    $ding_talk_token = $res->body->accessToken; 
    $time            = $res->body->expireIn; 
    if(function_exists('cache') && $ding_talk_token && $time){
        cache($key,$ding_talk_token,$time-10); 
    }
    return $ding_talk_token;
} 
/**
* 返回错误信息
*/
function get_ding_error()
{
    global $ding_error;
    return $ding_error; 
}
/**
* 向钉钉发起CURL请求
*/ 
function ding_curl($uri , $data = []){
    global $ding_talk_token,$ding_error;
    $client = new GuzzleHttp\Client([
        'base_uri' => 'https://oapi.dingtalk.com',
        'timeout' => 30,
        'allow_redirects' => false,
    ]); 
    $uri .= '?access_token=' . $ding_talk_token;
    $res = $client->request('POST', $uri,
        [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $data
        ]);
    $res = json_decode($res->getBody()->getContents(), true);
    if($res['errcode'] != 0){
        $ding_error = $res['errmsg'];
        if(function_exists("trace")){
            trace($ding_error,'error'); 
        }
        return false;
    }
    return $res;
}
/**
* 获取总人数
*/
function get_ding_user_count(){
    $list = ding_curl("/topapi/user/count",[
        'only_active'=>false,
    ]);
    return $list['result']['count']; 
}
/**
* 操作员ID
*/
function get_ding_op_user_id(){
    return get_ding_admin()[0];
}
/**
* 获取管理员
*/
function get_ding_admin($is_ori = false){
    $list = ding_curl("/topapi/user/listadmin",[
        'a'=>1,
    ])['result'];
    if(!$is_ori){
        $new_list = [];
        if($list){
            foreach($list as $v){
                $new_list[] = $v['userid'];
            }
        }
        return $new_list;
    }
    return $list; 
}
/**
* 取部门列表
*/
function get_ding_dept_id(){ 
    $list = ding_curl('/topapi/v2/department/listsub', [ 
        'dept_id'  => 1,
        'language' => 'zh_CN'
    ]); 
    $all = $list['result']; 
    if($list['errcode']!=0){
        return;
    }
    $new_ids = [];
    foreach($all as $v){
        $dept_id = $v['dept_id'];
        $res = get_ding_dept_id_next($dept_id);  
        $new_ids[] = $dept_id;
        if($res){
            foreach($res as $id){
                $new_ids[] = $id;
            } 
        } 
    }  
    return $new_ids; 
}
/**
* 取子部门
*/
function get_ding_dept_id_next($dept_id){
    static $_ding_dept_id_in;
    $res = ding_curl('/topapi/v2/department/listsubid',[
        'dept_id'  => $dept_id,
    ]);
    $list = $res['result']['dept_id_list'];
    if($list){
        if(!$_ding_dept_id_in){
            $_ding_dept_id_in = $list;
        }else{
            $_ding_dept_id_in = array_merge($_ding_dept_id_in,$list);
        }
        foreach($list as $id){
            get_ding_dept_id_next($id);    
        } 
    }
    return $_ding_dept_id_in;
}


/**
* 取所有用户信息
* name mobile avatar admin active
*/
function get_ding_users(){
    $list = [];
    $all = get_ding_dept_id();
    foreach($all as $dept_id){
        $users = _get_ding_users($dept_id);
        if($users){
            foreach($users as $v){
                $list[$v['userid']] = $v;
            }
        } 
    }
    return $list;
}
/**
* 取部门下的用户
*/
function _get_ding_users($dept_id,$size=10,$cursor = 0){
    static $ding_user; 
    $res = ding_curl('/topapi/v2/user/list', [ 
        'dept_id' => $dept_id,
        'cursor'  => $cursor,
        'size'    => $size
    ]);  
    if($res['errcode'] != 0){ 
        return $ding_user[$dept_id]['user'];
    }
    $res  = $res['result'];
    $list = $res['list'];
    if($list){
        if(!isset($ding_user[$dept_id]['user'])){
            $ding_user[$dept_id]['user'] = $list;
        }else{
            $ding_user[$dept_id]['user'] = array_merge($ding_user[$dept_id]['user'],$list);
        }
    }
    $next_cursor = $res['next_cursor'];
    if($next_cursor){
        _get_ding_users($dept_id,$size,$next_cursor);
    }
    return $ding_user[$dept_id]['user']??[];
}
 
/**
* 钉钉机器人client
*/
function get_ding_robot_client(){
    $c = new Darabonba\OpenApi\Models\Config; 
    $c->protocol = "https";
    $c->regionId = "central";
    return new AlibabaCloud\SDK\Dingtalk\Vrobot_1_0\Dingtalk($c);
}
/**
* 发送消息
* @param $msg_param ['title'=>'','text'=>''];
*/
function send_ding_notice($robot_code,$user_id = [], $msg_param = [],$msg_key = 'sampleMarkdown'){ 
    global $ding_talk;
    global $ding_talk_token;
    $batchSendOTOHeaders = new AlibabaCloud\SDK\Dingtalk\Vrobot_1_0\Models\BatchSendOTOHeaders([]);
    $batchSendOTOHeaders->xAcsDingtalkAccessToken = $ding_talk_token;
    $batchSendOTORequest = new AlibabaCloud\SDK\Dingtalk\Vrobot_1_0\Models\BatchSendOTORequest([
        "robotCode" => $robot_code,
        "userIds"   => $user_id,
        //https://open.dingtalk.com/document/group/message-types-and-data-format
        "msgKey"    => $msg_key,
        "msgParam"  => json_encode($msg_param, JSON_UNESCAPED_UNICODE),
    ]);
    try {
        $res = get_ding_robot_client()->batchSendOTOWithOptions(
            $batchSendOTORequest, $batchSendOTOHeaders, new AlibabaCloud\Tea\Utils\Utils\RuntimeOptions([])
        );
        return true;
    } catch (Exception $e) {
        
    } 
} 
/**
* 发送文本消息
*/
function send_ding_notice_text($robot_code,$user_id = [], $title = '',$text = ''){
    return send_ding_notice($robot_code,$user_id, ['title'=>$title,'text'=>$text]);
}
/**
* 获取考勤信息
* [
*   'start'=>'',
*   'end'  =>'',
* ]
*/
function get_ding_kq($opt = []){
    $start = $opt['start']??date('Y-m-d 00:00:00',time()-86400*7);
    $end   = $opt['end']??date('Y-m-d H:i:s');
    $all   = get_ding_users();
    $in    = [];
    foreach($all as $v){
        $userid = $v['userid'];
        $group_id = get_ding_kq_group_id_by_userid($userid);
        if($group_id){
            $in[] = $userid; 
        } 
    } 
    $res = ding_curl('/attendance/listRecord', [ 
        'checkDateFrom' => $start,
        'checkDateTo'   => $end,
        'userIds'       => $in, 
    ]);  
    if($res){
        $all = $res['recordresult']; 
        foreach($all as &$v){
            $v['sign_time'] = date("Y-m-d H:i:s",$v['userCheckTime']/1000);
            $v['sign_day']  = date("Y-m-d",$v['userCheckTime']/1000);
            $user_id = $v['userId'];
            $v['user_info'] = get_ding_user_info($user_id);
        }
    }    
    return $all;     
}
/**
* 取用户所在考勤组group_id
*/
function get_ding_kq_group_id_by_userid($userid ){
    $res = ding_curl('/topapi/attendance/getusergroup', [ 
        'userid'      => $userid,
        'op_user_id'  => get_ding_op_user_id(), 
    ]);
    if($res && is_array($res) && $res['result']){
        return $res['result']['group_id'];
    } 
} 
/**
* 取用户详情
*/
function get_ding_user_info($user_id){
    static $_ding_cur_user;
    if(isset($_ding_cur_user[$user_id])){
        return $_ding_cur_user[$user_id];
    }
    $res = ding_curl('/topapi/v2/user/get', [ 
        'userid' => $user_id, 
    ]);
    if($res && is_array($res) && $res['result']){
        $_ding_cur_user[$user_id] =  $res['result'];
    }  else {
        $_ding_cur_user[$user_id] = [];
    }
    return $_ding_cur_user[$user_id];
}
/**
* 取部门信息
*/
function get_ding_dept_info($dept_id){
    $res = ding_curl('/topapi/v2/department/get', [ 
        'dept_id' => $dept_id, 
    ]);
    return $res['result'];
} 
/**
* 取所有部门列表信息
*/
function get_ding_depts()
{
    $all  = get_ding_dept_id();
    $list = [];
    foreach($all as $v){
        $list[] = get_ding_dept_info($v);
    }
    return $list;
}
/**
* 创建部门
*/
function ding_create_dept($name)
{
    $res = ding_curl('/topapi/v2/department/create', [ 
        'name'     => $name, 
        'parent_id'=> 1,
    ]);
    return $res['result']['dept_id']; 
}
/**
* 更新部门
*/
function ding_update_dept($old_name,$new_name)
{
    if(is_string($old_name)){
        $dept_id = get_ding_dept_id_by_name($old_name);
        if(!$dept_id){
            return false;
        }
    } else {
        $dept_id = $old_name;
    } 
    $res = ding_curl('/topapi/v2/department/update', [ 
        'dept_id' => $dept_id, 
        'name'    => $new_name,
    ]);
    if($res && $res['errcode'] == 0){
        return true;
    } else {
        return false;
    }
}

/**
* 删除部门
*/
function ding_del_dept($old_name,$new_name)
{
    if(is_string($old_name)){
        $dept_id = get_ding_dept_id_by_name($old_name);
        if(!$dept_id){
            return false;
        }
    } else {
        $dept_id = $old_name;
    } 
    $res = ding_curl('/topapi/v2/department/delete', [ 
        'dept_id' => $dept_id,  
    ]);
    if($res && $res['errcode'] == 0){
        return true;
    } else {
        return false;
    }
} 

/**
* 根据部门名称取部门ID
*/
function get_ding_dept_id_by_name($name){
    $all = get_ding_depts();
    $dept_id = '';
    foreach($all as $v){
        if($v['name'] == $name){
            $dept_id = $v['dept_id'];
            break;
        }
    }
    return $dept_id;
}



/**
* 创建用户
* [
*    'userid'=>'',
*    'name'=>'',
*    'mobile'=>'', 
*    'dept_name'=>'',
* ]
* https://open.dingtalk.com/document/orgapp/user-information-creation
*/
function ding_create_user($arr = [])
{ 
    if(isset($arr['dept_name'])){
        $dept_name = $arr['dept_name'];
        $arr['dept_id_list'] = get_ding_dept_id_by_name($dept_name);
    } 
    if(!isset($arr['name']) || !isset($arr['mobile']) || !$arr['dept_name']){
        return false;
    }
    $arr['parent_id'] = 1;
    $res = ding_curl('/topapi/v2/user/create',$arr);
    if($res && $res['errcode'] == 0){
        return $res['result'];
    } else {
        return false;
    }
}
/**
* 更新用户
*/
function ding_update_user($name_or_email_or_mobile,$arr = [])
{
    $name    = $name_or_email_or_mobile;
    $arr['userid'] = get_ding_user_id($name);
    if(isset($arr['dept_name'])){
        $dept_name = $arr['dept_name']; 
        $arr['dept_id_list'] = get_ding_dept_id_by_name($dept_name);
    } 
    if(!isset($arr['name']) || !$arr['userid']){
        return false;
    }  
    $res = ding_curl('/topapi/v2/user/update',$arr); 
    if($res && $res['errcode'] == 0){
        return true;
    } else {
        return false;
    }
}

/**
* 根据姓名取用户ID
*/
function get_ding_user_id($name_or_email_or_mobile,$show_full = false){
    $name = $name_or_email_or_mobile;
    $all  = get_ding_users();  
    foreach($all as $v){
        if($v['mobile'] == $name || $v['email'] == $name  || $v['name'] == $name ){
            $user_id = $v['userid'];
            if($show_full){
                return $v;
            }else {
                return $user_id;
            }
            break;
        }
    } 
} 

/**
* 继承辅助类
*/
class DingDingClass extends DingDingHelper{
    protected $group_name; 
    public function __construct(){
        self::get($this->group_name);
    }
}
/**
* 辅助类
*/
class DingDingHelper{    
    public static $group;
    public static $init;    
    /**
    * 实例
    */
    public static function instence(){ 
        if(!static::$init){
            static::$init = new Self;
        }
        return self::$init;
    }
    /**
    * 获取分组
    */
    public static function get($group_name = '')
    {
        $instence = self::instence();
        global $ding_talk_token; 
        $group_name = $group_name??$instence->group_name;
        $ding_talk_token = static::$group[$group_name];  
        return $instence;
    }
    /**
    * 配置分组
    */
    public static function set($group_name,$dd_app_key,$dd_app_secret)
    { 
        static::$group[$group_name] = get_ding_token($dd_app_key,$dd_app_secret);
    }
    /**
    * 调用方法
    */
    public  function __call($method,$args){ 
        if(function_exists($method)){ 
           $ret =  call_user_func_array($method,$args);
           return $ret;
        }else {
            echo "未找到对应函数";exit;
        }
    }
}

