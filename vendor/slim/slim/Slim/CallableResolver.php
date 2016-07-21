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
 * This class resolves a string of the format 'class:method' into a closure
 * that can be dispatched.
 * 本类将形如'class:method'的字符串解析为路由器可以调度的闭包
 */
final class CallableResolver implements CallableResolverInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Resolve toResolve into a closure that that the router can dispatch.
     * 将传入的参数解析为路由器可以调度的，callable的数组
     *
     * If toResolve is of the format 'class:method', then try to extract 'class'
     * from the container otherwise instantiate it and then dispatch 'method'.
     * 假如传入的参数形如'class:method', 则尝试解析出类并组合成callable数组，否则
     * 实例化之
     *
     * @param mixed $toResolve
     *
     * @return callable
     *
     * @throws RuntimeException if the callable does not exist
     * @throws RuntimeException if the callable is not resolvable
     */
    public function resolve($toResolve)
    {
        $resolved = $toResolve;

        // 如果不可调用，且是字符串，则尝试解析
        if (!is_callable($toResolve) && is_string($toResolve)) {
            // check for slim callable as "class:method"
            // 尝试以"class:method"的方式解析
            // 非：开头的多个字符，：，紧跟字母下划线，再跟数字字母下划线
            $callablePattern = '!^([^\:]+)\:([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$!';
            if (preg_match($callablePattern, $toResolve, $matches)) {
                $class = $matches[1];
                $method = $matches[2];

                // 如果容器中有此类（实例还是类名？）
                // 则包装成callable数组
                if ($this->container->has($class)) {
                    $resolved = [$this->container->get($class), $method];
                } else {
                    // 如果该类不存在，则抛出异常
                    if (!class_exists($class)) {
                        throw new RuntimeException(sprintf('Callable %s does not exist', $class));
                    }
                    // 最终包装成实例和方法组成的callable数组
                    $resolved = [new $class($this->container), $method];
                }
            } else {
                // check if string is something in the DIC that's callable or is a class name which
                // has an __invoke() method
                // 如果不符合"class:method"模式，则先去DI容器中看有没有这一项，如果有，
                // 则取出（有可能是实例，也有可能是配置项）
                // 如果容器中没这一项，则以类名查找看存不存子啊，再尝试实例化
                $class = $toResolve;
                if ($this->container->has($class)) {
                    $resolved = $this->container->get($class);
                } else {
                    if (!class_exists($class)) {
                        throw new RuntimeException(sprintf('Callable %s does not exist', $class));
                    }
                    $resolved = new $class($this->container);
                }
            }
        }

        // 如果解析失败，不可调用，则抛出异常
        if (!is_callable($resolved)) {
            throw new RuntimeException(sprintf('%s is not resolvable', $toResolve));
        }

        return $resolved;
    }
}
