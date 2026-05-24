<?php

declare(strict_types=1);

namespace Vented\Plenum\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Throwable;
use Vented\Plenum\Contracts\HealthChecker;
use Vented\Plenum\Plenum;

final class DashboardController
{
    public function __invoke(Plenum $plenum, HealthChecker $health, ConfigRepository $config): View
    {
        $samples = max(0, (int) $config->get('plenum.dashboard.distribution_samples', 1000));

        $drivers = [];
        foreach ($plenum->drivers() as $name => $driver) {
            $nodes = [];
            foreach ($driver->nodes() as $node) {
                $nodes[] = [
                    'name' => $node,
                    'healthy' => $health->isHealthy($name, $node),
                ];
            }

            $drivers[] = [
                'name' => $name,
                'class' => $driver::class,
                'nodes' => $nodes,
                'distribution' => $this->distribution($plenum, $name, $driver->nodes(), $samples),
            ];
        }

        return view('plenum::dashboard', [
            'strategy' => $plenum->strategy()->name(),
            'drivers' => $drivers,
            'samples' => $samples,
        ]);
    }

    /**
     * @param  array<int, string>  $nodes
     * @return array<int, array{node: string, count: int, share: float}>
     */
    private function distribution(Plenum $plenum, string $driver, array $nodes, int $samples): array
    {
        if ($samples === 0 || $nodes === []) {
            return [];
        }

        $counts = array_fill_keys($nodes, 0);

        for ($i = 0; $i < $samples; $i++) {
            try {
                $counts[$plenum->nodeFor($driver, "sample:{$i}")]++;
            } catch (Throwable) {
                // Unroutable when no nodes are healthy; reflected as zero counts.
            }
        }

        $rows = [];
        foreach ($counts as $node => $count) {
            $rows[] = [
                'node' => (string) $node,
                'count' => $count,
                'share' => $samples > 0 ? round(($count / $samples) * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }
}
