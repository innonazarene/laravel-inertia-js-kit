<?php

namespace Innonazarene\LaravelInertiaJsKit\Tests;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Innonazarene\LaravelInertiaJsKit\LaravelInertiaJsKitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            LaravelInertiaJsKitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }
}
