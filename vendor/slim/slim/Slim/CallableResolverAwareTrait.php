<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2016 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */
namespace Slim;

use RuntimeException;
use Interop\Container\ContainerInterface;
use Slim\Interfaces\CallableResolverInterface;

/**
 * ResolveCallable
 * 解决调用
 *
 * This is an internal class that enables resolution of 'class:method' strings
 * into a closure. This class is an implementation detail and is used only inside
 * of the Slim application.
 * 本类是一个可以将形如'class:method'的字符串解析为闭包。本类是细节实现，仅供Slim内部
 * 使用
 *
 * @property ContainerInterface $container
 */
trait CallableResolverAwareTrait
{
    /**
     * Resolve a string of the format 'class:method' into a closure that the
     * router can dispatch.
     * 将形如'class:method'的字符串解析为路由器可以调度的闭包
     *
     * @param callable|string $callable
     *
     * @return \Closure
     *
     * @throws RuntimeException If the string cannot be resolved as a callable
     */
    protected function resolveCallable($callable)
    {
        if (!$this->container instanceof ContainerInterface) {
            return $callable;
        }

        /** @var CallableResolverInterface $resolver */
        $resolver = $this->container->get('callableResolver');

        return $resolver->resolve($callable);
    }
}
