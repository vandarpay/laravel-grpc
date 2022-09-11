<?php

namespace vandarpay\LaravelGrpc;

use Exception;
use Generator;
use Grpc\BaseStub;
use vandarpay\LaravelGrpc\Exceptions\GrpcServiceException;

class BaseStubWrapper extends BaseStub
{

    private string $serviceName;

    /**
     * @return string
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * @param string $serviceName
     * @return $this
     */
    public function setServiceName(string $serviceName): static
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    /**
     * @param string $methodName
     * @param $request
     * @return mixed
     * @throws GrpcServiceException
     * @throws Exception
     */
    public function simpleRequest(string $methodName, $request)
    {
        $grpcRequest = $this->_simpleRequest(
            '/' . $this->getServiceName() . '/' . $methodName,
            $request,
            [$request::class, 'decode'],
            [],
            []
        );
        [$response, $status] = $grpcRequest->wait();
        if ($status->code == 99) {
            throw new GrpcServiceException(json_decode($status->details, true));
        }
        if ($status->code != 0) {
            throw new Exception($status->details);
        }
        return $response;
    }

    /**
     * @param string $methodName
     * @param $request
     * @return Generator|mixed
     */
    public function streamRequest(string $methodName, $request): mixed
    {
        $grpcRequest = $this->_serverStreamRequest(
            '/' . $this->getServiceName() . '/' . $methodName,
            $request,
            [$request::class, 'decode'],
            [],
            []
        );
        return $grpcRequest->responses();
    }

}
