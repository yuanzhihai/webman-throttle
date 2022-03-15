<?php
/**
 * 节流设置
 * @copyright The PHP-Tools
*/

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use yzh52521\middleware\Throttle as ThrottleCore;

/**
* Class StaticFile
* @package app\middleware
*/
class Throttle implements MiddlewareInterface
{
    public function process(Request $request, callable $next):Response
    {
        return (new ThrottleCore())->handle($request, $next);
    }

}