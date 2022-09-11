<?php

use App\Providers\GrpcServiceProvider;
use Spiral\Goridge\StreamRelay;
use Spiral\RoadRunner\Worker;
use vandarpay\LaravelGrpc\Contracts\Kernel as KernelContract;
use vandarpay\LaravelGrpc\Contracts\ServiceInvoker;
use vandarpay\LaravelGrpc\Kernel;
use vandarpay\LaravelGrpc\LaravelServiceInvoker;

require __DIR__ . '/vendor/autoload.php';

/**
 * @var Illuminate\Foundation\Application $app
 */
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->singleton(KernelContract::class, Kernel::class);
$app->singleton(ServiceInvoker::class, LaravelServiceInvoker::class);

$kernel = $app->make(KernelContract::class);

$app->register(GrpcServiceProvider::class);

$w = new Worker(new StreamRelay(STDIN, STDOUT));
$kernel->serve($w);
