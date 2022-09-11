<?php

namespace vandarpay\LaravelGrpc\Contracts;


use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;

interface ServiceWrapper
{
    /**
     * Retrive service name.
     *
     * @return  string
     */
    public function getName(): string;

    /**
     * Retrieve public methods.
     *
     * @return  array
     */
    public function getMethods(): array;


    /**
     * Invoke service.
     *
     * @param string $method
     * @param ContextInterface $ctx
     * @param string|null $input
     * @return string
     * @throws InvokeException
     */
    public function invoke(string $method, ContextInterface $ctx, ?string $input): string;
}
