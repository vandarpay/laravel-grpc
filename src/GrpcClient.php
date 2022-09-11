<?php

namespace vandarpay\LaravelGrpc;

use Exception;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Config;

abstract class GrpcClient
{
    protected string $service = '';
    protected BaseStubWrapper $client;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $config = Config::get("grpc.".strtolower($this->service));
        if(is_null($config)){
            throw new Exception('Configuration Failed ('.$this->service.')');
        }
        $authenticationMethod = 'create'.ucfirst($config['authentication']).'Credentials';
        $this->client = new BaseStubWrapper($config['host'], [
            'credentials' => $this->{$authenticationMethod}($config['cert']??''),
        ]);
        $this->client->setServiceName($this->service);
    }

    /**
     * Create tls credential
     * @param string $certPath
     * @return ChannelCredentials
     */
    private function createTlsCredentials(string $certPath): ChannelCredentials
    {
        return ChannelCredentials::createSsl(file_get_contents(base_path($certPath)));
    }

    /**
     * Create insecure credential
     * @param string $certPath
     * @return null
     */
    private function createInsecureCredentials(string $certPath)
    {
        return ChannelCredentials::createInsecure();
    }
}
