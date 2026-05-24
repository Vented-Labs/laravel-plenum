#!/usr/bin/env bash
set -euo pipefail

# Install composer deps if vendor/ is missing (first-time bind mount).
if [ ! -d vendor ]; then
    composer install --no-interaction --no-progress --prefer-dist
fi

# Optional: pre-mark a couple of nodes down so the dashboard has a visible
# mix of UP/DOWN status the moment Mason opens it. The PLENUM_VISUALIZE_SEED
# env var, set in compose.yml, controls this.
if [ "${PLENUM_VISUALIZE_SEED:-0}" = "1" ]; then
    php -r "
require '/app/vendor/autoload.php';
\$app = require '/app/vendor/orchestra/testbench-core/laravel/bootstrap/app.php';
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
\$cache = \$app->make(Illuminate\\Contracts\\Cache\\Factory::class)->store(config('plenum.health.cache_store'));
\$prefix = config('plenum.health.cache_prefix', 'plenum:health:');
\$ttl = 86400;
\$cache->put(\$prefix.'database:db_2', 'down', \$ttl);
echo \"Seeded: db_2 marked down.\\n\";
"
fi

exec vendor/bin/testbench serve --host 0.0.0.0 --port 8000 --no-reload
