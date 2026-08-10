<?php

namespace Innonazarene\LaravelInertiaJsKit;

use Illuminate\Support\ServiceProvider;
use Innonazarene\LaravelInertiaJsKit\Console\Commands\InstallCommand;

class LaravelInertiaJsKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
