<?php

declare(strict_types=1);

namespace Vented\Plenum\Events;

use Throwable;

final readonly class FailoverOccurred
{
    public function __construct(
        public string $driver,
        public string $fromNode,
        public string $toNode,
        public Throwable $reason,
    ) {}
}
