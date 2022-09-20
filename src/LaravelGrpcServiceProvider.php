<?php

namespace vandarpay\LaravelGrpc;

use Illuminate\Support\ServiceProvider;
use vandarpay\LaravelGrpc\Commands\GrpcClientMakeCommand;

class LaravelGrpcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->publishConfigs();
        }
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/grpc.php', 'grpc');
    }

    protected function registerCommands(): void
    {
        $this->commands([
            GrpcClientMakeCommand::class,
        ]);
    }

    protected function publishConfigs(): void
    {
        $this->publishes([
            __DIR__ . '/../config/grpc.php' => config_path('grpc.php'),
        ], 'grpc-config');
        $this->publishes([
            __DIR__ . '/worker.php' => base_path('worker.php'),
        ], 'grpc-worker');
        $this->publishes([
            __DIR__ . '/../Providers/GrpcServiceProvider.php' => app_path('Providers/GrpcServiceProvider.php'),
        ], 'grpc-provider');
        $this->publishes([
            __DIR__ . '/../RoadRunner/.rr.yaml' => base_path('.rr.yaml'),
        ], 'roadrunner-config');
    }
}
