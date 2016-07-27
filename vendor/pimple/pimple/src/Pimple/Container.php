<?php

/*
 * This file is part of Pimple.
 *
 * Copyright (c) 2009 Fabien Potencier
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Pimple;

/**
 * Container main class.
 *
 * @author  Fabien Potencier
 */
class Container implements \ArrayAccess
{
    // 缓存所有数组式赋值，当服务被首次取出时，
    // 相应的values值会被替换为闭包的结果。
    private $values = array();
    private $factories;
    // 用于储存包裹（用作参数的函数）的闭包
    private $protected;
    // 用以标记被赋值，并被取出的服务
    private $frozen = array();
    // 用以储存产生单例实例的原闭包
    private $raw = array();
    // 用于标记该id是否已被赋值注入
    private $keys = array();

    /**
     * Instantiate the container.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $values The parameters or objects.
     */
    public function __construct(array $values = array())
    {
        $this->factories = new \SplObjectStorage();
        $this->protected = new \SplObjectStorage();

        foreach ($values as $key => $value) {
            $this->offsetSet($key, $value);
        }
    }

    /**
     * Sets a parameter or an object.
     * 为容器添加一个参数或对象
     *
     * Objects must be defined as Closures.
     * 对象必须定义为闭包
     *
     * Allowing any PHP callable leads to difficult to debug problems
     * as function names (strings) are callable (creating a function with
     * the same name as an existing parameter would break your container).
     * 如果不是闭包而是callable，会使程序变得难以维护
     *
     * @param string $id    The unique identifier for the parameter or object
     * @param mixed  $value The value of the parameter or a closure to define an object
     *
     * @throws \RuntimeException Prevent override of a frozen service
     */
    public function offsetSet($id, $value)
    {
        // 如果设置的容器id在frozen属性内，则抛出异常
        if (isset($this->frozen[$id])) {
            throw new \RuntimeException(sprintf('Cannot override frozen service "%s".', $id));
        }

        // 将id与值都存入values属性
        $this->values[$id] = $value;
        // 将keys标记为true
        $this->keys[$id] = true;
    }

