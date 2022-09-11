# Laravel Grpc

This package is prepared for implementing `GRPC` on the server side and client side in Laravel to implement the
microservice structure.

Please follow the steps below to set up the desired section, Also, at the end of the page, examples are given that will
give you a better understanding

## Installation

    composer require vandarpay/laravel-grpc

### Publish Necessary File

    php artisan vendor:publish --provider="vandarpay\LaravelGrpc\GrpcServiceProvider"

## Requirement

- PHP 8.1
- [Install](https://github.com/protocolbuffers/protobuf/tree/master/php) `protobuf-ext` .
- Install `grpc-ext` .

_________________

# Server Side

This package use RoadRunner for manage process and implement grpc server, RoadRunner is a high-performance PHP
application server, load-balancer, and process manager written in Golang.

## Installation RoadRunner Binary

You can also install RoadRunner automatically using command shipped with the composer package, run:

```bash
./vendor/bin/rr-worker get-binary
```

Server binary will be available at the root of your project.

> PHP's extensions php-curl and php-zip are required to download RoadRunner automatically. PHP's extensions php-sockets need to be installed to run roadrunner. Check with php --modules your installed extensions.

## Run RoadRunner Sever

```bash
./rr serve --dotenv .env
```

## Environments

Please add this environment variable to `.env` file

    #Available value : panic, error, warn, info, debug. Default: debug
    ROAD_RUNNER_LOG_LEVEL=debug
    GRPC_XDEBUG=0 # 1,0
    GRPC_SERVER=tcp://127.0.0.1:6001
    GRPC_WORKER_MAX_JOBS=0
    GRPC_WORKER_NUM_WORKERS=2

- #### GRPC_XDEBUG :

To activate xDebug make sure to set the `xdebug.mode=debug` in your `php.ini`.

To enable xDebug in your application make sure to set `ENV` variable `GRPC_XDEBUG`

- #### GRPC_SERVER :

Consider the address you want for the GRPC server

- #### GRPC_WORKER_MAX_JOBS :

Maximal count of worker executions. Zero (or nothing) means no limit.(Default: 0)

- #### GRPC_WORKER_NUM_WORKERS :

How many worker processes will be started. Zero (or nothing) means the number of logical CPUs.(Default : 0)

## Make New Service

    php artisan make:service {service-name} --grpc --language=fa

## Compile proto files

To start, create a proto file named `echo.proto` in path `app/Protobuf` and copy the following content into it, if this
folder does not exist, create it

```
syntax = "proto3";

package services;

option php_namespace = "GrpcServices\\Echo\\Messages";
option php_metadata_namespace = "GrpcServices\\Echo";

service Echo {
    rpc Ping (PingMessage) returns (PingMessage) {
    }
}

message PingMessage {
    string msg = 1;
}
```

Note that the following 3 lines of this file should not be changed

```
package services;

option php_namespace = "GrpcServices\\Echo\\Messages";
option php_metadata_namespace = "GrpcServices\\Echo";
```

Next, after installing program `protoc`, execute the following command to create the required files for connection

```bash
protoc --proto_path=app/Protobuf --php_out=./app/Protobuf echo.proto
```

After executing command `protoc`, a folder named `GrpcServices` is created in path `app/Protobuf`, which contains
messages and the main service class

## Create First Method

Add this line to created repository

```php
use Spiral\RoadRunner\GRPC\ContextInterface;
use GrpcServices\Echo\Messages\PingMessage;

public function Ping(ContextInterface $ctx, PingMessage $in): PingMessage;
```

And then in the related service, add the additional method in the repository and put its output according to the
definitions of the `proto` file.

## Register Proto File In RoadRunner

The path of file proto created in the previous step should be added in file `.rr.yaml` in the following way

```
grpc:
  listen: ${GRPC_SERVER}
  proto:
    - "app/Protobuf/notification.proto"
    - "proto file path"
```

## Register Service To GRPC Server

To register the service created in method `register`, class `App\Providers\GrpcServiceProvider.php`, register your
service as below

```php
$this->bindGrpc(PingRepository::class, PingService::class);
```

# Client Side

To build the communication class on the client side, first, the proto file must be compiled like the server routine, and
the communication class must be created by the following command.

```
php artisan make:grpc-client {service name}
```

The proto file should be in `app/Protobuf` path. And after executing the above command, folder `Clients` will be created
in path `app/Protobuf`.

After this, the communication settings of the defined service should be placed in the config file `grpc.php`

```
        'notification' => [
            'host' => env('NOTIFICATION_SERVER_HOST'),
            'authentication' => env('NOTIFICATION_SERVER_AUTHENTICATION','insecure'), // insecure, tls
            'cert' => env('NOTIFICATION_SERVER_CERT','')
        ],
```

**Note**: The index of this setting must be equal to the service name

Below is an example to better understand the JRPC client class
```php
<?php

namespace App\Protobuf\Clients;

use GrpcServices\Echo\Messages\PingMessage;
use GrpcServices\Notification\Messages\TextMessage;
use vandarpay\LaravelGrpc\GrpcClient;

class NotificationClient extends GrpcClient
{
    protected string $service = 'services.Notification';

    public function send(string $message): TextMessage
    {
        $request = new TextMessage();
        $request->setMsg($message . ' In Client ' . rand(0, 9999));

        return $this->client->simpleRequest('send', $request);
    }
}
```

## Versioning On Service

Of course, in the development of a service, there are times when there is a need to upgrade the old version, and this
part is fully supported in this package. For this purpose, after considering the necessary folders for the service.

In this case, the folder structure changes as follows

```
├── Services
    ├── Test 
    |   ├── v1
    |   |   ├── TestException.php
    |   |   ├── TestRepository.php
    |   |   ├── TestTransformer.php
    |   |   └── TestService.php
    |   └── v2
    |       ├── TestException.php
    |       ├── TestRepository.php
    |       ├── TestTransformer.php
    |       └── TestService.php
    └── AlphaService
```
