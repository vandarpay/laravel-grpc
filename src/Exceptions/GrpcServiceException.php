<?php

declare(strict_types=1);

namespace vandarpay\LaravelGrpc\Exceptions;


class GrpcServiceException extends \Exception
{
    protected string $exception_code = '';

    public function __construct(array $exceptionArray)
    {
        $this->message = $exceptionArray['message'];
        $this->code = $exceptionArray['code'];
        $this->exception_code = $exceptionArray['app_code'];
    }
    /**
     * @return string
     */
    public function getAppCode() : string
    {
        return $this->exception_code;
    }
}