    /**
     * Gets a parameter or an object.
     * 根据id获取参数或对象
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     *
     * @throws \InvalidArgumentException if the identifier is not defined
     */
    public function offsetGet($id)
    {
        // 如果在keys属性中未找到，则抛出异常
        if (!isset($this->keys[$id])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        if (
            // 在raw属性中发现了此id
            isset($this->raw[$id])
            // 或此id对应的values不是闭包，也就是字符串
            || !is_object($this->values[$id])
            // 或该id对应的values存在于protected属性中（即被当错参数的匿名函数，
            // 此时取出的是闭包，需要执行才能得出结果）
            || isset($this->protected[$this->values[$id]])
            // 或该id对应的values是不可执行的（匿名函数是存在'__invoke'的）
            // var_dump(method_exists((function(){}), '__invoke')); // true
            || !method_exists($this->values[$id], '__invoke')
        ) {
            // 则直接返回对应的vaules值
            return $this->values[$id];
        }

        // 在检索id时，如果发现响应的values存在于factories属性
        if (isset($this->factories[$this->values[$id]])) {
            // 则执行对应的values，并传入本容器
            return $this->values[$id]($this);
        }

        // 如果走到这一步，则对应的values值应该为对象，且是第一次访问
        // 取出闭包
        $raw = $this->values[$id];
        // 将闭包的执行结果赋值给values，并返回
        $val = $this->values[$id] = $raw($this);
        // 将对应的原闭包存入raw属性
        $this->raw[$id] = $raw;

        // 缓存完毕，将此id冻结，不能再被赋值
        $this->frozen[$id] = true;

        return $val;
    }

    /**
     * Checks if a parameter or an object is set.
     * 判断给定的id是否存在于容器
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return bool
     */
    public function offsetExists($id)
    {
        return isset($this->keys[$id]);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     */
    public function offsetUnset($id)
    {
        if (isset($this->keys[$id])) {
            if (is_object($this->values[$id])) {
                unset($this->factories[$this->values[$id]], $this->protected[$this->values[$id]]);
            }

            unset($this->values[$id], $this->frozen[$id], $this->raw[$id], $this->keys[$id]);
        }
    }

    /**
     * Marks a callable as being a factory service.
     * 将匿名函数存入factory属性，取出时，每取出一次，执行一次闭包
     *
     * @param callable $callable A service definition to be used as a factory
     *
     * @return callable The passed callable
     *
     * @throws \InvalidArgumentException Service definition has to be a closure of an invokable object
     */
    public function factory($callable)
    {
        if (!method_exists($callable, '__invoke')) {
            throw new \InvalidArgumentException('Service definition is not a Closure or invokable object.');
        }

        $this->factories->attach($callable);

        return $callable;
    }

    /**
     * Protects a callable from being interpreted as a service.
     * 保护callable类型的元素，使其不被视为“服务”
     * 默认情况下Pimple会将匿名函数视为服务，因此，需要将匿名函数包装在
     * protect中
     *
     * This is useful when you want to store a callable as a parameter.
     *
     * @param callable $callable A callable to protect from being evaluated
     *
     * @return callable The passed callable
     *
     * @throws \InvalidArgumentException Service definition has to be a closure of an invokable object
     */
    public function protect($callable)
    {
        // 如果不可执行则抛出异常
        if (!method_exists($callable, '__invoke')) {
            throw new \InvalidArgumentException('Callable is not a Closure or invokable object.');
        }

        // 将其加入protected属性，并返回闭包
        $this->protected->attach($callable);

        return $callable;
    }

    /**
     * Gets a parameter or the closure defining an object.
     * 获取包裹参数或服务的原始闭包
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or the closure defining an object
     *
     * @throws \InvalidArgumentException if the identifier is not defined
     */
    public function raw($id)
    {
        if (!isset($this->keys[$id])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        if (isset($this->raw[$id])) {
            return $this->raw[$id];
        }

        // 走到这一步，说明没被调用过
        return $this->values[$id];
    }

    /**
     * Extends an object definition.
     * 扩展已定义的对象
     * （既可以扩展单例服务，也可以扩展工厂服务）
     *
     * Useful when you want to extend an existing object definition,
     * without necessarily loading that object.
     * 在有些时候可能需要对已定义的对象进行进一步操作，此时可以使用本方法
     *
     * @param string   $id       The unique identifier for the object
     * @param callable $callable A service definition to extend the original
     *
     * @return callable The wrapped callable
     *
     * @throws \InvalidArgumentException if the identifier is not defined or not a service definition
     */
    public function extend($id, $callable)
    {
        // 操作的对象必须已被注入
        if (!isset($this->keys[$id])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        // 操作的对象必须是对象，且可调用
        if (!is_object($this->values[$id]) || !method_exists($this->values[$id], '__invoke')) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" does not contain an object definition.', $id));
        }

        // 扩展对象的参数也必须是对象，且可调用
        if (!is_object($callable) || !method_exists($callable, '__invoke')) {
            throw new \InvalidArgumentException('Extension service definition is not a Closure or invokable object.');
        }

        $factory = $this->values[$id];

        $extended = function ($c) use ($callable, $factory) {
            return $callable($factory($c), $c);
        };

        // 缓存工厂
        if (isset($this->factories[$factory])) {
            $this->factories->detach($factory);
            $this->factories->attach($extended);
        }

        return $this[$id] = $extended;
    }

    /**
     * Returns all defined value names.
     *
     * @return array An array of value names
     */
    public function keys()
    {
        return array_keys($this->values);
    }

    /**
     * Registers a service provider.
     *
     * @param ServiceProviderInterface $provider A ServiceProviderInterface instance
     * @param array                    $values   An array of values that customizes the provider
     *
     * @return static
     */
    public function register(ServiceProviderInterface $provider, array $values = array())
    {
        // 从服务提供者的register函数中批量注入
        $provider->register($this);

        // 使用第二个参数批量注入
        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
    }
}
