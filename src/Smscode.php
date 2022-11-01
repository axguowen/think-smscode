<?php
// +----------------------------------------------------------------------
// | ThinkSmscode [smscode package for thinkphp]
// +----------------------------------------------------------------------
// | ThinkPHP短信验证码扩展
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: axguowen <axguowen@qq.com>
// +----------------------------------------------------------------------

namespace axguowen;

use think\facade\Config;
use think\facade\Session;
use think\facade\Request;
use axguowen\facade\Sms;

/**
 * Class Smscode
 * @package axguowen
 */
class Smscode
{
    /**
     * 配置参数
     * @var array
     */
    protected $options = [
        // 验证码位数
        'length' => 6,
        // 验证码有效期
        'expire' => 900,
        // 短信平台设置，可配置多个平台，会随机排序后按顺序发送，直到发送成功为止
        // 键为短信平台名称
        // 值为模板ID，留空则使用sms扩展配置的默认模板ID
        'platforms' => [],
    ];

	/**
     * 架构函数
     * @access public
     * @param array $app 应用对象
     */
    public function __construct()
    {
        // 获取配置
        $this->setConfig(Config::get('smscode', []));
    }

    /**
     * 获取配置
     * @access public
     * @param  string $name 配置名称
     * @return array
     */
    public function config($name = null)
    {
        // 未指定配置项
        if (is_null($name)) {
            // 返回全部配置
            return $this->options;
        }
        // 存在指定的配置项
        if(isset($this->options[$name])){
            return $this->options[$name];
        }
        // 指定的配置项不存在
        return null;
    }

    /**
     * 设置配置
     * @access public
     * @param  array $options 配置参数
     * @return $this
     */
    public function setConfig($options)
    {
        // 合并配置参数
        $this->options = array_merge($this->options, $options);
        // 返回
        return $this;
    }

    /**
     * 发送验证码并把验证码的值保存到session中
     * @access public
     * @param string $mobile 手机号
     * @return bool
     */
    public function create($mobile)
    {
        // 生成验证码
        $code = $this->makeCode();
        // 获取可用的平台
        $platforms = $this->platforms();
        // 验证码发送状态
        $sendStatus = false;
        // 遍历平台发送验证码
        foreach($platforms as $name => $platform){
            // 模板ID
            $templateId = null;
            if(!empty($platform['template_id'])){
                $templateId = $platform['template_id'];
            }
            // 短信变量
            $codeVar = 'code';
            if(!empty($platform['code_var'])){
                $codeVar = $platform['code_var'];
            }
            $data[$codeVar] = $code;
            // 获取短信平台驱动并发送短信
            if(Sms::platform($name)->setMobiles($mobile)->send($data, $templateId)){
                // 改变状态
                $sendStatus = true;
                // 写入数据到Session
                Session::set('smscode.mobile', $mobile);
                Session::set('smscode.code', $code);
                Session::set('smscode.expire', Request::time() + $this->options['expire']);
                // 结束循环
                break;
            }
        }
        return $sendStatus;
    }

    /**
     * 短信验证码校验
     * @access public
     * @param  string $mobile 手机号
     * @param  string $code 验证码
     * @return bool
     */
    public function validate($mobile, $code)
    {
        // Session数据不存在
        if (!Session::has('smscode')) {
            return false;
        }
        // 对比手机号
        $smscodeMobile = Session::get('smscode.mobile');
        if($smscodeMobile != $mobile){
            return false;
        }
        // 对比验证码
        $smscodeCode = Session::get('smscode.code');
        if($smscodeCode != $code){
            return false;
        }
        // 对比时间
        $smscodeExpire = Session::get('smscode.expire');
        if(Request::time() > $smscodeExpire){
            return false;
        }
        // 删除
        Session::delete('smscode');
        // 验证成功
        return true;
    }

    /**
     * 生成验证码
     * @access public
     * @return string
     */
    protected function makeCode()
    {
        $code = '';
        // 构造验证码的字符串
        $chars = str_repeat('0123456789', 3);
        // 随机排序
        $chars = str_shuffle($chars);
        // 截取
        $code = substr($chars, 0, $this->options['length']);
        // 返回
        return $code;
    }

    /**
     * 构建可用的平台数组
     * @access public
     * @return array
     */
    protected function platforms()
    {
        // 平台配置为空
        if(empty($this->options['platforms'])){
            return [];
        }
        // 可用的平台
        $platforms = [];
        // 遍历配置数组
        foreach($this->options['platforms'] as $k => $v){
            // 如果未指定键名
            if(is_int($k)){
                $platforms[$v] = [
                    'template_id' => '',
                    'code_var' => '',
                ];
            }
            else{
                // 如果值是字符串
                if(is_string($v)){
                    $platforms[$k] = [
                        'template_id' => $v,
                        'code_var' => '',
                    ];
                }
                else{
                    $platforms[$k] = [
                        'template_id' => isset($v['template_id']) ? $v['template_id'] : '',
                        'code_var' => isset($v['code_var']) ? $v['code_var'] : '',
                    ];
                }
            }
        }
        // 获取键名
        $keys = array_keys($platforms);
        // 打乱顺序
        shuffle($keys);
        // 随机顺序数组
        $randPlatforms = [];
        // 遍历
        foreach($keys as $key){
            $randPlatforms[$key] = $platforms[$key];
        }
        // 返回
        return $randPlatforms;
    }
}