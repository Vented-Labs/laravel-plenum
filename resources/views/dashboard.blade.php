<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Plenum</title>

    {{-- Apply theme before paint to avoid FOUC. --}}
    <script>
        (function () {
            try {
                var forced = new URLSearchParams(window.location.search).get('theme');
                var stored = window.localStorage.getItem('plenum-theme');
                var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                var theme = (forced === 'dark' || forced === 'light')
                    ? forced
                    : (stored || (prefersDark ? 'dark' : 'light'));
                document.documentElement.dataset.theme = theme;
            } catch (e) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>

    {!! \Vented\Plenum\Facades\Plenum::css() !!}
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-900 antialiased font-sans selection:bg-neutral-900 selection:text-white dark:bg-neutral-950 dark:text-neutral-100 dark:selection:bg-white dark:selection:text-neutral-900">
<div class="mx-auto max-w-4xl px-6 py-12">

    <header class="flex items-center justify-between gap-6 mb-6">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-neutral-900 text-white shadow-sm dark:bg-white dark:text-neutral-900" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M3 12h18"></path>
                    <path d="M12 3a13 13 0 0 1 0 18"></path>
                    <path d="M12 3a13 13 0 0 0 0 18"></path>
                </svg>
            </span>
            <div>
                <div class="text-base font-semibold tracking-tight leading-tight">Plenum</div>
                <div class="text-xs text-neutral-500 dark:text-neutral-400">Application-layer routing</div>
            </div>
        </div>
        <div class="flex items-center gap-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="text-neutral-500 uppercase tracking-widest dark:text-neutral-400">Strategy</span>
                <span class="inline-flex items-center rounded-lg bg-neutral-900 px-2.5 py-1 text-[11px] font-medium tracking-wider text-white dark:bg-white dark:text-neutral-900">{{ $strategy }}</span>
            </div>

            <button
                id="plenum-theme-toggle"
                type="button"
                aria-label="Toggle colour scheme"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-neutral-200/80 bg-white text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
            >
                {{-- Moon (light mode, click → dark) --}}
                <svg class="h-4 w-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
                {{-- Sun (dark mode, click → light) --}}
                <svg class="hidden h-4 w-4 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2"></path>
                    <path d="M12 20v2"></path>
                    <path d="m4.93 4.93 1.41 1.41"></path>
                    <path d="m17.66 17.66 1.41 1.41"></path>
                    <path d="M2 12h2"></path>
                    <path d="M20 12h2"></path>
                    <path d="m6.34 17.66-1.41 1.41"></path>
                    <path d="m19.07 4.93-1.41 1.41"></path>
                </svg>
            </button>
        </div>
    </header>

    @if ($drivers === [])
        <section class="rounded-3xl border border-neutral-200/80 bg-white p-2 shadow-[0_1px_2px_rgba(15,15,15,0.04)] dark:border-neutral-800/80 dark:bg-neutral-900 dark:shadow-none">
            <div class="rounded-2xl bg-neutral-50 px-8 py-12 text-center dark:bg-neutral-950/60">
                <h2 class="text-base font-semibold tracking-tight">No drivers configured</h2>
                <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">
                    Set <code class="rounded bg-white px-1.5 py-0.5 text-xs font-mono text-neutral-700 border border-neutral-200 dark:bg-neutral-900 dark:text-neutral-200 dark:border-neutral-800">PLENUM_DB_NODES</code>
                    or <code class="rounded bg-white px-1.5 py-0.5 text-xs font-mono text-neutral-700 border border-neutral-200 dark:bg-neutral-900 dark:text-neutral-200 dark:border-neutral-800">PLENUM_REDIS_NODES</code>
                    in your environment to enable routing.
                </p>
            </div>
        </section>
    @else
        <div class="space-y-4">
            @foreach ($drivers as $driver)
                <section class="rounded-3xl border border-neutral-200/80 bg-white p-2 shadow-[0_1px_2px_rgba(15,15,15,0.04)] dark:border-neutral-800/80 dark:bg-neutral-900 dark:shadow-none">
                    <header class="flex items-baseline justify-between gap-4 px-4 pt-2 pb-3">
                        <h2 class="text-base font-semibold tracking-tight">{{ $driver['name'] }}</h2>
                        <span class="text-xs text-neutral-400 font-mono dark:text-neutral-500">{{ class_basename($driver['class']) }}</span>
                    </header>

                    <div class="rounded-2xl bg-neutral-50 px-5 py-4 dark:bg-neutral-950/60">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] uppercase tracking-widest text-neutral-400 dark:text-neutral-500">
                                    <th class="pb-2.5 font-medium">Node</th>
                                    <th class="pb-2.5 text-right font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200/70 dark:divide-neutral-800/70">
                                @foreach ($driver['nodes'] as $node)
                                    <tr>
                                        <td class="py-2.5 font-mono text-[13px] text-neutral-700 dark:text-neutral-300">{{ $node['name'] }}</td>
                                        <td class="py-2.5 text-right">
                                            @if ($node['healthy'])
                                                <span data-status="up" class="inline-flex items-center gap-1.5 rounded-md bg-neutral-900 px-2 py-0.5 text-[11px] font-medium tracking-wider text-white dark:bg-white dark:text-neutral-900">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-white/90 dark:bg-neutral-900/80"></span>
                                                    UP
                                                </span>
                                            @else
                                                <span data-status="down" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-2 py-0.5 text-[11px] font-medium tracking-wider text-neutral-400 dark:border-neutral-700 dark:bg-transparent dark:text-neutral-500">
                                                    <span class="h-1.5 w-1.5 rounded-full border border-neutral-400 dark:border-neutral-600"></span>
                                                    DOWN
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($driver['distribution'] !== [])
                        <div class="mt-2 rounded-2xl bg-neutral-50 px-5 py-4 dark:bg-neutral-950/60">
                            <div class="flex items-baseline justify-between pb-3 text-[11px] uppercase tracking-widest text-neutral-400 dark:text-neutral-500">
                                <span>Distribution</span>
                                <span class="text-neutral-400 normal-case tracking-normal dark:text-neutral-500">{{ number_format($samples) }} synthetic keys</span>
                            </div>
                            <ul class="space-y-2.5">
                                @foreach ($driver['distribution'] as $row)
                                    <li class="flex items-center gap-3 text-xs">
                                        <span class="w-20 font-mono text-[12px] text-neutral-600 dark:text-neutral-400">{{ $row['node'] }}</span>
                                        <span class="flex-1 h-1.5 rounded-full bg-neutral-200/80 overflow-hidden dark:bg-neutral-800/80">
                                            <span class="block h-full rounded-full bg-neutral-900 dark:bg-white" style="width: {{ $row['share'] }}%"></span>
                                        </span>
                                        <span class="flex w-24 items-baseline justify-end gap-2 tabular-nums">
                                            <span class="text-neutral-700 dark:text-neutral-300">{{ number_format($row['count']) }}</span>
                                            <span class="text-neutral-400 dark:text-neutral-500">{{ $row['share'] }}%</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @endif

    <footer class="pt-8 text-xs text-neutral-400 dark:text-neutral-600">
        Read-only status view.
    </footer>

</div>

<script>
    (function () {
        var btn = document.getElementById('plenum-theme-toggle');
        if (! btn) return;
        btn.addEventListener('click', function () {
            var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            try { window.localStorage.setItem('plenum-theme', next); } catch (e) {}
        });
    })();
</script>
</body>
</html>
