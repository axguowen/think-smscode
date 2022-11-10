# think-smscode

ThinkPHP6.0 短信验证码扩展

主要功能：短信验证码发送和验证

## 安装

~~~php
composer require axguowen/think-smscode
~~~

## 用法示例

本扩展不能单独使用，依赖ThinkPHP6.0+和axguowen\think-sms包，

think-sms的具体配置请参考：

https://github.com/axguowen/think-sms

注意：V1.1版本开始短信验证码数据存储基于Cache, 方便前后端分离应用

本扩展包的使用说明如下：

首先配置config目录下的smscode.php配置文件，然后可以按照下面的用法使用。

生成并发送短信验证码

~~~php

use axguowen\facade\Smscode;

// 生成发送短信验证码
$smscode = Smscode::create($tel);
// 获取发送状态, 成功返回true, 失败返回false;
$sendStatus = $smscode->getSendStatus();
// 如果失败则可以获取错误信息
if(false == $sendStatus){
    echo $smscode->getErrorMsg();
}

~~~

校验短信验证码

~~~php

use axguowen\facade\Smscode;

// 验证码校验, 校验成功返回true
$validateStatus = Smscode::validate('188****8888', '486936');

~~~

## 配置说明

~~~php

// 短信验证码配置
return [
    // 验证码位数
    'length' => 6,
    // 验证码有效期
    'expire' => 900,
    // 短信平台设置，可配置多个平台，会随机排序后按顺序发送，直到发送成功为止
    // 键为短信平台名称，平台名称请填写axguowen\think-sms扩展包配置文件里面的platforms配置项的平台名称
    // 值为模板ID，留空则使用sms扩展配置的默认模板ID
    'platforms' => [
        // 直接填写平台名
        'qiniu',
        // 指定模板ID,不指定则使用axguowen\think-sms配置的默认模板ID
        'aliyun' => 'SMS_2****78',
        // 指定短信模板变量名,不指定则为默认值code
        'tencent' => [
            'template_id' => 'SMS_2****78',
            'code_var' => 'mycode',
        ]
    ],
];

~~~