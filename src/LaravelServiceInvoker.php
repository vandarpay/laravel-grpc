<?php

namespace vandarpay\LaravelGrpc;

use BadFunctionCallException;
use Google\Protobuf\Internal\Message;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\StatusCode;
use Throwable;
use vandarpay\LaravelGrpc\Contracts\ServiceInvoker;
use vandarpay\ServiceRepository\ServiceException;
use function get_class;
use function get_debug_type;
use function is_object;
use function is_subclass_of;
use function sprintf;

class LaravelServiceInvoker implements ServiceInvoker
{
    /**
     * @var string
     */
    private const ERROR_METHOD_RETURN =
        'Method %s must return an object that instance of %s, ' .
        'but the result provides type of %s';

    /**
     * @var string
     */
    private const ERROR_METHOD_IN_TYPE =
        'Method %s input type must be an instance of %s, ' .
        'but the input is type of %s';
    /**
     * The application implementation.
     *
     * @var Application
     */
    protected $app;

    /**
     * Create new Invoker instance
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the Laravel application instance.
     *
     * @return Application
     */
    public function getApplication(): Application
    {
        return $this->app;
    }

    /**
     * {@inheritDoc}
     */
    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
    {
        try {
            /** @var callable $callable */
            $callable = [$service, $method->getName()];

            /** @var Message $message */
            $message = $callable($ctx, $this->makeInput($method, $input));
        } catch (ServiceException $e) {
            $exceptionArray = [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'app_code' => $e->getAppCode()
            ];
            throw InvokeException::create(json_encode($exceptionArray), 99, $e);
        }
        // Note: This validation will only work if the
        // assertions option ("zend.assertions") is enabled.
        assert($this->assertResultType($method, $message));

        try {
            return $message->serializeToString();
        } catch (Throwable $e) {
            throw InvokeException::create($e->getMessage(), StatusCode::INTERNAL, $e);
        }
    }

    /**
     * Checks that the result from the GRPC service method returns the
     * Message object.
     *
     * @param Method $method
     * @param mixed $result
     * @return bool
     * @throws BadFunctionCallException
     */
    private function assertResultType(Method $method, $result): bool
    {
        if (!$result instanceof Message) {
            $type = is_object($result) ? get_class($result) : get_debug_type($result);

            throw new BadFunctionCallException(
                sprintf(self::ERROR_METHOD_RETURN, $method->getName(), Message::class, $type)
            );
        }

        return true;
    }

    /**
     * @param Method $method
     * @param string|null $body
     * @return Message
     * @throws InvokeException
     */
    private function makeInput(Method $method, ?string $body): Message
    {
        try {
            $class = $method->getInputType();

            // Note: This validation will only work if the
            // assertions option ("zend.assertions") is enabled.
            assert($this->assertInputType($method, $class));

            /** @psalm-suppress UnsafeInstantiation */
            $in = new $class();

            if ($body !== null) {
                $in->mergeFromString($body);
            }

            return $in;
        } catch (Throwable $e) {
            throw InvokeException::create($e->getMessage(), StatusCode::INTERNAL, $e);
        }
    }

    /**
     * Checks that the input of the GRPC service method contains the
     * Message object.
     *
     * @param Method $method
     * @param string $class
     * @return bool
     * @throws InvalidArgumentException
     */
    private function assertInputType(Method $method, string $class): bool
    {
        if (!is_subclass_of($class, Message::class)) {
            throw new InvalidArgumentException(
                sprintf(self::ERROR_METHOD_IN_TYPE, $method->getName(), Message::class, $class)
            );
        }

        return true;
    }
}
