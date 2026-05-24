<?php

declare(strict_types=1);

namespace Vented\Plenum\Drivers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Throwable;
use Vented\Plenum\Contracts\ConnectionDriver;

final class RedisDriver implements ConnectionDriver
{
    public const ACTIVE_BINDING = 'plenum.active_redis';

    /** @var array<int, string> */
    private readonly array $nodes;

    /**
     * @param  array<int, string>  $nodes
     */
    public function __construct(
        private readonly RedisFactory $redis,
        private readonly Container $container,
        array $nodes,
        private readonly string $name = 'redis',
    ) {
        $this->nodes = array_values($nodes);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function nodes(): array
    {
        return $this->nodes;
    }

    public function activate(string $node): void
    {
        $this->container->instance(self::ACTIVE_BINDING, $node);
    }

    public function deactivate(): void
    {
        $this->container->forgetInstance(self::ACTIVE_BINDING);
    }

    public function ping(string $node): bool
    {
        try {
            $this->redis->connection($node)->ping();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function failoverExceptions(): array
    {
        // Plain strings rather than ::class so the driver works whether or not
        // phpredis/predis are installed — instanceof with a missing class is false.
        return [
            'RedisException',
            'Predis\\Connection\\ConnectionException',
            'Predis\\CommunicationException',
        ];
    }
}
