<?php

declare(strict_types=1);

namespace Vented\Plenum\Drivers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use PDOException;
use Throwable;
use Vented\Plenum\Contracts\ConnectionDriver;

final class DatabaseDriver implements ConnectionDriver
{
    /** @var array<int, string> */
    private readonly array $nodes;

    /**
     * @param  array<int, string>  $nodes
     */
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly ConfigRepository $config,
        array $nodes,
        private readonly string $name = 'database',
    ) {
        $this->nodes = array_values($nodes);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function nodes(): array
    {
        return $this->nodes;
    }

    public function activate(string $node): void
    {
        $this->db->setDefaultConnection($node);
        $this->config->set('database.default', $node);
    }

    public function ping(string $node): bool
    {
        try {
            $this->db->connection($node)->select('select 1');

            return true;
        } catch (Throwable) {
            // Force the next request to rebuild the PDO instance instead of reusing the broken one.
            $this->db->purge($node);

            return false;
        }
    }

    public function failoverExceptions(): array
    {
        return [PDOException::class];
    }
}
