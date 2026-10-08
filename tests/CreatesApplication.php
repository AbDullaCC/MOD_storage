<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // RefreshDatabase rebuilds the schema. Never let it target app data,
        // including through cached configuration or a DATABASE_URL override.
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.mysql');
        if ($connection !== 'mysql' || $database['database'] !== 'mod_storage_testing' || ! empty($database['url'])) {
            throw new \RuntimeException('Tests require the dedicated mod_storage_testing MySQL database without a DATABASE_URL override.');
        }

        return $app;
    }
}
