# webman-throttle

## 作用
通过本中间件可限定用户在一段时间内的访问次数，可用于保护接口防爬防爆破的目的。

## 安装

```composer require yzh52521/webman-throttle```


安装后会自动为项目生成 config/plugin/yzh52521/throttle/app.php (***注意:任何时候你都不应该修改最后一行的 hash注释***)配置文件，安装后组件不会自动启用，需要手动设置。


## 开启

插件以中间件的方式进行工作，因此它的开启与其他中间件一样，例如在全局中间件中使用:


```
<?php
//cat config/middleware.php

return [
    // 全局中间件
    '' => [
        // ... 这里省略其它中间件
        app\middleware\Throttle::class,
    ]
];

```


## 配置说明
```
<?php
// cat config/plugin/yzh52521/throttle/app.php
// 中间件配置
return [

    // 缓存键前缀，防止键值与其他应用冲突
    'prefix' => 'throttle_',
	
    // 缓存的键，true 表示使用来源ip (request->getRealIp(true))
    'key' => true,
	
    // 要被限制的请求类型, eg: GET POST PUT DELETE HEAD
    'visit_method' => ['GET'],
	
    // 设置访问频率，例如 '10/m' 指的是允许每分钟请求10次。值 null 表示不限制,
	// eg: null 10/m  20/h  300/d 200/300
    'visit_rate' => '100/m',

    // 响应体中设置速率限制的头部信息，含义见：https://docs.github.com/en/rest/overview/resources-in-the-rest-api#rate-limiting
    'visit_enable_show_rate_limit' => true,
	
    // 访问受限时返回的响应( type: null|callable )
    'visit_fail_response' => function (Throttle $throttle, Request $request, int $wait_seconds): Response {
        return response('Too many requests, try again after ' . $wait_seconds . ' seconds.', 429);
    },
	
    /*
     * 设置节流算法，组件提供了四种算法：
     *  - CounterFixed ：计数固定窗口
     *  - CounterSlider: 滑动窗口
     *  - TokenBucket : 令牌桶算法
     *  - LeakyBucket : 漏桶限流算法
     */
    'driver_name' => CounterFixed::class,
	
    // Psr-16通用缓存库规范: https://blog.csdn.net/maquealone/article/details/79651111
    // Cache驱动必须符合PSR-16缓存库规范，最低实现get/set俩个方法 (且需静态化实现)
    //    static get(string $key, mixed $default=null)
    //    static set(string $key, mixed $value, int $ttl=0);

    //webman默认使用 symfony/cache作为cache组件(https://www.workerman.net/doc/webman/db/cache.html)
	'cache_drive' => support\Cache::class,
    
    //使用ThinkCache
    //'cache_drive' => think\facade\Cache::class,
	
];
```

当配置项满足以下条件任何一个时，不会限制访问频率：


```key === false || key === null || visit_rate === null```



其中 key 用来设置缓存键的, 而 visit_rate 用来设置访问频率，单位可以是秒，分，时，天。例如：1/s, 10/m, 98/h, 100/d , 也可以是 100/600 （600 秒内最多 100 次请求）。


### 灵活定制

###### 示例一：针对用户个体做限制,key的值可以设为函数，该函数返回新的缓存键值(需要Session支持)，例如：

```
'key' => function($throttle, $request) {
    return $request->session()->get('user_id');
},
```

###### 实例二：在回调函数里针对不同控制器和方法定制生成key，中间件会进行转换:

```
'key' => function($throttle, $request) {
    return  implode('/', [
		$request->controller,
		$request->action,
		$request->getRealIp($safe_mode=true)
	]);
},

//'key' => 'controller/action/ip' //上述配置的快捷实现


```


###### 示例三：在闭包内修改本次访问频率或临时更换限流策略：(PS：此示例需要本中间件在路由中间件后启用，这样预设的替换功能才会生效。)

```
'key' => function($throttle, $request) {
    $throttle->setRate('5/m');                      // 设置频率
    $throttle->setDriverClass(CounterSlider::class);// 设置限流策略
    return true;
},
```

###### 示例四：在路由中独立配置

```

Route::any('/api/driver-ocr', [ app\api\controller\Ocr::class, 'driver'])->middleware([
    app\middleware\Throttle::class
]);


//错误使用例子（不支持路由中间件默认传参）
Route::group('/path', function() {
    //路由注册
    ...

})->middleware(\app\middleware\Throttle::class, [
    'visit_rate' => '20/m',
    ...
    ...
]);
```

## 注意：
    1、截至(Webman 1.2.5, Webman-framework v1.2.7) 不支持路由中间件默认传参
    2、禁止访问时，throttle 默认是抛出 HttpResponseException, 当前插件场景下将正常响应一个httpResponse（即不会主动:Throw Exception），特殊需求请在 "visit_fail_response" 匿名函数中配置

## 申明

本库提取于[think-throttle v1.3.x](https://github.com/top-think/think-throttle)