<?php

namespace Psr\Http\Message;

/**
 * Describes a data stream.
 * 本接口用于描述数据流
 *
 * Typically, an instance will wrap a PHP stream; this interface provides
 * a wrapper around the most common operations, including serialization of
 * the entire stream to a string.
 * 一般来说，PHP流将被实例包裹，本接口提供了一个具备通用操作的包装器，包裹将实体
 * 流序列化为字符串
 */
interface StreamInterface
{
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
    public function __toString();

    /**
     * Closes the stream and any underlying resources.
     * 关闭流和与其相关的底层资源
     *
     * @return void
     */
    public function close();

    /**
     * Separates any underlying resources from the stream.
     * 将流与底层资源解绑
     *
     * After the stream has been detached, the stream is in an unusable state.
     * 流被解绑之后，将处于不可用状态
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach();

    /**
     * Get the size of the stream if known.
     * 在可获取的情况下获取流的大小 如果不可获取，则返回null
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize();

    /**
     * Returns the current position of the file read/write pointer
     * 获取该流的读/写指针位置
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell();

    /**
     * Returns true if the stream is at the end of the stream.
     * 判断是否到达流的末尾
     *
     * @return bool
     */
    public function eof();

    /**
     * Returns whether or not the stream is seekable.
     * 判断该流是否可寻址
     *
     * @return bool
     */
    public function isSeekable();

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     */
    public function seek($offset, $whence = SEEK_SET);

    /**
     * Seek to the beginning of the stream.
     * 将指针定位于流的开头
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     * 加入流是不可寻址的，本方法会触发异常，否则将执行seek(0)
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     */
    public function rewind();

    /**
     * Returns whether or not the stream is writable.
     * 流是否可写
     *
     * @return bool
     */
    public function isWritable();

    /**
     * Write data to the stream.
     * 向流写入数据
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string);

    /**
     * Returns whether or not the stream is readable.
     * 判断该流是否可读
     *
     * @return bool
     */
    public function isReadable();

    /**
     * Read data from the stream.
     * 从数据流中读取
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read($length);

    /**
     * Returns the remaining contents in a string
     * 以字符串形式获取流的剩余内容
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents();

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     * 根据指定参数获取流的元信息，如果参数为null则返回元信息的关联数组
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     * 本方法的行为与stream_get_meta_data()一致
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null);
}
