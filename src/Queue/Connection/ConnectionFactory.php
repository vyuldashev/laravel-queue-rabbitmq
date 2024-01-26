<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connection;

use Exception;
use Illuminate\Support\Arr;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Connection\AMQPLazySocketConnection;
use PhpAmqpLib\Connection\AMQPLazySSLConnection;
use PhpAmqpLib\Connection\AMQPSocketConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPLogicException;

class ConnectionFactory
{
    protected const CONNECTION_TYPE_DEFAULT = 'default';

    protected const CONNECTION_TYPE_EXTENDED = AbstractConnection::class;

    protected const CONNECTION_SUB_TYPE_STREAM = AMQPStreamConnection::class;

    protected const CONNECTION_SUB_TYPE_SOCKET = AMQPSocketConnection::class;

    protected const CONNECTION_SUB_TYPE_SSL = AMQPSSLConnection::class;

    protected const CONFIG_CONNECTION = 'connection';

    /**
     * Create a Connection
     *
     * @throws Exception
     */
    public static function make(array $config = []): AbstractConnection
    {
        $connection = self::getConnectionFromConfig($config);
        $connectionConfig = ConfigFactory::make($config);

        /**
         * Todo [Major]:
         *  - Remove if statement and contents.
         *  - Remove unused method: self::_createLazyConnection()
         */
        if (in_array($connection, [AMQPLazyConnection::class, AMQPLazySocketConnection::class, AMQPLazySSLConnection::class])) {
            return self::_createLazyConnection($connection ?: AMQPLazyConnection::class, $config);
        }

        if (strtolower($connection) == self::CONNECTION_TYPE_DEFAULT) {
            return AMQPConnectionFactory::create($connectionConfig);
        }

        return self::create($connection, $connectionConfig);
    }

    /**
     * Get the validated connection from config
     */
    protected static function getConnectionFromConfig(array $config): string
    {
        $connection = (string) Arr::get($config, self::CONFIG_CONNECTION, self::CONNECTION_TYPE_DEFAULT);

        self::assertConnectionFromConfig($connection);

        return $connection;
    }

    /**
     * Creation of your own connection
     */
    protected static function create($connection, AMQPConnectionConfig $config): AbstractConnection
    {
        if ($config->getIoType() === AMQPConnectionConfig::IO_TYPE_SOCKET) {
            return self::createSocketConnection($connection, $config);
        }

        return self::createStreamConnection($connection, $config);
    }

    protected static function createSocketConnection($connection, AMQPConnectionConfig $config): AMQPSocketConnection
    {
        self::assertSocketConnection($connection, $config);

        return new $connection(
            $config->getHost(),
            $config->getPort(),
            $config->getUser(),
            $config->getPassword(),
            $config->getVhost(),
            $config->isInsist(),
            $config->getLoginMethod(),
            $config->getLoginResponse(),
            $config->getLocale(),
            $config->getReadTimeout(),
            $config->isKeepalive(),
            $config->getWriteTimeout(),
            $config->getHeartbeat(),
            $config->getChannelRPCTimeout(),
            $config
        );
    }

    protected static function createStreamConnection($connection, AMQPConnectionConfig $config): AMQPStreamConnection
    {
        self::assertStreamConnection($connection);

        if ($config->isSecure()) {
            self::assertSSLConnection($connection);

            return new $connection(
                $config->getHost(),
                $config->getPort(),
                $config->getUser(),
                $config->getPassword(),
                $config->getVhost(),
                self::getSslOptions($config),
                [
                    'insist' => $config->isInsist(),
                    'login_method' => $config->getLoginMethod(),
                    'login_response' => $config->getLoginResponse(),
                    'locale' => $config->getLocale(),
                    'connection_timeout' => $config->getConnectionTimeout(),
                    'read_write_timeout' => self::getReadWriteTimeout($config),
                    'keepalive' => $config->isKeepalive(),
                    'heartbeat' => $config->getHeartbeat(),
                ],
                $config
            );
        }

        return new $connection(
            $config->getHost(),
            $config->getPort(),
            $config->getUser(),
            $config->getPassword(),
            $config->getVhost(),
            $config->isInsist(),
            $config->getLoginMethod(),
            $config->getLoginResponse(),
            $config->getLocale(),
            $config->getConnectionTimeout(),
            self::getReadWriteTimeout($config),
            $config->getStreamContext(),
            $config->isKeepalive(),
            $config->getHeartbeat(),
            $config->getChannelRPCTimeout(),
            $config->getNetworkProtocol(),
            $config
        );
    }

    protected static function getReadWriteTimeout(AMQPConnectionConfig $config): float
    {
        return min($config->getReadTimeout(), $config->getWriteTimeout());
    }

    protected static function getSslOptions(AMQPConnectionConfig $config): array
    {
        return array_filter([
            'cafile' => $config->getSslCaCert(),
            'capath' => $config->getSslCaPath(),
            'local_cert' => $config->getSslCert(),
            'local_pk' => $config->getSslKey(),
            'verify_peer' => $config->getSslVerify(),
            'verify_peer_name' => $config->getSslVerifyName(),
            'passphrase' => $config->getSslPassPhrase(),
            'ciphers' => $config->getSslCiphers(),
            'security_level' => $config->getSslSecurityLevel(),
        ], static function ($value) {
            return $value !== null;
        });
    }

    protected static function assertConnectionFromConfig(string $connection): void
    {
        if ($connection !== self::CONNECTION_TYPE_DEFAULT && ! is_subclass_of($connection, self::CONNECTION_TYPE_EXTENDED)) {
            throw new AMQPLogicException(sprintf('The config property \'%s\' must contain \'%s\' or must extend: %s', self::CONFIG_CONNECTION, self::CONNECTION_TYPE_DEFAULT, class_basename(self::CONNECTION_TYPE_EXTENDED)));
        }
    }

    protected static function assertSocketConnection($connection, AMQPConnectionConfig $config): void
    {
        self::assertExtendedOf($connection, self::CONNECTION_SUB_TYPE_SOCKET);

        if ($config->isSecure()) {
            throw new AMQPLogicException('The socket connection implementation does not support secure connections.');
        }
    }

    protected static function assertStreamConnection($connection): void
    {
        self::assertExtendedOf($connection, self::CONNECTION_SUB_TYPE_STREAM);
    }

    protected static function assertSSLConnection($connection): void
    {
        self::assertExtendedOf($connection, self::CONNECTION_SUB_TYPE_SSL);
    }

    protected static function assertExtendedOf($connection, string $parent): void
    {
        if (! is_subclass_of($connection, $parent) && $connection !== $parent) {
            throw new AMQPLogicException(sprintf('The connection must extend: %s', class_basename($parent)));
        }
    }

    /**
     * @return mixed
     *
     * @throws Exception
     *
     * @deprecated This is the fallback method, update your config asap. (example: connection => 'default')
     */
    protected static function _createLazyConnection($connection, array $config): AbstractConnection
    {
        return $connection::create_connection(
            Arr::shuffle(Arr::get($config, ConfigFactory::CONFIG_HOSTS, [])),
            Arr::add(Arr::get($config, 'options', []), 'heartbeat', 0)
        );
    }
}
