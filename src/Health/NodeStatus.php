<?php

declare(strict_types=1);

namespace Vented\Plenum\Health;

use DateTimeImmutable;

final readonly class NodeStatus
{
    public function __construct(
        public string $driver,
        public string $node,
        public bool $healthy,
        public DateTimeImmutable $checkedAt,
        public ?string $reason = null,
    ) {}
}
