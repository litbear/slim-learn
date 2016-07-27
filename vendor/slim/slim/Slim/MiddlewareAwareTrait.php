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
use SplStack;
use SplDoublyLinkedList;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

/**
 * Middleware
 * 中间件
 *
 * This is an internal class that enables concentric middleware layers. This
 * class is an implementation detail and is used only inside of the Slim
 * application; it is not visible to—and should not be used by—end users.
 * 这是个为应用提供同心圆中间件层的内部类，本类属于细节实现，仅用于Slim应用内部。
 * 本类不可访问，并且不能被终端用户使用
 */
trait MiddlewareAwareTrait
{
    /**
     * Middleware call stack
     * 中间件调用栈
     *
     * @var  \SplStack
     * @link http://php.net/manual/class.splstack.php
     */
    protected $stack;

    /**
     * Middleware stack lock
     * 中间件锁
     *
     * @var bool
     */
    protected $middlewareLock = false;

    /**
     * Add middleware
     * 添加中间件
     *
     * This method prepends new middleware to the application middleware stack.
     * 本方法将新的中间件前置于应用的中间件栈
     *
     * @param callable $callable Any callable that accepts three arguments:
     *                  所有的callable类型参数接受三个参数
     *                           1. A Request object 请求对象
     *                           2. A Response object 响应镀锡
     *                           3. A "next" middleware callable 下一个中间件对象
     * @return static
     *
     * @throws RuntimeException         If middleware is added while the stack is dequeuing
     * @throws UnexpectedValueException If the middleware doesn't return a Psr\Http\Message\ResponseInterface
     */
    protected function addMiddleware(callable $callable)
    {
        // 正在依次执行中间件期间不能新增中间件
        if ($this->middlewareLock) {
            throw new RuntimeException('Middleware can’t be added once the stack is dequeuing');
        }

        // 如果中间件栈为空，则调用seedMiddlewareStack()初始化栈
        if (is_null($this->stack)) {
            $this->seedMiddlewareStack();
        }
        // 下一个中间件栈指向栈顶
        $next = $this->stack->top();
        // 中间件栈的每一个元素都是一个闭包-匿名函数 该函数接受两个参数
        // 请求实例和响应实例 $callable 应为DeferredCallable实例
        // 匿名函数的返回值是DeferredCallable实例的运行结果，即
        // __invoke结果。
        $this->stack[] = function (ServerRequestInterface $req, ResponseInterface $res) use ($callable, $next) {
            $result = call_user_func($callable, $req, $res, $next);
            // 如果该中间件执行的结果不是ResponseInterface实例，则抛出异常
            if ($result instanceof ResponseInterface === false) {
                throw new UnexpectedValueException(
                    'Middleware must return instance of \Psr\Http\Message\ResponseInterface'
                );
            }

            return $result;
        };

        return $this;
    }

    /**
     * Seed middleware stack with first callable
     *
     * @param callable $kernel The last item to run as middleware
     * 中间件栈的最后一个元素
     *
     * @throws RuntimeException if the stack is seeded more than once
     */
    protected function seedMiddlewareStack(callable $kernel = null)
    {
        if (!is_null($this->stack)) {
            throw new RuntimeException('MiddlewareStack can only be seeded once.');
        }
        if ($kernel === null) {
            $kernel = $this;
        }
        $this->stack = new SplStack;
        // 设置链表(栈)的迭代模式 栈风格|Elements are traversed by the iterator
        $this->stack->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO | SplDoublyLinkedList::IT_MODE_KEEP);
        $this->stack[] = $kernel;
    }

    /**
     * Call middleware stack
     * 调用中间件栈
     *
     * @param  ServerRequestInterface $req A request object
     * @param  ResponseInterface      $res A response object
     *
     * @return ResponseInterface
     */
    public function callMiddlewareStack(ServerRequestInterface $req, ResponseInterface $res)
    {
        if (is_null($this->stack)) {
            $this->seedMiddlewareStack();
        }
        /** @var callable $start */
        $start = $this->stack->top();
        $this->middlewareLock = true;
        $resp = $start($req, $res);
        $this->middlewareLock = false;
        return $resp;
    }
}
