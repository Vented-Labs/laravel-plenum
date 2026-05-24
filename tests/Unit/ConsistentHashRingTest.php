<?php

declare(strict_types=1);

use Vented\Plenum\Exceptions\NoHealthyNodesException;
use Vented\Plenum\Hashing\ConsistentHashRing;

it('returns deterministic lookups for the same key', function () {
    $ring = new ConsistentHashRing();
    $ring->setNodes(['n1', 'n2', 'n3']);

    expect($ring->lookup('user:42'))->toBe($ring->lookup('user:42'))
        ->and($ring->lookup(7))->toBe($ring->lookup('7'));
});

it('accepts integer and string keys interchangeably', function () {
    $ring = new ConsistentHashRing();
    $ring->setNodes(['a', 'b']);

    expect($ring->lookup(123))->toBe($ring->lookup('123'));
});

it('distributes keys across all nodes within tolerance', function () {
    $ring = new ConsistentHashRing(replicasPerNode: 128);
    $ring->setNodes(['n1', 'n2', 'n3', 'n4', 'n5']);

    $counts = ['n1' => 0, 'n2' => 0, 'n3' => 0, 'n4' => 0, 'n5' => 0];
    $samples = 2000;

    for ($i = 0; $i < $samples; $i++) {
        $counts[$ring->lookup("user:{$i}")]++;
    }

    foreach ($counts as $count) {
        // expect each node within +/-40% of the average (400) — generous for hash variance
        expect($count)->toBeGreaterThan(240)->toBeLessThan(560);
    }
});

it('reshuffles only ~1/N of keys when a node is removed', function () {
    $ring = new ConsistentHashRing(replicasPerNode: 128);
    $ring->setNodes(['n1', 'n2', 'n3', 'n4']);

    $samples = 2000;
    $before = [];
    for ($i = 0; $i < $samples; $i++) {
        $before[$i] = $ring->lookup("user:{$i}");
    }

    $ring->setNodes(['n1', 'n2', 'n3']);

    $moved = 0;
    for ($i = 0; $i < $samples; $i++) {
        if ($ring->lookup("user:{$i}") !== $before[$i]) {
            $moved++;
        }
    }

    // ideal is ~25% of keys move (one of four nodes gone); allow a wide band
    $ratio = $moved / $samples;
    expect($ratio)->toBeGreaterThan(0.15)->toBeLessThan(0.40);
});

it('returns the same routing across deploys when nodes and replicas match', function () {
    $a = new ConsistentHashRing(replicasPerNode: 64);
    $a->setNodes(['n1', 'n2', 'n3']);

    $b = new ConsistentHashRing(replicasPerNode: 64);
    $b->setNodes(['n3', 'n1', 'n2']);

    for ($i = 0; $i < 100; $i++) {
        expect($a->lookup("k:{$i}"))->toBe($b->lookup("k:{$i}"));
    }
});

it('returns N best candidates in preference order', function () {
    $ring = new ConsistentHashRing();
    $ring->setNodes(['n1', 'n2', 'n3', 'n4']);

    $list = $ring->lookupList('user:42', 3);
    expect($list)->toHaveCount(3)
        ->and(array_unique($list))->toHaveCount(3);
});

it('returns empty array for zero or negative candidate counts', function () {
    $ring = new ConsistentHashRing();
    $ring->setNodes(['n1', 'n2']);

    expect($ring->lookupList('k', 0))->toBe([])
        ->and($ring->lookupList('k', -3))->toBe([]);
});

it('exposes the current node set sorted and deduplicated', function () {
    $ring = new ConsistentHashRing();
    $ring->setNodes(['n3', 'n1', 'n2', 'n1']);

    expect($ring->nodes())->toBe(['n1', 'n2', 'n3']);
});

it('handles a single-node pool correctly', function () {
    $ring = new ConsistentHashRing();
    $ring->setNodes(['only']);

    expect($ring->lookup('anything'))->toBe('only')
        ->and($ring->lookupList('anything', 1))->toBe(['only']);
});

it('throws NoHealthyNodesException when looking up against empty ring', function () {
    $ring = new ConsistentHashRing(driverName: 'database');
    $ring->setNodes([]);

    $ring->lookup('k');
})->throws(NoHealthyNodesException::class, 'database');

it('throws NoHealthyNodesException when listing candidates against empty ring', function () {
    $ring = new ConsistentHashRing(driverName: 'redis');
    $ring->setNodes([]);

    $ring->lookupList('k', 2);
})->throws(NoHealthyNodesException::class, 'redis');

it('starts empty so initial lookups also throw', function () {
    (new ConsistentHashRing())->lookup('k');
})->throws(NoHealthyNodesException::class);
