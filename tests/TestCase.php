<?php

namespace Vented\Plenum\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vented\Plenum\PlenumServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PlenumServiceProvider::class,
        ];
    }
}
