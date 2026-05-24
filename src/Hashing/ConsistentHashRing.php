<?php

declare(strict_types=1);

namespace Vented\Plenum\Hashing;

use Esi\ConsistentHash\ConsistentHash;
use Esi\ConsistentHash\Hasher\Crc32Hasher;
use Esi\ConsistentHash\Hasher\HasherInterface;
use Vented\Plenum\Contracts\HashRing;
use Vented\Plenum\Exceptions\NoHealthyNodesException;

final class ConsistentHashRing implements HashRing
{
    private ConsistentHash $ring;

    /** @var array<int, string> */
    private array $nodes = [];

    public function __construct(
        private readonly int $replicasPerNode = 64,
        private readonly ?HasherInterface $hasher = null,
        private readonly string $driverName = 'default',
    ) {
        $this->ring = $this->makeRing();
    }

    public function setNodes(array $nodes): void
    {
        $unique = array_values(array_unique($nodes));
        sort($unique);

        $this->nodes = $unique;
        $this->ring = $this->makeRing();

        if ($unique !== []) {
            $this->ring->addTargets($unique);
        }
    }

    public function lookup(int|string $key): string
    {
        $this->assertNotEmpty();

        return $this->ring->lookup((string) $key);
    }

    public function lookupList(int|string $key, int $count): array
    {
        $this->assertNotEmpty();

        if ($count <= 0) {
            return [];
        }

        return $this->ring->lookupList((string) $key, $count);
    }

    public function nodes(): array
    {
        return $this->nodes;
    }

    private function makeRing(): ConsistentHash
    {
        return new ConsistentHash($this->hasher ?? new Crc32Hasher(), $this->replicasPerNode);
    }

    private function assertNotEmpty(): void
    {
        if ($this->nodes === []) {
            throw NoHealthyNodesException::empty($this->driverName);
        }
    }
}
