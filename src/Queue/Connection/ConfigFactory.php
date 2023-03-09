<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connection;

use Illuminate\Support\Arr;
use PhpAmqpLib\Connection\AMQPConnectionConfig;

class ConfigFactory
{
    protected const CONFIG_OPTIONS = 'options';

    public const CONFIG_HOSTS = 'hosts';

    /**
     * Create a config object from config array
     */
    public static function make(array $config = []): AMQPConnectionConfig
    {
        return tap(new AMQPConnectionConfig(), function (AMQPConnectionConfig $connectionConfig) use ($config) {
            // Set the connection to a Lazy by default
            $connectionConfig->setIsLazy(! in_array(
                Arr::get($config, 'lazy') ?? true,
                [false, 0, '0', 'false'],
                true)
            );

            // Set the connection to unsecure by default
            $connectionConfig->setIsSecure(in_array(
                Arr::get($config, 'secure'),
                [true, 1, '1', 'true'],
                true)
            );

            if ($connectionConfig->isSecure()) {
                self::sllOptionsFromConfig($connectionConfig, $config);
            }

            self::hostFromConfig($connectionConfig, $config);
            self::heartbeatFromConfig($connectionConfig, $config);
        });
    }

    protected static function hostFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
    {
        $hostConfig = Arr::first(Arr::shuffle(Arr::get($config, self::CONFIG_HOSTS, [])), null, []);

        if ($location = Arr::get($hostConfig, 'host')) {
            $connectionConfig->setHost($location);
        }
        if ($port = Arr::get($hostConfig, 'port')) {
            $connectionConfig->setPort($port);
        }
        if ($vhost = Arr::get($hostConfig, 'vhost')) {
            $connectionConfig->setVhost($vhost);
        }
        if ($user = Arr::get($hostConfig, 'user')) {
            $connectionConfig->setUser($user);
        }
        if ($password = Arr::get($hostConfig, 'password')) {
            $connectionConfig->setPassword($password);
        }
    }

    protected static function sllOptionsFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
    {
        $sslConfig = Arr::get($config, self::CONFIG_OPTIONS.'.ssl_options', []);

        if ($caFile = Arr::get($sslConfig, 'cafile')) {
            $connectionConfig->setSslCaCert($caFile);
        }
        if ($cert = Arr::get($sslConfig, 'local_cert')) {
            $connectionConfig->setSslCert($cert);
        }
        if ($key = Arr::get($sslConfig, 'local_key')) {
            $connectionConfig->setSslKey($key);
        }
        if ($verifyPeer = Arr::get($sslConfig, 'verify_peer')) {
            $connectionConfig->setSslVerify($verifyPeer);
        }
        if ($passphrase = Arr::get($sslConfig, 'passphrase')) {
            $connectionConfig->setSslPassPhrase($passphrase);
        }
    }

    protected static function heartbeatFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
    {
        $heartbeat = Arr::get($config, self::CONFIG_OPTIONS.'.heartbeat');

        if (! empty($heartbeat) && is_numeric($heartbeat) && 0 < (int) $heartbeat) {
            $connectionConfig->setHeartbeat($heartbeat);
        }
    }
}
