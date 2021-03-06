<?php

namespace module\lib\listener;

/**
 * Class ListenerQueue - 一个事件的监听器队列存储管理类
 */
class ListenerQueue implements \IteratorAggregate, \Countable
{
    /**
     * 对象存储器
     * @var \SplObjectStorage
     */
    private $store;

    /**
     * 优先级队列
     * @var \SplPriorityQueue
     */
    private $queue;

    /**
     * 计数器。设定最大值为 PHP_INT_MAX
     * @var int
     */
    private $counter = \PHP_INT_MAX;

    public function __construct()
    {
        $this->store = new \SplObjectStorage();
        $this->queue = new \SplPriorityQueue();
    }

    /**
     * 添加一个监听器, 增加了添加 callback(string|array)
     * @param \Closure|callable|\stdClass|mixed $listener 监听器
     * @param integer                           $priority 优先级
     * @return $this
     */
    public function add($listener, int $priority)
    {
        // transfer to object. like string/array
        if (!\is_object($listener)) {
            $listener = new LazyListener($listener);
        }

        if (!$this->has($listener)) {
            // Compute the internal priority as an array. 计算内部优先级为一个数组。
            // @see http://php.net/manual/zh/splpriorityqueue.compare.php#93999
            $priorityData = [$priority, $this->counter--];

            $this->store->attach($listener, $priorityData);
            $this->queue->insert($listener, $priorityData);
        }

        return $this;
    }

    /**
     * 删除一个监听器
     * @param object $listener
     * @return $this
     */
    public function remove($listener)
    {
        if ($this->has($listener)) {
            $this->store->detach($listener);
            $this->store->rewind();

            $queue = new \SplPriorityQueue();

            foreach ($this->store as $otherListener) {
                // 优先级信息 @see self::add(). It like `[priority, counter value]`
                $priority = $this->store->getInfo();
                $queue->insert($otherListener, $priority);
            }

            $this->queue = $queue;
        }

        return $this;
    }

    /**
     * Get the priority of the given listener. 得到指定监听器的优先级
     * @param   mixed $listener The listener.
     * @param   int   $default The default value to return if the listener doesn't exist.
     * @return  mixed  The listener priority if it exists, null otherwise.
     */
    public function getPriority($listener, int $default = null)
    {
        if ($this->store->contains($listener)) {
            // @see self::add(). attach as: `[priority, counter value]`
            return $this->store[$listener][0];
        }

        return $default;
    }

    /**
     * getPriority() alias method
     * @param     $listener
     * @param int $default
     * @return mixed
     */
    public function getLevel($listener, int $default = null)
    {
        return $this->getPriority($listener, $default);
    }

    /**
     * Get all listeners contained in this queue, sorted according to their priority.
     * @return  mixed[]  An array of listeners.
     */
    public function getAll()
    {
        $listeners = [];

        // Get a clone of the queue.
        $queue = $this->getIterator();

        foreach ($queue as $listener) {
            $listeners[] = $listener;
        }

        unset($queue);
        return $listeners;
    }

    /**
     * @param object $listener
     * @return bool
     */
    public function has($listener): bool
    {
        return $this->store->contains($listener);
    }

    /**
     * @param object $listener
     * @return bool
     */
    public function exists($listener): bool
    {
        return $this->has($listener);
    }

    /**
     * Get the inner queue with its cursor on top of the heap.
     * @return  \SplPriorityQueue  The inner queue.
     */
    public function getIterator()
    {
        // SplPriorityQueue queue is a heap.
        $queue = clone $this->queue;

        // rewind pointer.
        if (!$queue->isEmpty()) {
            $queue->top();
        }

        return $queue;
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->queue);
    }

    /**
     * clear queue
     */
    public function clear(): void
    {
        $this->queue = $this->store = null;
    }
}
