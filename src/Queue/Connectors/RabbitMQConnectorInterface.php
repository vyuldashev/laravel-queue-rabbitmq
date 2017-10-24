<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors;

use Illuminate\Queue\Connectors\ConnectorInterface;

public interface RabbitMQConnectorInterface extends ConnectorInterface
{
    public function reconnect();
}
