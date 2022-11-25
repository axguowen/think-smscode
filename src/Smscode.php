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

use Closure;
use FilesystemIterator;
use SplFileInfo;
use Generator;
use think\facade\App;
use think\facade\Config;
use think\facade\Cache;
use axguowen\facade\Sms;

/**
 * Class Smscode
 * @package axguowen
 */
class Smscode
{
    /**
     * 缓存句柄
     * @var Cache
     */
    protected $cache;

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
     * 发送状态
     * @var bool
     */
    protected $sendStatus = false;

    /**
     * 错误信息
     * @var string
     */
    protected $errorMsg = '';

    /**
     * 短信验证码
     * @var string
     */
    protected $code = '';

	/**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
        // 获取配置参数
        $options = Config::get('smscode', []);
        // 合并配置参数
        $this->options = array_merge($this->options, $options);
        // 初始化
        $this->init();
    }

    /**
     * 初始化
     * @access protected
     * @return void
     */
    protected function init($name = null)
    {
        // 获取缓存配置
        $cacheConfig = Config::get('cache');
        // 增加缓存类型配置
        $cacheConfig['stores']['smscode'] = [
            // 驱动方式
            'type' => 'File',
            // 缓存保存目录
            'path' => App::getRuntimePath() . 'cache' . DIRECTORY_SEPARATOR . 'smscode',
            // 缓存有效期
            'expire' => $this->options['expire'],
        ];
        // 更新缓存配置
        Config::set($cacheConfig, 'cache');
        // 实例化缓存句柄
        $this->cache = Cache::store('smscode');
        // 缓存垃圾回收
        $this->cacheClear();
    }

    /**
     * 发送验证码
     * @access public
     * @param string $mobile 手机号
     * @return bool
     */
    public function create($mobile)
    {
        // 初始化错误信息
        $this->initError();
        // 读取发送缓存
        $smscodeSended = $this->cache->get($mobile . '_sended');
        // 如果60秒内已发送过
        if($smscodeSended){
            return $this->setSendFailed('短信发送过于频繁');
        }

        $sendSuccess = false;
        // 生成验证码
        $code = $this->makeCode();
        // 获取可用的平台
        $platforms = $this->platforms();
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
                $sendSuccess = true;
                // 写入数据到缓存
                $this->saveCache($mobile, $code);
                // 结束循环
                break;
            }
        }
        // 如果发送成功
        if($sendSuccess){
            return $this->setSendSuccess($code);
        }
        // 返回失败
        return $this->setSendFailed('短信发送失败');
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
        // 获取缓存中的验证码
        $codeInCache = $this->cache->get($mobile . '_code');
        // 验证错误
        if($codeInCache != $code){
            return false;
        }
        // 删除缓存
        $this->deleteCache($mobile);
        // 验证成功
        return true;
    }

    /**
     * 是否发送成功
     * @access public
     * @return bool
     */
    public function getSendStatus()
    {
        return $this->sendStatus;
    }

    /**
     * 获取错误信息
     * @access public
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * 获取发送的短信
     * @access public
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * 通过token获取手机号
     * @access public
     * @param string $smstoken 令牌
     * @return string
     */
    public function getMobileByToken($smstoken)
    {
        return $this->cache->get($smstoken);
    }

    /**
     * 设置手机号token的缓存
     * @access public
     * @param string $smstoken 令牌
     * @param string $mobile 手机号
     * @return void
     */
    public function setTokenCache($smstoken, $mobile)
    {
        $this->cache->set($smstoken, $mobile);
    }

    /**
     * 通过手机号构造token
     * @access public
     * @param string $mobile 手机号
     * @return string
     */
    public function makeMobileToken($mobile)
    {
        // 构造token
        $smstoken = md5($mobile . time());
        $this->cache->set($smstoken, $mobile);
        return $smstoken;
    }

    /**
     * 生成验证码
     * @access protected
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
     * @access protected
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

    /**
     * 写入缓存
     * @access protected
     * @param  string $mobile 手机号
     * @param  string $code 验证码
     * @return void
     */
    protected function saveCache($mobile, $code)
    {
        // 存储验证码
        $this->cache->set($mobile . '_code', $code);
        // 存储已发送状态
        $this->cache->set($mobile . '_sended', '1', 60);
    }

    /**
     * 删除缓存缓存
     * @access protected
     * @param  string $mobile 手机号
     * @return void
     */
    protected function deleteCache($mobile)
    {
        // 删除验证码缓存
        $this->cache->delete($mobile . '_code');
        // 删除已发送状态缓存
        // $this->cache->delete($mobile . '_sended');
    }

    /**
     * 缓存垃圾回收
     * @access protected
     * @return void
     */
    protected function cacheClear()
    {
        $lifetime = $this->options['expire'];
        $now      = time();

        // 获取当前配置
        $cacheStoreConfig = Cache::getStoreConfig('smscode');
        // 如果不是文件类型
        if(!isset($cacheStoreConfig['type']) || $cacheStoreConfig['type'] != 'File'){
            return false;
        }
        // 如果目录不存在则返回
        if(!is_dir($cacheStoreConfig['path'])){
            return false;
        }
        $files = $this->findFiles($cacheStoreConfig['path'], function (SplFileInfo $item) use ($lifetime, $now) {
            return $now - $lifetime > $item->getMTime();
        });

        foreach ($files as $file) {
            $this->unlink($file->getPathname());
        }
    }

    /**
     * 查找文件
     * @access protected
     * @param string  $root
     * @param Closure $filter
     * @return Generator
     */
    protected function findFiles($root, Closure $filter)
    {
        $items = new FilesystemIterator($root);
        
        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                // PHP7 写法
                // yield from $this->findFiles($item->getPathname(), $filter);

                //*/ PHP5.6 兼容写法
                $files = $this->findFiles($item->getPathname(), $filter);
                foreach ($files as $file) {
                    $this->unlink($file->getPathname());
                }
                //*/
                
            } else {
                if ($filter($item)) {
                    yield $item;
                }
            }
        }
    }

    /**
     * 判断文件是否存在后，删除
     * @access protected
     * @param string $file
     * @return bool
     */
    protected function unlink($file)
    {
        return is_file($file) && unlink($file);
    }

    /**
     * 初始化错误信息
     * @access protected
     * @return void
     */
    protected function initError()
    {
        $this->sendStatus = false;
        $this->errorMsg = '';
        $this->code = '';
    }

    /**
     * 发送失败
     * @access protected
     * @param string $msg 错误信息
     * @return $this
     */
    protected function setSendFailed($msg = '')
    {
        $this->sendStatus = false;
        $this->errorMsg = $msg;
        $this->code = '';
        // 返回
        return $this;
    }

    /**
     * 发送成功
     * @access protected
     * @param string $code 短信验证码
     * @return $this
     */
    protected function setSendSuccess($code)
    {
        $this->sendStatus = true;
        $this->errorMsg = '';
        $this->code = $code;
        // 返回
        return $this;
    }

}