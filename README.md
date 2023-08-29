# 钉钉

### 获取用户

~~~
get_ding_token($dd_app_key,$dd_app_secret); 
$res = get_ding_users();
print_r($res); 
~~~

### 发送消息

~~~
$title = "test";
$text  = "这个是内容";
send_ding_notice($robot_code='ding0uednrlb3kyef0xb',$user_id = ['0246365867749182'], $title, $text);
~~~

markdown消息  $text 值如下所示
~~~
# 这是支持markdown的文本   \n   ## 标题2    \n   * 列表1   \n  ![alt 啊](https://img.alicdn.com/tps/TB1XLjqNVXXXXc4XVXXXXXXXXXX-170-64.png)
~~~



### 开源协议 

The [MIT](LICENSE) License (MIT)