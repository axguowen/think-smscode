<?php
// +----------------------------------------------------------------------
// | ThinkSms [sms package for thinkphp]
// +----------------------------------------------------------------------
// | ThinkPHP短信验证码扩展
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: axguowen <axguowen@qq.com>
// +----------------------------------------------------------------------

namespace axguowen\facade;

use think\Facade;

/**
 * Class Smscode
 * @package axguowen\facade
 * @mixin \axguowen\Smscode
 * @method static obj create(string $mobile) 生成发送短信验证码
 * @method static bool validate(string $mobile, string $code) 验证码校验
 */
class Smscode extends Facade
{
    /**
     * 获取当前Facade对应类名
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return \axguowen\Smscode::class;
    }
}