<?php
/*
 * This file is part of the nia framework architecture.
 *
 * (c) Patrick Ullmann <patrick.ullmann@nat-software.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types = 1);
namespace Nia\Collection\Queue\Memcached;

use Memcached;
use Nia\Collection\Queue\QueueInterface;

/**
 * Last-In-First-Out queue (similar to a stack) implementation using memcached.
 *
 * ATTENTION:
 * ----------
 * The methods enqueue() and dequeue() are race condition safe.
 * All other methods are not race condition safe because memcached nor php
 * provide the required functionality for clear(), count() and getIterator().
 *
 * NOTICE:
 * -------
 * Memcached::decrement does not allow values below 0, so the initial
 * counting of elements starts at 2 and not with 1. This is required because
 * in dequeue() is a check needed if there are any elements in the queue to
 * be race conditional safe.
 */
class LifoMemcachedQueue implements QueueInterface
{

    /**
     * The used memcached instance.
     *
     * @var Memcached
     */
    private $memcached = null;

    /**
     * Name of the queue.
     *
     * @var string
     */
    private $queueName = null;

    /**
     * Expiration time of the element in seconds.
     *
     * @var int
     */
    private $expire = null;

    /**
     * Constructor.
     *
     * @param Memcached $memcached
     *            The used memcached instance.
     * @param string $queueName
     *            Name of the queue.
     * @param mixed[] $elements
     *            List with elements to add to queue.
     * @param int $expire
     *            Expiration time of the element in seconds.
     */
    public function __construct(Memcached $memcached, string $queueName, array $elements = [], int $expire = null)
    {
        $this->memcached = $memcached;
        $this->queueName = $queueName;
        $this->expire = $expire ?? 86400; // 24h

        foreach ($elements as $element) {
            $this->enqueue($element);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Nia\Collection\Queue\QueueInterface::clear()
     */
    public function clear(): QueueInterface
    {
        $maxEnqueued = (int) $this->memcached->get($this->queueName . '--max-enqueued');

        if ($maxEnqueued) {
            $elementNames = array_map(function ($id) {
                return $this->queueName . '--' . $id;
            }, range(2, $maxEnqueued));

            $this->memcached->deleteMulti($elementNames);
            $this->memcached->delete($this->queueName . '--max-enqueued');
        }

        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Nia\Collection\Queue\QueueInterface::count()
     */
    public function count(): int
    {
        $maxEnqueued = (int) $this->memcached->get($this->queueName . '--max-enqueued');

        if ($maxEnqueued <= 1) {
            return 0;
        }

        return $maxEnqueued - 1;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Nia\Collection\Queue\QueueInterface::dequeue()
     */
    public function dequeue()
    {
        $id = $this->memcached->decrement($this->queueName . '--max-enqueued');

        if ($id === false) {
            return null;
        }

        if ($id > 0) {
            // because the queue starts with 1 and not with 0,
            // we have to increment the id to get the real id.
            ++ $id;

            $result = $this->memcached->get($this->queueName . '--' . $id);

            $this->memcached->delete($this->queueName . '--' . $id);

            return $result;
        }

        $this->memcached->increment($this->queueName . '--max-enqueued');

        return null;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Nia\Collection\Queue\QueueInterface::enqueue($value)
     */
    public function enqueue($value): QueueInterface
    {
        $id = $this->memcached->increment($this->queueName . '--max-enqueued');

        if ($id === false) {
            if (! $this->memcached->add($this->queueName . '--max-enqueued', 2, $this->expire)) {
                $id = $this->memcached->increment($this->queueName . '--max-enqueued');
            } else {
                $id = 2;
            }
        }

        $this->memcached->set($this->queueName . '--' . $id, $value, $this->expire);

        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        $maxEnqueued = (int) $this->memcached->get($this->queueName . '--max-enqueued');

        $elements = [];

        if ($maxEnqueued) {
            $elementNames = array_map(function ($id) {
                return $this->queueName . '--' . $id;
            }, range(2, $maxEnqueued));

            $cas = null;
            $elements = array_values($this->memcached->getMulti($elementNames));
        }

        return new \ArrayIterator($elements);
    }
}
