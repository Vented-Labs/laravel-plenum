<?php

declare(strict_types=1);

namespace Vented\Plenum\Console;

use Illuminate\Console\Command;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Plenum;

final class DiagnoseCommand extends Command
{
    protected $signature = 'plenum:diagnose';

    protected $description = 'Show Plenum routing configuration and current node health.';

    public function handle(Plenum $plenum, HealthChecker $health): int
    {
        if ($plenum->drivers() === []) {
            $this->warn('Plenum has no drivers registered. Set PLENUM_DB_NODES or PLENUM_REDIS_NODES to enable routing.');

            return self::SUCCESS;
        }

        $this->line('<info>Strategy:</info> '.$plenum->strategy()->name());
        $this->newLine();

        foreach ($plenum->drivers() as $name => $driver) {
            $this->line("<info>Driver:</info> {$name} (".$driver::class.')');

            $rows = [];
            foreach ($driver->nodes() as $node) {
                $rows[] = [
                    $node,
                    $health->isHealthy($name, $node) ? '<fg=green>up</>' : '<fg=red>down</>',
                ];
            }

            $this->table(['Node', 'Status'], $rows);
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
