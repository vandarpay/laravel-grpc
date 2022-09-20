<?php

namespace vandarpay\LaravelGrpc\Commands;


use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:grpc-client')]
class GrpcClientMakeCommand extends GeneratorCommand
{
    protected $name = 'make:grpc-client';

    protected $description = 'Create a new grpc client class';

    protected $type = 'Grpc Client';

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);
        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name). 'Client.php';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/grpc-client.stub';
    }


    protected function getDefaultNamespace($rootNamespace)
    {
        return "{$rootNamespace}\\Protobuf\Clients" ;
    }

}
