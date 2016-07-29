<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2016 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */

namespace Slim;

use Closure;
use Interop\Container\ContainerInterface;

/**
 * 延迟(调用)对象
 * 
 * 调用了延迟调用对象的实例就等于调用了中间件
 */

class DeferredCallable
{
    use CallableResolverAwareTrait;

    private $callable;
    /** @var  ContainerInterface */
    private $container;

    /**
     * DeferredMiddleware constructor.
     * @param callable|string $callable
     * @param ContainerInterface $container
     */
    public function __construct($callable, ContainerInterface $container = null)
    {
        $this->callable = $callable;
        $this->container = $container;
    }

    public function __invoke()
    {
        $callable = $this->resolveCallable($this->callable);
        // 如果$callable不是可调用(callable)数组，而是闭包
        if ($callable instanceof Closure) {
            // 如果是闭包，则改变其上下文
            $callable = $callable->bindTo($this->container);
        }

        $args = func_get_args();

        return call_user_func_array($callable, $args);
    }
}
