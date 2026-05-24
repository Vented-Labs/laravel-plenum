<?php

declare(strict_types=1);

namespace Vented\Plenum\Tests\Integration;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Vented\Plenum\Plenum;
use Vented\Plenum\Tests\TestCase;

abstract class IntegrationCase extends TestCase
{
    /** @var array<string, array{host: string, port: int}> */
    public const array ENDPOINTS = [
        'mysql_1' => ['host' => '127.0.0.1', 'port' => 33061],
        'mysql_2' => ['host' => '127.0.0.1', 'port' => 33062],
        'valkey_1' => ['host' => '127.0.0.1', 'port' => 63791],
        'valkey_2' => ['host' => '127.0.0.1', 'port' => 63792],
    ];

    /**
     * Throw `MarkTestSkipped` if any required endpoint is unreachable.
     *
     * @param  array<int, string>  $names
     */
    protected function requireBackends(array $names): void
    {
        foreach ($names as $name) {
            $endpoint = self::ENDPOINTS[$name];
            $sock = @fsockopen($endpoint['host'], $endpoint['port'], $errno, $errstr, 1.0);
            if ($sock === false) {
                $this->markTestSkipped("Integration backend {$name} ({$endpoint['host']}:{$endpoint['port']}) unreachable. Run `docker compose -f docker/integration/docker-compose.yml up -d --wait` first.");
            }
            fclose($sock);
        }
    }

    /**
     * Replace the cached Plenum singleton so subsequent resolves pick up the
     * config changes the test just made.
     *
     * Also forgets the RedisManager because it snapshots `database.redis` at
     * construction time — without forgetting it, runtime config changes to
     * `database.redis.*` are invisible to the manager.
     */
    protected function rebuildPlenum(): Plenum
    {
        $this->app->forgetInstance(Plenum::class);
        if ($this->app->resolved('redis')) {
            $this->app->forgetInstance('redis');
        }

        return $this->app->make(Plenum::class);
    }

    /** @param  array<string, mixed>  $values */
    protected function setConfig(array $values): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make(ConfigRepository::class);
        foreach ($values as $key => $value) {
            $config->set($key, $value);
        }
    }
}
