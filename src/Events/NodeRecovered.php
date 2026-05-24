<?php

declare(strict_types=1);

namespace Vented\Plenum\Events;

final readonly class NodeRecovered
{
    public function __construct(
        public string $driver,
        public string $node,
    ) {}
}
