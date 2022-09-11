<?php

namespace vandarpay\LaravelGrpc\Contracts;

use Spiral\RoadRunner\Worker;
use Illuminate\Contracts\Foundation\Application;

interface Kernel
{
    /**
     * Bootstrap the application for GRPC requests.
     *
     * @return void
     */
    public function bootstrap(): void;

    /**
     * Register available services.
     *
     * @param   string              $interface
     *
     * @return  self
     */
    public function registerService(string $interface): Kernel;

    /**
     * Serve GRPC server.
     *
     * @param Worker $worker
     * @param callable|null $finalize
     * @return void
     */
    public function serve(Worker $worker, callable $finalize = null): void;

    /**
     * Get the Laravel application instance.
     *
     * @return Application
     */
    public function getApplication(): Application;
}
