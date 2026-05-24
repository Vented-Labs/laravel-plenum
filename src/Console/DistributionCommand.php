<?php

declare(strict_types=1);

namespace Vented\Plenum\Console;

use Illuminate\Console\Command;
use Throwable;
use Vented\Plenum\Contracts\ConnectionDriver;
use Vented\Plenum\Plenum;

class DistributionCommand extends Command
{
    protected $signature = 'plenum:distribution
        {driver? : Restrict to a single driver}
        {--samples=1000 : How many synthetic keys to bucket}
        {--prefix=sample : Prefix used to construct sample keys}';

    protected $description = 'Bucket synthetic keys across the ring to verify a balanced distribution.';

    public function handle(Plenum $plenum): int
    {
        $samples = max(0, (int) $this->option('samples'));
        $prefixOption = $this->option('prefix');
        $prefix = is_string($prefixOption) ? $prefixOption : 'sample';

        $drivers = $this->selectedDrivers($plenum);
        if ($drivers === []) {
            $this->warn('Plenum has no drivers registered.');

            return self::SUCCESS;
        }

        foreach ($drivers as $name => $driver) {
            $this->line("<info>Driver: {$name}</info>");
            $this->renderDistribution($plenum, $name, $driver, $samples, $prefix);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, ConnectionDriver>
     */
    private function selectedDrivers(Plenum $plenum): array
    {
        $requested = $this->argument('driver');
        if (! is_string($requested)) {
            return $plenum->drivers();
        }

        return [$requested => $plenum->driver($requested)];
    }

    private function renderDistribution(Plenum $plenum, string $name, ConnectionDriver $driver, int $samples, string $prefix): void
    {
        $counts = array_fill_keys($driver->nodes(), 0);

        for ($i = 0; $i < $samples; $i++) {
            try {
                $counts[$plenum->nodeFor($name, "{$prefix}:{$i}")]++;
            } catch (Throwable) {
                // Skip unroutable keys; reported by the share=0 row.
            }
        }

        $rows = [];
        foreach ($counts as $node => $count) {
            $share = $samples > 0 ? round(($count / $samples) * 100, 1).'%' : '–';
            $rows[] = [$node, $count, $share];
        }

        $this->table(['Node', 'Count', 'Share'], $rows);
    }
}
