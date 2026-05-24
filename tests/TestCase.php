<?php

declare(strict_types=1);

namespace Vented\Plenum\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vented\Plenum\PlenumServiceProvider;

class TestCase extends Orchestra
{
    public static ?string $bootEnv = null;

    /** @var array<string, mixed> */
    public static array $bootConfig = [];

    protected function getPackageProviders($app)
    {
        return [
            PlenumServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Sessions / encrypted cookies require an APP_KEY. Orchestra leaves it
        // blank by default; set one so tests that hit the web middleware group
        // can run without each suite re-implementing this.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Force an in-memory cache so the health cache used by Plenum doesn't
        // need a `cache` table. Testbench's default .env may set this to
        // `database` after workbench bootstrapping.
        $app['config']->set('cache.default', 'array');

        if (static::$bootEnv !== null) {
            $app->detectEnvironment(fn () => static::$bootEnv);
        }

        foreach (static::$bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    /**
     * Re-boot the application with a specific env and optional config overrides.
     *
     * Dashboard registration happens in the service provider's boot phase, so
     * env/config changes after setUp() require a full application refresh to
     * affect routing.
     *
     * @param  array<string, mixed>  $config
     */
    public function bootApp(string $env, array $config = []): void
    {
        static::$bootEnv = $env;
        static::$bootConfig = $config;

        $this->refreshApplication();
    }

    protected function tearDown(): void
    {
        \Vented\Plenum\Facades\Plenum::$authUsing = null;
        static::$bootEnv = null;
        static::$bootConfig = [];

        parent::tearDown();
    }
}
