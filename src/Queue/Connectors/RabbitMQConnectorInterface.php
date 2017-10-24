<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use Illuminate\Queue\Connectors\ConnectorInterface;

interface RabbitMQConnectorInterface extends ConnectorInterface
{
    public function reconnect();
}
