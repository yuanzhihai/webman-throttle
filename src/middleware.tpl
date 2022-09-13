<?php
/**
 * 节流设置
 * @copyright The PHP-Tools
*/

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

/**
* Class StaticFile
* @package app\middleware
*/
class Throttle implements MiddlewareInterface
{
    public function process(Request $request, callable $next):Response
    {
        if ( $route = $request->route ) {
            $params = $route->param();
         }
        return (new \yzh52521\middleware\Throttle())->handle($request, $next, $params??[]);
    }
}