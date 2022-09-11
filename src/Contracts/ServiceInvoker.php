<?php

namespace vandarpay\LaravelGrpc\Contracts;


use Illuminate\Contracts\Foundation\Application;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;

/**
 * Responsible for data marshalling/unmarshalling and method invocation.
 */
interface ServiceInvoker
{
    /**
     * @param ServiceInterface $service
     * @param Method $method
     * @param ContextInterface $ctx
     * @param string|null $input
     * @return string
     * @throws InvokeException
     */
    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string;

    /**
     * Get the Laravel application instance.
     *
     * @return Application
     */
    public function getApplication(): Application;
}
