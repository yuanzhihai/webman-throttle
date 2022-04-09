<?php
// +----------------------------------------------------------------------
// | 节流设置
// +----------------------------------------------------------------------
use yzh52521\middleware\Throttle;
use yzh52521\middleware\throttle\CounterFixed;
// use yzh52521\middleware\throttle\CounterSlider;
// use yzh52521\middleware\throttle\TokenBucket;
// use yzh52521\middleware\throttle\LeakyBucket;
use Webman\Http\{Request, Response};

return [
    'enable'                       => true,
    // 缓存键前缀，防止键值与其他应用冲突
    'prefix'                       => 'throttle_',

    // 缓存的键，true 表示使用来源ip (request->getRealIp(true))
    'key'                          => true,

    // 要被限制的请求类型, eg: GET POST PUT DELETE HEAD
    'visit_method'                 => ['GET'],

    // 设置访问频率，例如 '10/m' 指的是允许每分钟请求10次。值 null 表示不限制,
    // eg: null 10/m  20/h  300/d 200/300
    'visit_rate'                   => '100/m',

    // 响应体中设置速率限制的头部信息，含义见：https://docs.github.com/en/rest/overview/resources-in-the-rest-api#rate-limiting
    'visit_enable_show_rate_limit' => true,

    // 访问受限时返回的响应( type: null|callable )
    'visit_fail_response'          => function (Throttle $throttle, Request $request, int $wait_seconds): Response {
        return response('Too many requests, try again after ' . $wait_seconds . ' seconds.');
    },

    /*
     * 设置节流算法，组件提供了四种算法：
     *  - CounterFixed ：计数固定窗口
     *  - CounterSlider: 滑动窗口
     *  - TokenBucket : 令牌桶算法
     *  - LeakyBucket : 漏桶限流算法
     */
    'driver_name'                  => CounterFixed::class,

    // Psr-16通用缓存库规范: https://blog.csdn.net/maquealone/article/details/79651111
    // Cache驱动必须符合PSR-16缓存库规范，最低实现get/set俩个方法 (且需静态化实现)
    // static get(string $key, mixed $default=null)
    // static set(string $key, mixed $value, int $ttl=0);

    //webman默认使用 symfony/cache作为cache组件(https://www.workerman.net/doc/webman/db/cache.html)
    'cache_drive'                  => support\Cache::class,

    //使用ThinkCache
    //'cache_drive' => think\facade\Cache::class,
];