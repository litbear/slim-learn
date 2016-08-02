<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2016 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */
namespace Slim\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Represents a data stream as defined in PSR-7.
 *
 * @link https://github.com/php-fig/http-message/blob/master/src/StreamInterface.php
 */
class Stream implements StreamInterface
{
    /**
     * Bit mask to determine if the stream is a pipe
     * 用于判断该流是否是管道的位掩码
     *
     * This is octal as per header stat.h
     * 八进制
     */
    const FSTAT_MODE_S_IFIFO = 0010000;

    /**
     * Resource modes
     * 资源模式，是否可写
     *
     * @var  array
     * @link http://php.net/manual/function.fopen.php
     */
    protected static $modes = [
        'readable' => ['r', 'r+', 'w+', 'a+', 'x+', 'c+'],
        'writable' => ['r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'],
    ];

    /**
     * The underlying stream resource
     * 该流的底层子牙un
     *
     * @var resource
     */
    protected $stream;

    /**
     * Stream metadata
     * 该流的元数据
     *
     * @var array
     */
    protected $meta;

    /**
     * Is this stream readable?
     * 该流是否可读
     *
     * @var bool
     */
    protected $readable;

    /**
     * Is this stream writable?
     * 该流是否可写
     *
     * @var bool
     */
    protected $writable;

    /**
     * Is this stream seekable?
     * 该流是否可寻址
     *
     * @var bool
     */
    protected $seekable;

    /**
     * The size of the stream if known
     * 该流的大小
     *
     * @var null|int
     */
    protected $size;

    /**
     * Is this stream a pipe?
     * 该流是否是管道
     *
     * @var bool
     */
    protected $isPipe;

    /**
     * Create a new Stream.
     * 创建新流
     *
     * @param  resource $stream A PHP resource handle.
     *
     * @throws InvalidArgumentException If argument is not a resource.
     */
    public function __construct($stream)
    {
        $this->attach($stream);
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     * 根据指定参数获取流的元信息，如果参数为null则返回元信息的关联数组
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     * 本方法的行为与stream_get_meta_data()一致
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     *
     * @param string $key Specific metadata to retrieve.
     *
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        $this->meta = stream_get_meta_data($this->stream);
        if (is_null($key) === true) {
            return $this->meta;
        }

        return isset($this->meta[$key]) ? $this->meta[$key] : null;
    }

    /**
     * Is a resource attached to this stream?
     * 对应的底层资源是否绑定到了该流上
     *
     * Note: This method is not part of the PSR-7 standard.
     * 注意，本方法并不是PSR-7标准中的方法
     *
     * @return bool
     */
    protected function isAttached()
    {
        return is_resource($this->stream);
    }

    /**
     * Attach new resource to this object.
     * 绑定新的资源到本对象中
     *
     * Note: This method is not part of the PSR-7 standard.
     * 注意，本方法并不是PSR-7标准中的方法
     *
     * @param resource $newStream A PHP resource handle.
     *
     * @throws InvalidArgumentException If argument is not a valid PHP resource.
     */
    protected function attach($newStream)
    {
        if (is_resource($newStream) === false) {
            throw new InvalidArgumentException(__METHOD__ . ' argument must be a valid PHP resource');
        }

        // 如果有，则先解绑旧流，再去绑定新流
        if ($this->isAttached() === true) {
            $this->detach();
        }

        $this->stream = $newStream;
    }

    /**
     * Separates any underlying resources from the stream.
     * 将流与底层资源解绑
     *
     * After the stream has been detached, the stream is in an unusable state.
     * 流被解绑之后，将处于不可用状态
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        $oldResource = $this->stream;
        $this->stream = null;
        $this->meta = null;
        $this->readable = null;
        $this->writable = null;
        $this->seekable = null;
        $this->size = null;
        $this->isPipe = null;

        return $oldResource;
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     * 将流中的所有数据读取到字符串中
     * 
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     * 本方法必须尝试从流的开头读取，到流的结尾结束
     *
     * Warning: This could attempt to load a large amount of data into memory.
     * 注意：本操作将尝试向内存中加载大量数据
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     * 本方法一定不能触发异常，以保证与PHP的字符串生成方法行为一致
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString()
    {
        // 如果流被解绑，则处于不可用状态，返回空字符串
        if (!$this->isAttached()) {
            return '';
        }

        try {
            // 重置指针，获取所有内容
            $this->rewind();
            return $this->getContents();
        } catch (RuntimeException $e) {
            return '';
        }
    }

    /**
     * Closes the stream and any underlying resources.
     * 关闭流和与其相关的底层资源
     */
    public function close()
    {
        // 先关闭资源
        if ($this->isAttached() === true) {
            if ($this->isPipe()) {
                pclose($this->stream);
            } else {
                fclose($this->stream);
            }
        }

        // 在解绑流
        $this->detach();
    }

