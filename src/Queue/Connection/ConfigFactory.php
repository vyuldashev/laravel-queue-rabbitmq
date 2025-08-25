<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connection;

use Illuminate\Support\Arr;
use PhpAmqpLib\Connection\AMQPConnectionConfig;

class ConfigFactory
{
    public const CONFIG_HOSTS = 'hosts';

    protected const CONFIG_OPTIONS = 'options';

    /**
     * Create a config object from config array
     */
    public static function make(array $config = []): AMQPConnectionConfig
    {
        return tap(new AMQPConnectionConfig, function (AMQPConnectionConfig $connectionConfig) use ($config) {
            // Set the connection to a Lazy by default
            $connectionConfig->setIsLazy(! in_array(
                Arr::get($config, 'lazy') ?? true,
                [false, 0, '0', 'false', 'no'],
                true)
            );

            // Set the connection to unsecure by default
            $connectionConfig->setIsSecure(in_array(
                Arr::get($config, 'secure'),
                [true, 1, '1', 'true', 'yes'],
                true)
            );

            if ($connectionConfig->isSecure()) {
                self::getSLLOptionsFromConfig($connectionConfig, $config);
            }

            self::getHostFromConfig($connectionConfig, $config);
            self::getHeartbeatFromConfig($connectionConfig, $config);
            self::getKeepAliveFromConfig($connectionConfig, $config);
            self::getChannelRpcTimeoutConfig($connectionConfig, $config);
            self::getNetworkProtocolFromConfig($connectionConfig, $config);
            self::getTimeoutsFromConfig($connectionConfig, $config);
        });
    }

    protected static function getHostFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
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

    protected static function getSLLOptionsFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
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
        if (Arr::has($sslConfig, 'verify_peer')) {
            $verifyPeer = Arr::get($sslConfig, 'verify_peer');
            $connectionConfig->setSslVerify($verifyPeer);
        }
        if ($passphrase = Arr::get($sslConfig, 'passphrase')) {
            $connectionConfig->setSslPassPhrase($passphrase);
        }
    }

    protected static function getHeartbeatFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
    {
        $heartbeat = Arr::get($config, self::CONFIG_OPTIONS.'.heartbeat');

        if (is_numeric($heartbeat) && intval($heartbeat) > 0) {
            $connectionConfig->setHeartbeat((int) $heartbeat);
        }
    }

    protected static function getKeepAliveFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
    {
        $keepalive = Arr::get($config, self::CONFIG_OPTIONS.'.keepalive');
        if (is_bool($keepalive)) {
            $connectionConfig->setKeepalive($keepalive);
        }
    }

    protected static function getChannelRpcTimeoutConfig(AMQPConnectionConfig $connectionConfig, array $config): void
    {
        $timeout = Arr::get($config, self::CONFIG_OPTIONS.'.channel_rpc_timeout');
        if (is_numeric($timeout)) {
            $connectionConfig->setChannelRPCTimeout((float)$timeout);
        }
    }

    protected static function getNetworkProtocolFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
    {
        if ($networkProtocol = Arr::get($config, 'network_protocol')) {
            $connectionConfig->setNetworkProtocol($networkProtocol);
        }
    }

    protected static function getTimeoutsFromConfig(AMQPConnectionConfig $connectionConfig, array $config): void
    {
        $connectionTimeout = Arr::get($config, self::CONFIG_OPTIONS.'.connection_timeout');
        if (is_numeric($connectionTimeout) && floatval($connectionTimeout) >= 0) {
            $connectionConfig->setConnectionTimeout((float) $connectionTimeout);
        }

        $readTimeout = Arr::get($config, self::CONFIG_OPTIONS.'.read_timeout');
        if (is_numeric($readTimeout) && floatval($readTimeout) >= 0) {
            $connectionConfig->setReadTimeout((float) $readTimeout);
        }

        $writeTimeout = Arr::get($config, self::CONFIG_OPTIONS.'.write_timeout');
        if (is_numeric($writeTimeout) && floatval($writeTimeout) >= 0) {
            $connectionConfig->setWriteTimeout((float) $writeTimeout);
        }

        $chanelRpcTimeout = Arr::get($config, self::CONFIG_OPTIONS.'.channel_rpc_timeout');
        if (is_numeric($chanelRpcTimeout) && floatval($chanelRpcTimeout) >= 0) {
            $connectionConfig->setChannelRPCTimeout((float) $chanelRpcTimeout);
        }
    }
}
