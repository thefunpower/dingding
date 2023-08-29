# 钉钉

## 安装 

~~~
composer require thefunpower/dingding
~~~

开发版 
~~~
"thefunpower/dingding": "dev-main"
~~~

时区

~~~
date_default_timezone_set('PRC');
set_time_limit(-1); 
~~~


### 获取用户  
~~~
get_ding_token($dd_app_key,$dd_app_secret); 
$res = get_ding_users();
print_r($res); 
~~~

### 考勤

限制7天，格式 YYYY-MM-DD hh:ii:ss

https://open.dingtalk.com/document/isvapp/attendance-clock-in-record-is-open

~~~ 
get_ding_token($dd_app_key,$dd_app_secret); 
get_ding_kq($opt = [
  'start' => '',
  'end'   => '',
]);
~~~~

### 发送消息

~~~
$title = "test";
$text  = "这个是内容";
send_ding_notice_text($robot_code='ding0uednrlb3kyef0xb',$user_id = ['0246365867749182'], $title, $text);
~~~

markdown消息  $text 值如下所示
~~~
# 这是支持markdown的文本   \n   ## 标题2    \n   * 列表1   \n  ![alt 啊](https://img.alicdn.com/tps/TB1XLjqNVXXXXc4XVXXXXXXXXXX-170-64.png)
~~~

markdown语法说明如下

~~~
标题
# 一级标题
## 二级标题
### 三级标题
#### 四级标题
##### 五级标题
###### 六级标题
 
引用
> A man who stands for nothing will fall for anything.
 
文字加粗、斜体
**bold**
*italic*
 
链接
[this is a link](http://name.com)
 
图片
![](http://name.com/pic.jpg)
 
无序列表
- item1
- item2
 
有序列表
1. item1
2. item2

换行
  \n  (建议\n前后分别加2个空格)
~~~

### 原生发消息

`$msg_param` 参数请参考 https://open.dingtalk.com/document/orgapp/message-types-and-data-format

~~~
send_ding_notice($robot_code,$user_id = [], $msg_param = [],$msg_key = 'sampleMarkdown')
~~~


# 创建钉钉应用

[访问钉钉](https://open-dev.dingtalk.com/fe/app#/corp/app)

创建H5微应用

在【应用信息】中找到  【AppKey】  【AppSecret】 

在【机器人与消息推送】  复制 RobotCode




### 开源协议 

The [MIT](LICENSE) License (MIT)