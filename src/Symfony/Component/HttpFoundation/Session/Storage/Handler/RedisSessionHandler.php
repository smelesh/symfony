<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

use Predis\Response\ErrorInterface;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;

/**
 * Redis based session storage handler based on the Redis class
 * provided by the PHP redis extension.
 *
 * @author Dalibor Karlović <dalibor@flexolabs.io>
 */
class RedisSessionHandler extends AbstractSessionHandler
{
    private \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|RedisProxy|RedisClusterProxy $redis;

    /**
     * Key prefix for shared environments.
     */
    private string $prefix;

    /**
     * Time to live in seconds.
     */
    private ?int $ttl;

    /**
     * List of available options:
     *  * prefix: The prefix to use for the keys in order to avoid collision on the Redis server
     *  * ttl: The time to live in seconds.
     *
     * @throws \InvalidArgumentException When unsupported client or options are passed
     */
    public function __construct(\Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|RedisProxy|RedisClusterProxy $redis, array $options = [])
    {
        if ($diff = array_diff(array_keys($options), ['prefix', 'ttl'])) {
            throw new \InvalidArgumentException(sprintf('The following options are not supported "%s".', implode(', ', $diff)));
        }

        $this->redis = $redis;
        $this->prefix = $options['prefix'] ?? 'sf_s';
        $this->ttl = $options['ttl'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    protected function doRead(string $sessionId): string
    {
        return $this->redis->get($this->prefix.$sessionId) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite(string $sessionId, string $data): bool
    {
        $result = $this->redis->setEx($this->prefix.$sessionId, (int) ($this->ttl ?? ini_get('session.gc_maxlifetime')), $data);

        return $result && !$result instanceof ErrorInterface;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDestroy(string $sessionId): bool
    {
        static $unlink = true;

        if ($unlink) {
            try {
                $unlink = false !== $this->redis->unlink($this->prefix.$sessionId);
            } catch (\Throwable $e) {
                $unlink = false;
            }
        }

        if (!$unlink) {
            $this->redis->del($this->prefix.$sessionId);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function close(): bool
    {
        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        return 0;
    }

    public function updateTimestamp(string $sessionId, string $data): bool
    {
        return $this->redis->expire($this->prefix.$sessionId, (int) ($this->ttl ?? ini_get('session.gc_maxlifetime')));
    }
}
