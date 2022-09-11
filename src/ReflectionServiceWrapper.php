<?php

namespace vandarpay\LaravelGrpc;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Spiral\Goridge\RPC\Exception\ServiceException;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\NotFoundException;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\StatusCode;
use vandarpay\LaravelGrpc\Contracts\ServiceInvoker;
use vandarpay\LaravelGrpc\Contracts\ServiceWrapper;

class ReflectionServiceWrapper implements ServiceWrapper
{
    /**
     * Service name.
     *
     * @var string
     */
    protected string $name;

    /**
     * Service's methods
     *
     * @var array
     */
    protected array $methods = [];

    /**
     * Invoker.
     *
     * @var LaravelServiceInvoker|ServiceInvoker
     */
    protected LaravelServiceInvoker|ServiceInvoker $invoker;

    /**
     * Fully qualified service interface.
     *
     * @var ServiceInvoker|ServiceInterface
     */
    protected ServiceInvoker|ServiceInterface $interface;

    /**
     * Create new ServiceWrapper instance.
     * @param ServiceInvoker $invoker
     * @param ServiceInterface $interface
     * @throws ReflectionException
     */
    public function __construct(
        ServiceInvoker   $invoker,
        ServiceInterface $interface
    )
    {
        $this->invoker = $invoker;
        $this->interface = $interface;

        $this->configure($interface::class);
    }

    /**
     * Retrive service name.
     *
     * @return  string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retrieve public methods.
     *
     * @return  array
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @inheritdoc
     */
    public function invoke(string $method, ContextInterface $ctx, ?string $input): string
    {
        if (!isset($this->methods[$method])) {
            throw new NotFoundException("Method `{$method}` not found in service `{$this->name}`.");
        }
        return $this->invoker->invoke($this->interface, $this->methods[$method], $ctx, $input);
    }

    /**
     * Configure service name and methods.
     *
     * @param string $interface
     *
     * @throws ServiceException|ReflectionException
     */
    protected function configure(string $interface)
    {
        try {
            $r = new ReflectionClass($interface);
            if (!$r->hasConstant('NAME')) {
                throw new ServiceException(
                    "Invalid service interface `{$interface}`, constant `NAME` not found."
                );
            }
            $this->name = $r->getConstant('NAME');
        } catch (ReflectionException $e) {
            throw new ServiceException(
                "Invalid service interface `{$interface}`.",
                StatusCode::INTERNAL,
                $e
            );
        }

        // list of all available methods and their object types
        $this->methods = $this->fetchMethods($interface);
    }

    /**
     * @param string $interface
     * @return array
     * @throws ReflectionException
     */
    protected function fetchMethods(string $interface): array
    {
        $reflection = new ReflectionClass($interface);

        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (Method::match($method)) {
                $methods[$method->getName()] = Method::parse($method);
            }
        }

        return $methods;
    }
}
