<?php

namespace BeyondCode\LaravelMaskedDumper;

use BeyondCode\LaravelMaskedDumper\Console\DumpDatabaseCommand;
use Illuminate\Support\ServiceProvider;

class LaravelMaskedDumpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/masked-dump.php' => config_path('masked-dump.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->commands([
            DumpDatabaseCommand::class,
        ]);

        $this->mergeConfigFrom(__DIR__.'/../config/masked-dump.php', 'masked-dump');
    }
}
