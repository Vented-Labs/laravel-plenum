<?php

declare(strict_types=1);

namespace Vented\Plenum\Console;

use Illuminate\Console\Command;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Plenum;

class ProbeCommand extends Command
{
    protected $signature = 'plenum:probe
        {--watch : Run continuously until killed}
        {--interval=10 : Seconds between cycles in watch mode}
        {--max-cycles=0 : Stop after N cycles (0 = unlimited; used for testing)}';

    protected $description = 'Probe every configured node and update the shared health cache.';

    /** @var (callable(int): void)|null */
    public static $sleeper = null;

    public function handle(Plenum $plenum, HealthChecker $health): int
    {
        if ($plenum->drivers() === []) {
            $this->warn('Plenum has no drivers registered.');

            return self::SUCCESS;
        }

        if (! $this->option('watch')) {
            return $this->probeOnce($plenum, $health);
        }

        return $this->watchLoop($plenum, $health);
    }

    private function probeOnce(Plenum $plenum, HealthChecker $health): int
    {
        $allHealthy = true;

        foreach ($plenum->drivers() as $name => $driver) {
            foreach ($driver->nodes() as $node) {
                $status = $health->probe($name, $node, $driver);
                $marker = $status->healthy ? '<fg=green>✓</>' : '<fg=red>✗</>';
                $this->line("  {$marker} {$name}/{$node}");

                if (! $status->healthy) {
                    $allHealthy = false;
                }
            }
        }

        return $allHealthy ? self::SUCCESS : self::FAILURE;
    }

    private function watchLoop(Plenum $plenum, HealthChecker $health): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $maxCycles = max(0, (int) $this->option('max-cycles'));
        $sleeper = self::$sleeper ?? 'sleep';
        $cycles = 0;
        $lastExit = self::SUCCESS;

        while (true) {
            $this->line('<info>['.date('Y-m-d H:i:s').']</info>');
            $lastExit = $this->probeOnce($plenum, $health);

            $cycles++;
            if ($maxCycles > 0 && $cycles >= $maxCycles) {
                return $lastExit;
            }

            $sleeper($interval);
        }
    }
}