    /**
     * Get the size of the stream if known.
     * 在可获取的情况下获取流的大小 如果不可获取，则返回null
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize()
    {
        if (!$this->size && $this->isAttached() === true) {
            $stats = fstat($this->stream);
            // 文件的stat有size，管道stat的size为0，这里返回null
            $this->size = isset($stats['size']) && !$this->isPipe() ? $stats['size'] : null;
        }

        return $this->size;
    }

    /**
     * Returns the current position of the file read/write pointer
     * 获取该流的读/写指针位置
     *
     * @return int Position of the file pointer
     *
     * @throws RuntimeException on error.
     */
    public function tell()
    {
        if (!$this->isAttached() || ($position = ftell($this->stream)) === false || $this->isPipe()) {
            throw new RuntimeException('Could not get the position of the pointer in stream');
        }

        return $position;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     * 判断是否到达流的末尾
     *
     * @return bool
     */
    public function eof()
    {
        return $this->isAttached() ? feof($this->stream) : true;
    }

    /**
     * Returns whether or not the stream is readable.
     * 判断该流是否可读
     *
     * @return bool
     */
    public function isReadable()
    {
        if ($this->readable === null) {
            // 管道是可读的
            if ($this->isPipe()) {
                $this->readable = true;
            } else {
                $this->readable = false;
                if ($this->isAttached()) {
                    $meta = $this->getMetadata();
                    foreach (self::$modes['readable'] as $mode) {
                        if (strpos($meta['mode'], $mode) === 0) {
                            $this->readable = true;
                            break;
                        }
                    }
                }
            }
        }

        return $this->readable;
    }

    /**
     * Returns whether or not the stream is writable.
     * 流是否可写
     *
     * @return bool
     */
    public function isWritable()
    {
        // 管道是不可写的
        if ($this->writable === null) {
            $this->writable = false;
            if ($this->isAttached()) {
                $meta = $this->getMetadata();
                foreach (self::$modes['writable'] as $mode) {
                    if (strpos($meta['mode'], $mode) === 0) {
                        $this->writable = true;
                        break;
                    }
                }
            }
        }

        return $this->writable;
    }

    /**
     * Returns whether or not the stream is seekable.
     * 判断该流是否可寻址
     *
     * @return bool
     */
    public function isSeekable()
    {
        if ($this->seekable === null) {
            $this->seekable = false;
            if ($this->isAttached()) {
                $meta = $this->getMetadata();
                // 未解绑，非管道，可寻址
                $this->seekable = !$this->isPipe() && $meta['seekable'];
            }
        }

        return $this->seekable;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     *
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     *
     * @throws RuntimeException on failure.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        // Note that fseek returns 0 on success!
        // 注意，fseek在成功是返回的是0
        if (!$this->isSeekable() || fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException('Could not seek in stream');
        }
    }

    /**
     * Seek to the beginning of the stream.
     * 将指针定位于流的开头
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     * 加入流是不可寻址的，本方法会触发异常，否则将执行seek(0)
     *
     * @see seek()
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     *
     * @throws RuntimeException on failure.
     */
    public function rewind()
    {
        if (!$this->isSeekable() || rewind($this->stream) === false) {
            throw new RuntimeException('Could not rewind stream');
        }
    }

    /**
     * Read data from the stream.
     * 以字符串形式获取流的剩余内容
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     *
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     *
     * @throws RuntimeException if an error occurs.
     */
    public function read($length)
    {
        if (!$this->isReadable() || ($data = fread($this->stream, $length)) === false) {
            throw new RuntimeException('Could not read from stream');
        }

        return $data;
    }

    /**
     * Write data to the stream.
     * 判断该流是否可读
     *
     * @param string $string The string that is to be written.
     *
     * @return int Returns the number of bytes written to the stream.
     *
     * @throws RuntimeException on failure.
     */
    public function write($string)
    {
        if (!$this->isWritable() || ($written = fwrite($this->stream, $string)) === false) {
            throw new RuntimeException('Could not write to stream');
        }

        // reset size so that it will be recalculated on next call to getSize()
        // 重置size以便重新计算
        $this->size = null;

        return $written;
    }

    /**
     * Returns the remaining contents in a string
     * 以字符串形式获取流的剩余内容
     *
     * @return string
     *
     * @throws RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents()
    {
        if (!$this->isReadable() || ($contents = stream_get_contents($this->stream)) === false) {
            throw new RuntimeException('Could not get contents of stream');
        }

        return $contents;
    }

    /**
     * Returns whether or not the stream is a pipe.
     * 判断该流是否是管道
     *
     * @return bool
     */
    public function isPipe()
    {
        if ($this->isPipe === null) {
            $this->isPipe = false;
            if ($this->isAttached()) {
                $mode = fstat($this->stream)['mode'];
                // 以后解决
                $this->isPipe = ($mode & self::FSTAT_MODE_S_IFIFO) !== 0;
            }
        }

        return $this->isPipe;
    }
}
