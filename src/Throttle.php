<?php
/**
 *
 * 访问频率限制中间件
 * @link https://github.com/yzh52521/webman-throttle
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @copyright The PHP - Tools
 */
declare(strict_types=1);

namespace yzh52521\middleware;

use Psr\SimpleCache\CacheInterface;
use yzh52521\middleware\throttle\{CounterFixed, ThrottleAbstract};
use Webman\Config;
use support\{Container, Cache, Request, Response};
use function sprintf;

/**
 * 访问频率限制中间件
 * Class Throttle
 * @package app\middleware\Throttle
 */
class Throttle
{
    /**
     * 默认配置参数
     * @var array
     */
    public static $default_config = [
        'prefix'                       => 'throttle_',                    // 缓存键前缀，防止键与其他应用冲突
        'key'                          => true,                           // 节流规则 true为自动规则
        'visit_method'                 => ['GET', 'HEAD'],          // 要被限制的请求类型
        'visit_rate'                   => null,                       // 节流频率 null 表示不限制 eg: 10/m  20/h  300/d
        'visit_enable_show_rate_limit' => true,     // 在响应体中设置速率限制的头部信息
        'visit_fail_code'              => 429,                   // 访问受限时返回的http状态码，当没有visit_fail_response时生效
        'visit_fail_text'              => 'Too Many Requests',   // 访问受限时访问的文本信息，当没有visit_fail_response时生效
        'visit_fail_response'          => null,              // 访问受限时的响应信息闭包回调
        'driver_name'                  => CounterFixed::class,       // 限流算法驱动
        'cache_drive'                  => Cache::class               // 缓存驱动
    ];

    public static $duration = [
        's' => 1,
        'm' => 60,
        'h' => 3600,
        'd' => 86400,
    ];

    /**
     * 缓存对象
     * @var CacheInterface
     */
    protected $cache;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    protected $key = null;          // 解析后的标识
    protected $wait_seconds = 0;    // 下次合法请求还有多少秒
    protected $now = 0;             // 当前时间戳
    protected $max_requests = 0;    // 规定时间内允许的最大请求次数
    protected $expire = 0;          // 规定时间
    protected $remaining = 0;       // 规定时间内还能请求的次数
    /**
     * @var ThrottleAbstract|null
     */
    protected $driver_class = null;

    /**
     * Throttle constructor.
     * @param Cache $cache
     * @param Config $config
     */
    public function __construct(array $params = [])
    {
        $this->config = array_merge(static::$default_config, Config::get('throttle', []), $params);
        $this->cache  = Container::make($this->config['cache_drive'], []);
    }

    /**
     * 请求是否允许
     * @param Request $request
     * @return bool
     */
    protected function allowRequest(Request $request): bool
    {
        // 若请求类型不在限制内
        if (!in_array($request->method(), $this->config['visit_method'])) {
            return true;
        }

        $key = $this->getCacheKey($request);
        if (null === $key) {
            return true;
        }
        [$max_requests, $duration] = $this->parseRate($this->config['visit_rate']);

        $micronow = microtime(true);
        $now      = (int)$micronow;

        $this->driver_class = Container::make($this->config['driver_name'], []);
        if (!$this->driver_class instanceof ThrottleAbstract) {
            throw new \TypeError('The throttle driver must extends ' . ThrottleAbstract::class);
        }
        $allow = $this->driver_class->allowRequest($key, $micronow, $max_requests, $duration, $this->cache);

        if ($allow) {
            // 允许访问
            $this->now          = $now;
            $this->expire       = $duration;
            $this->max_requests = $max_requests;
            $this->remaining    = $max_requests - $this->driver_class->getCurRequests();
            return true;
        }

        $this->wait_seconds = $this->driver_class->getWaitSeconds();
        return false;
    }

    /**
     * 处理限制访问
     * @param Request $request
     * @param array $params
     * @return bool
     * @exception
     */
    public function handle(Request $request, callable $next, array $params = []): Response
    {

        if ($params) {
            $this->config = array_merge($this->config, $params);
        }

        $allow = $this->allowRequest($request);
        if (!$allow) {
            // 访问受限
            return $this->buildLimitException($this->wait_seconds, $request);
        }

        $response = $next($request);

        if ((200 <= $response->getStatusCode() || 300 > $response->getStatusCode()) && $this->config['visit_enable_show_rate_limit']) {
            // 将速率限制 headers 添加到响应中
            $response->withHeaders($this->getRateLimitHeaders());
        }

        return $response;
    }

    /**
     * 生成缓存的 key
     * @param Request $request
     * @return null|string
     */
    protected function getCacheKey(Request $request): ?string
    {
        $key = $this->config['key'];

        if ($key instanceof \Closure) {
            $key = $key($this, $request);
        }

        if ($key === null || $key === false || $this->config['visit_rate'] === null) {
            // 关闭当前限制
            return null;
        }

        if ($key === true) {
            $key = $request->getRealIp($safe_mode = true);
        } else {
            $key = str_replace(
                [' ', 'controller/action/ip'],
                ['', $request->controller . '/' . $request->action . '/' . $request->getRealIp($safe_mode = true)],
                strtolower(trim($key))
            );
        }
        return md5($this->config['prefix'] . $key . $this->config['driver_name']);
    }

    /**
     * 解析频率配置项
     * @param string $rate
     * @return int[]
     */
    protected function parseRate($rate): array
    {
        [$num, $period] = explode("/", $rate);
        $max_requests = (int)$num;
        $duration     = static::$duration[$period] ?? (int)$period;
        return [$max_requests, $duration];
    }

    /**
     * 设置速率
     * @param string $rate '10/m'  '20/300'
     * @return $this
     */
    public function setRate(string $rate): self
    {
        $this->config['visit_rate'] = $rate;
        return $this;
    }

    /**
     * 设置缓存驱动
     * @param CacheInterface $cache
     * @return $this
     */
    public function setCache(CacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * 设置限流算法类
     * @param string $class_name
     * @return $this
     */
    public function setDriverClass(string $class_name): self
    {
        $this->config['driver_name'] = $class_name;
        return $this;
    }

    /**
     * 获取速率限制头
     * @return array
     */
    public function getRateLimitHeaders(): array
    {
        return [
            'X-Rate-Limit-Limit'     => $this->max_requests,
            'X-Rate-Limit-Remaining' => max($this->remaining, 0),
            'X-Rate-Limit-Reset'     => $this->now + $this->expire,
        ];
    }

    /**
     * 构建 Response Exception
     * @param int $wait_seconds
     * @param Request $request
     * @return Response
     */
    public function buildLimitException(int $wait_seconds, Request $request)
    {
        $visitFail = $this->config['visit_fail_response'] ?? null;
        if ($visitFail instanceof \Closure) {
            $response = $visitFail($this, $request, $wait_seconds);
            if (!$response instanceof Response) {
                throw new \TypeError(sprintf('The closure must return %s instance', Response::class));
            }
        } else {
            $content  = str_replace('__WAIT__', (string)$wait_seconds, $this->config['visit_fail_text']);
            $response = new Response($this->config['visit_fail_code'], [], $content);
        }
        if ($this->config['visit_enable_show_rate_limit']) {
            $response->withHeaders(['Retry-After' => $wait_seconds]);
        }
        return $response;
    }
}