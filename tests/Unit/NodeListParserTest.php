<?php

declare(strict_types=1);

use Vented\Plenum\Support\NodeListParser;

dataset('valid_inputs', [
    'empty string' => [
        '', 5432, [],
    ],
    'whitespace-only string' => [
        '   ', 5432, [],
    ],
    'newlines and tabs only' => [
        "\n\t  \r", 5432, [],
    ],
    'single named host with port' => [
        'db_1=host1:5432',
        5432,
        ['db_1' => ['host' => 'host1', 'port' => 5432]],
    ],
    'multiple named entries' => [
        'a=h1:1111,b=h2:2222',
        5432,
        [
            'a' => ['host' => 'h1', 'port' => 1111],
            'b' => ['host' => 'h2', 'port' => 2222],
        ],
    ],
    'default port when port missing on named entry' => [
        'db_1=host1',
        5432,
        ['db_1' => ['host' => 'host1', 'port' => 5432]],
    ],
    'auto-named unnamed entries' => [
        'host1:1111,host2:2222',
        5432,
        [
            'node_1' => ['host' => 'host1', 'port' => 1111],
            'node_2' => ['host' => 'host2', 'port' => 2222],
        ],
    ],
    'mixed named and unnamed entries' => [
        'db_1=host1,host2:2222',
        5432,
        [
            'db_1' => ['host' => 'host1', 'port' => 5432],
            'node_2' => ['host' => 'host2', 'port' => 2222],
        ],
    ],
    'auto-named without port uses default' => [
        'host1,host2',
        6379,
        [
            'node_1' => ['host' => 'host1', 'port' => 6379],
            'node_2' => ['host' => 'host2', 'port' => 6379],
        ],
    ],
    'trims internal whitespace around name and host' => [
        '  db_1  =  host1  :  5432  ',
        5432,
        ['db_1' => ['host' => 'host1', 'port' => 5432]],
    ],
    'trailing comma is ignored' => [
        'host1:1111,host2:2222,',
        5432,
        [
            'node_1' => ['host' => 'host1', 'port' => 1111],
            'node_2' => ['host' => 'host2', 'port' => 2222],
        ],
    ],
    'consecutive commas are ignored' => [
        'host1:1111,,,host2:2222',
        5432,
        [
            'node_1' => ['host' => 'host1', 'port' => 1111],
            'node_2' => ['host' => 'host2', 'port' => 2222],
        ],
    ],
    'leading comma is ignored' => [
        ',host1:1111',
        5432,
        ['node_1' => ['host' => 'host1', 'port' => 1111]],
    ],
    'port zero is accepted' => [
        'host1:0',
        5432,
        ['node_1' => ['host' => 'host1', 'port' => 0]],
    ],
    'non-numeric port casts to int' => [
        'host1:abc',
        5432,
        ['node_1' => ['host' => 'host1', 'port' => 0]],
    ],
    'redis default port honored' => [
        'r=host',
        6379,
        ['r' => ['host' => 'host', 'port' => 6379]],
    ],
    'dotted hostnames preserved' => [
        'db_1=db-1.internal:5432,db_2=db-2.internal:5432',
        5432,
        [
            'db_1' => ['host' => 'db-1.internal', 'port' => 5432],
            'db_2' => ['host' => 'db-2.internal', 'port' => 5432],
        ],
    ],
]);

it('parses inputs into the expected array', function (string $input, int $port, array $expected) {
    expect(NodeListParser::parse($input, $port))->toBe($expected);
})->with('valid_inputs');

it('throws on entry with empty name', function () {
    NodeListParser::parse('=host', 5432);
})->throws(InvalidArgumentException::class, 'empty name');

it('throws on whitespace-only name', function () {
    NodeListParser::parse('   =host', 5432);
})->throws(InvalidArgumentException::class, 'empty name');

it('throws on entry with empty host (name=)', function () {
    NodeListParser::parse('db_1=', 5432);
})->throws(InvalidArgumentException::class, 'empty host');

it('throws on entry with whitespace-only host', function () {
    NodeListParser::parse('db_1= : 5432', 5432);
})->throws(InvalidArgumentException::class, 'empty host');

it('throws with the offending entry quoted in the message', function () {
    try {
        NodeListParser::parse('=brokenhost', 5432);
        expect(false)->toBeTrue('expected exception');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('=brokenhost');
    }
});

it('throws with the named offender in the message for empty host', function () {
    try {
        NodeListParser::parse('mynode=', 5432);
        expect(false)->toBeTrue('expected exception');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('mynode');
    }
});
