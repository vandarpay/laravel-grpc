<?php

namespace App\Providers;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use ReflectionException;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use vandarpay\LaravelGrpc\Contracts\Kernel;

class GrpcServiceProvider extends ServiceProvider
{
    /**
     * @var \vandarpay\LaravelGrpc\Kernel
     */
    private mixed $server;

    /**
     * Register any application services.
     *
     * @return void
     * @throws BindingResolutionException
     * @throws ReflectionException
     */
    public function register()
    {
        $this->server = $this->app->make(Kernel::class);
        //$this->bindGrpc(NotificationRepository::class, NotificationService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register a binding with the container.
     *
     * @param string $abstract
     * @param Closure|string|null $concrete
     * @param bool $shared
     * @return void
     * @throws ReflectionException
     */
    public function bindGrpc(string $abstract, Closure|string $concrete = null, bool $shared = false)
    {
        $this->app->bind($abstract, $concrete, $shared);
        if (!is_string($concrete) || !class_exists($concrete)) {
            return;
        }
        if ((new $concrete()) instanceof ServiceInterface) {
            $this->server->registerService($abstract);
        }
    }
}
