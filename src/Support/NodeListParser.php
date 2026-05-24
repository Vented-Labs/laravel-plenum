<?php

declare(strict_types=1);

namespace Vented\Plenum\Support;

use InvalidArgumentException;

final class NodeListParser
{
    /**
     * @return array<string, array{host: string, port: int}>
     */
    public static function parse(string $input, int $defaultPort): array
    {
        $input = trim($input);

        if ($input === '') {
            return [];
        }

        $entries = array_filter(array_map('trim', explode(',', $input)), static fn (string $e): bool => $e !== '');

        $result = [];
        $autoIndex = 1;

        foreach ($entries as $entry) {
            [$name, $hostPort] = self::splitNameAndHost($entry, $autoIndex);
            [$host, $port] = self::splitHostAndPort($hostPort, $defaultPort);

            if ($name === '') {
                throw new InvalidArgumentException("Node entry has empty name: \"{$entry}\".");
            }

            if ($host === '') {
                throw new InvalidArgumentException("Node \"{$name}\" has empty host.");
            }

            $result[$name] = ['host' => $host, 'port' => $port];
            $autoIndex++;
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitNameAndHost(string $entry, int $autoIndex): array
    {
        if (str_contains($entry, '=')) {
            [$name, $hostPort] = explode('=', $entry, 2);

            return [trim($name), trim($hostPort)];
        }

        return ["node_{$autoIndex}", $entry];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function splitHostAndPort(string $hostPort, int $defaultPort): array
    {
        if (str_contains($hostPort, ':')) {
            [$host, $port] = explode(':', $hostPort, 2);

            return [trim($host), (int) trim($port)];
        }

        return [trim($hostPort), $defaultPort];
    }
}
