<?php

namespace vandarpay\LaravelGrpc;

use Google\Protobuf\Any;
use Google\Protobuf\Internal\Message;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Bootstrap\SetRequestForConsole;
use JsonException;
use ReflectionException;
use Spiral\RoadRunner\GRPC\Context;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\GRPCException;
use Spiral\RoadRunner\GRPC\Exception\GRPCExceptionInterface;
use Spiral\RoadRunner\GRPC\Exception\NotFoundException;
use Spiral\RoadRunner\GRPC\Internal\Json;
use Spiral\RoadRunner\GRPC\ResponseHeaders;
use Spiral\RoadRunner\GRPC\StatusCode;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Worker;
use Throwable;
use vandarpay\LaravelGrpc\Contracts\Kernel as KernelContract;
use vandarpay\LaravelGrpc\Contracts\ServiceInvoker;
use function filter_var;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Manages group of services and communication with RoadRunner server.
 *
 * @psalm-type ServerOptions = array {
 *  debug?: bool
 * }
 *
 * @psalm-type ContextResponse = array {
 *  service: string,
 *  method:  string,
 *  context: array<string, array<string>>
 * }
 */
class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Service invoker.
     *
     * @var LaravelServiceInvoker
     */
    protected LaravelServiceInvoker $invoker;

    /**
     * Services definition.
     *
     * @var array
     */
    protected array $services = [];

    /**
     * The bootstrap classes for the application.
     *
     * @var array
     */
    protected array $bootstrappers = [
        LoadEnvironmentVariables::class,
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterFacades::class,
        SetRequestForConsole::class,
        RegisterProviders::class,
        BootProviders::class,
    ];
    /**
     * @var ServerOptions
     */
    private array $options;

    /**.
     * Create a new GRPC kernel instance.
     *
     * @param Application $app
     * @param ServiceInvoker $invoker
     * @param array $options
     */
    public function __construct(Application $app, ServiceInvoker $invoker, array $options = [])
    {
        $this->app = $app;
        $this->invoker = $invoker;
        $this->options = $options;
    }

    /**
     * Register available services.
     * @param string $interface
     * @return KernelContract
     * @throws ReflectionException|BindingResolutionException
     */
    public function registerService(string $interface): KernelContract
    {
        $service = new ReflectionServiceWrapper($this->invoker, $this->app->make($interface));
        $this->services[$service->getName()] = $service;

        return $this;
    }

    /**
     * @param Worker $worker
     * @param string $body
     * @param string $headers
     * @psalm-suppress InaccessibleMethod
     */
    private function workerSend(Worker $worker, string $body, string $headers): void
    {
        $worker->respond(new Payload($body, $headers));
    }

    /**
     * @param Worker $worker
     * @param string $message
     */
    private function workerError(Worker $worker, string $message): void
    {
        $worker->error($message);
    }

    /**
     * If server runs in debug mode
     *
     * @return bool
     */
    private function isDebugMode(): bool
    {
        $debug = false;

        if (isset($this->options['debug'])) {
            $debug = filter_var($this->options['debug'], FILTER_VALIDATE_BOOLEAN);
        }

        return $debug;
    }

    /**
     * Serve GRPC over given RoadRunner worker.
     *
     * @param Worker|null $worker
     * @param callable|null $finalize
     */
    public function serve(Worker $worker = null, callable $finalize = null): void
    {
        $this->bootstrap();
        $worker ??= Worker::create();
        while (true) {
            $request = $worker->waitPayload();
            if ($request === null) {
                return;
            }

            try {
                /** @var ContextResponse $context */
                $context = Json::decode($request->header);

                [$answerBody, $answerHeaders] = $this->tick($request->body, $context);
                $this->workerSend($worker, $answerBody, $answerHeaders);
            } catch (GRPCExceptionInterface $e) {
                $this->workerError($worker, $this->packError($e));
            } catch (Throwable $e) {
                $this->workerError($worker, $this->isDebugMode() ? (string)$e : $e->getMessage());
            } finally {
                if ($finalize !== null) {
                    isset($e) ? $finalize($e) : $finalize();
                }
            }
        }
    }

    /**
     * @param string $body
     * @param ContextResponse $data
     * @return array{ 0: string, 1: string }
     * @throws JsonException
     * @throws Throwable
     */
    private function tick(string $body, array $data): array
    {
        $context = (new Context($data['context']))
            ->withValue(ResponseHeaders::class, new ResponseHeaders());

        $response = $this->invoke($data['service'], $data['method'], $context, $body);

        /** @var ResponseHeaders|null $responseHeaders */
        $responseHeaders = $context->getValue(ResponseHeaders::class);
        $responseHeadersString = $responseHeaders ? $responseHeaders->packHeaders() : '{}';

        return [$response, $responseHeadersString];
    }

    /**
     * Bootstrap the application for HTTP requests.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }
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
     * Invoke service method with binary payload and return the response.
     *
     * @param string $service
     * @param string $method
     * @param ContextInterface $context
     * @param string $body
     * @return string
     * @throws GRPCException
     */
    protected function invoke(string $service, string $method, ContextInterface $context, string $body): string
    {
        if (!isset($this->services[$service])) {
            throw NotFoundException::create("Service `{$service}` not found.", StatusCode::NOT_FOUND);
        }

        return $this->services[$service]->invoke($method, $context, $body);
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }

    /**
     * Packs exception message and code into one string.
     *
     * Internal agreement:
     *
     * Details will be sent as serialized google.protobuf.Any messages after code and exception message separated with |:| delimeter.
     *
     * @param GRPCException $e
     * @return string
     */
    protected function packError(GRPCException $e): string
    {
        $data = [$e->getCode(), $e->getMessage()];

        foreach ($e->getDetails() as $detail) {
            /**
             * @var Message $detail
             */

            $anyMessage = new Any();

            $anyMessage->pack($detail);

            $data[] = $anyMessage->serializeToString();
        }

        return implode("|:|", $data);
    }
}
