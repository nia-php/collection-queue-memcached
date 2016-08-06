# nia - Collection Memcached Queue

Implementation of the nia queue component using memcached.

The `enqueue()` and `dequeue()` method implementations of the `Nia\Collection\Queue\Memcached\FifoMemcachedQueue` and `Nia\Collection\Queue\Memcached\LifoMemcachedQueue` class are race condition safe, so it is possible to use them as a worker message queue.

## Installation

Require this package with Composer.

```bash
    composer require nia/collection-queue-memcached
```

## Tests
To run the unit test use the following command:

```bash
    cd /path/to/nia/component/
    phpunit --bootstrap=vendor/autoload.php tests/
```

## How to use
The following sample shows you how to use the `Nia\Collection\Queue\Memcached\FifoMemcachedQueue` class of the queue component.

```php
    $memcached = new Memcached();
    $memcached->addserver('localhost', 11211);

    $queue = new FifoMemcachedQueue($memcached, 'my-queue-name');
    $queue->enqueue('abc');
    $queue->enqueue('def');

    while ($element = $queue->dequeue()) {
        echo $element . "\n";
    }

    // Outputs:
    //  abc
    //  def
```
