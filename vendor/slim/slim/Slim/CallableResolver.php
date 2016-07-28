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
 * ���ཫ����'class:method'���ַ�������Ϊ·�������Ե��ȵıհ�
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
     * ������Ĳ�������Ϊ·�������Ե��ȵģ�callable������
     *
     * If toResolve is of the format 'class:method', then try to extract 'class'
     * from the container otherwise instantiate it and then dispatch 'method'.
     * ���紫��Ĳ�������'class:method', ���Խ������ಢ��ϳ�callable���飬����
     * ʵ����֮
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

        // ������ɵ��ã������ַ��������Խ���
        if (!is_callable($toResolve) && is_string($toResolve)) {
            // check for slim callable as "class:method"
            // ������"class:method"�ķ�ʽ����
            // �ǣ���ͷ�Ķ���ַ�������������ĸ�»��ߣ��ٸ�������ĸ�»���
            $callablePattern = '!^([^\:]+)\:([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$!';
            if (preg_match($callablePattern, $toResolve, $matches)) {
                $class = $matches[1];
                $method = $matches[2];

                // ����������д��ࣨʵ��������������
                // ���װ��callable����
                if ($this->container->has($class)) {
                    $resolved = [$this->container->get($class), $method];
                } else {
                    // ������಻���ڣ����׳��쳣
                    if (!class_exists($class)) {
                        throw new RuntimeException(sprintf('Callable %s does not exist', $class));
                    }
                    // ���հ�װ��ʵ���ͷ�����ɵ�callable����
                    $resolved = [new $class($this->container), $method];
                }
            } else {
                // check if string is something in the DIC that's callable or is a class name which
                // has an __invoke() method
                // ���������"class:method"ģʽ������ȥDI�����п���û����һ�����У�
                // ��ȡ�����п�����ʵ����Ҳ�п����������
                // ���������û��һ������������ҿ��治���Ӱ����ٳ���ʵ����
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

        // �������ʧ�ܣ����ɵ��ã����׳��쳣
        if (!is_callable($resolved)) {
            throw new RuntimeException(sprintf(
                '%s is not resolvable',
                is_array($toResolve) || is_object($toResolve) ? json_encode($toResolve) : $toResolve
            ));
        }

        return $resolved;
    }
}
