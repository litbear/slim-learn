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
    // 储存生成类的闭包，或者定义全局变量的字符串
    private $values = array();
    // 储存构造器，每次返回不同的实例
    private $factories;
    // 以参数形式储存匿名函数
    private $protected;
    // 以单例模式取出对象时，会将本属性元素的值设为true
    private $frozen = array();
    // 
    private $raw = array();
    // 以array_key形式赋值的对象，都会储存key
    private $keys = array();

    /**
     * Instantiate the container.
     * 实例化容器
     *
     * Objects and parameters can be passed as argument to the constructor.
     * 对象和参数可以通过参数传入构造函数
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
     * 设置参数或对象
     *
     * Objects must be defined as Closures.
     * 对象也可以定义为闭包
     *
     * Allowing any PHP callable leads to difficult to debug problems
     * as function names (strings) are callable (creating a function with
     * the same name as an existing parameter would break your container).
     * 允许所有的PHP回调会导致难以调试，因为函数名是可执行的。（创建一个与
     * 已存在参数同名的方法会导致容器报错）
     *
     * @param string $id    The unique identifier for the parameter or object
     * 参数或对象的唯一ID
     * @param mixed  $value The value of the parameter or a closure to define an object
     * 参数的值或者定义对象的闭包
     *
     * @throws \RuntimeException Prevent override of a frozen service
     */
    public function offsetSet($id, $value)
    {
        if (isset($this->frozen[$id])) {
            throw new \RuntimeException(sprintf('Cannot override frozen service "%s".', $id));
        }

        $this->values[$id] = $value;
        $this->keys[$id] = true;
    }

    /**
     * Gets a parameter or an object.
     * 获取参数或对象
     * 在获取对象时，本方法不会返回匿名函数本身，而是返回单例
     *
     * @param string $id The unique identifier for the parameter or object
     * 参数或对象的唯一ID
     *
     * @return mixed The value of the parameter or an object
     * 参数的值或者定义对象的闭包
     *
     * @throws \InvalidArgumentException if the identifier is not defined
     */
    public function offsetGet($id)
    {
        if (!isset($this->keys[$id])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        // 进入这个分支，返回的就是匿名函数本身了，但判断条件
        // 现在还不是很明白
        if (
            isset($this->raw[$id])
            || !is_object($this->values[$id])
            || isset($this->protected[$this->values[$id]])
            || !method_exists($this->values[$id], '__invoke')
        ) {
            return $this->values[$id];
        }

        // 进入这个分支，返回的是一个新的实例
        if (isset($this->factories[$this->values[$id]])) {
            return $this->values[$id]($this);
        }

        $raw = $this->values[$id];
        $val = $this->values[$id] = $raw($this);
        $this->raw[$id] = $raw;

        $this->frozen[$id] = true;

        return $val;
    }

    /**
     * Checks if a parameter or an object is set.
     * 检查参数或对象是否被设置
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
     * 移除指定参数或对象
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
     * 标记一个回调函数为工厂服务
     * factories属性储存构造器，每次返回不同的实例
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
     * 保护回调函数使之不被看作服务
     *
     * This is useful when you want to store a callable as a parameter.
     * 本方法用于将一个回调函数储存为参数
     *
     * @param callable $callable A callable to protect from being evaluated
     *
     * @return callable The passed callable
     *
     * @throws \InvalidArgumentException Service definition has to be a closure of an invokable object
     */
    public function protect($callable)
    {
        if (!method_exists($callable, '__invoke')) {
            throw new \InvalidArgumentException('Callable is not a Closure or invokable object.');
        }

        $this->protected->attach($callable);

        return $callable;
    }

    /**
     * Gets a parameter or the closure defining an object.
     * 获取一个参数或定义对象的回调函数
     * 本方法获取回调函数本身，例如$c->foo或者$c['foo']返回的是匿名函数的返回值
     * 本方法返回的是匿名函数本身
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

        return $this->values[$id];
    }

    /**
     * Extends an object definition.
     * 扩展对象的定义
     *
     * Useful when you want to extend an existing object definition,
     * without necessarily loading that object.
     * 本函数用于扩展一个已存在的对象，没有必要加载那个对象
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
        // 必须是已经注入到的类
        if (!isset($this->keys[$id])) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }

        // 该类必须以对象或者闭包的形式注册，不能是字符串参数
        if (!is_object($this->values[$id]) || !method_exists($this->values[$id], '__invoke')) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" does not contain an object definition.', $id));
        }

        // 第二个参数必须是对象或者闭包
        if (!is_object($callable) || !method_exists($callable, '__invoke')) {
            throw new \InvalidArgumentException('Extension service definition is not a Closure or invokable object.');
        }

        // 
        $factory = $this->values[$id];

        $extended = function ($c) use ($callable, $factory) {
            return $callable($factory($c), $c);
        };

        // 重新绑定工厂生成器
        if (isset($this->factories[$factory])) {
            $this->factories->detach($factory);
            $this->factories->attach($extended);
        }

        return $this[$id] = $extended;
    }

    /**
     * Returns all defined value names.
     * 返回所有values属性的键
     *
     * @return array An array of value names
     */
    public function keys()
    {
        return array_keys($this->values);
    }

    /**
     * Registers a service provider.
     * 注册服务提供者
     *
     * @param ServiceProviderInterface $provider A ServiceProviderInterface instance
     * @param array                    $values   An array of values that customizes the provider
     *
     * @return static
     */
    public function register(ServiceProviderInterface $provider, array $values = array())
    {
        $provider->register($this);

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
    }
}
