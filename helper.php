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
        $data = cache($key);
        if($data){
            return $data;    
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
* 向钉钉发起CURL请求
*/ 
function ding_curl($uri , $data = []){
    global $ding_talk_token;
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
        throw new Exception($res['errmsg']); 
    }
    return $res;
}
/**
* 取部门列表
*/
function get_ding_dept(){ 
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
        $res = get_ding_dept_next($dept_id);  
        $new_ids[] = $dept_id;
        foreach($res as $id){
            $new_ids[] = $id;
        } 
    } 
    return $new_ids; 
}
/**
* 取子部门
*/
function get_ding_dept_next($dept_id){
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
            get_ding_dept_next($id);    
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
    $all = get_ding_dept();
    foreach($all as $dept_id){
        $users = _get_ding_users($dept_id);
        foreach($users as $v){
            $list[$v['userid']] = $v;
        }
    }
    return $list;
}
/**
* 取部门下的用户
*/
function _get_ding_users($dept_id,$size=10){
    static $ding_user;
    if(!isset($ding_user[$dept_id]['cursor'])){
        $ding_user[$dept_id]['cursor'] = 0;    
    }else{
        $ding_user[$dept_id]['cursor']++;
    } 
    $res = ding_curl('/topapi/v2/user/list', [ 
        'dept_id' => $dept_id,
        'cursor'  => $ding_user[$dept_id]['cursor'],
        'size'    => $size
    ]);  
    if($res && $res['result']){
        $res = $res['result'];
    }else{
        return $ding_user[$dept_id]['user'];
    }
    $list = $res['list'];
    if($list){
        if(!isset($ding_user[$dept_id]['user'])){
            $ding_user[$dept_id]['user'] = $list;
        }else{
            $ding_user[$dept_id]['user'] = array_merge($ding_user[$dept_id]['user'],$list);
        }
    }
    $has_more = $res['has_more'];
    if($has_more){
        _get_ding_users($dept_id,$size);
    }
    return $ding_user[$dept_id]['user'];
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
function send_ding_notice_text($robot_code,$user_id = [], $title,$text){
    return send_ding_notice($robot_code,$user_id = [], ['title'=>$title,'text'=>$text]);
}