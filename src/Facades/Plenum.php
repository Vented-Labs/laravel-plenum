<?php

namespace Vented\Plenum\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Vented\Plenum\Plenum
 */
class Plenum extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vented\Plenum\Plenum::class;
    }
}
