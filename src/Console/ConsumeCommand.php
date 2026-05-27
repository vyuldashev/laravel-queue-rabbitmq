<?php

namespace VladimirYuldashev\LaravelQueueRabbitMQ\Console;

use Illuminate\Console\Parser;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Worker;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use VladimirYuldashev\LaravelQueueRabbitMQ\Consumer;

class ConsumeCommand extends WorkCommand
{
    protected const NAME = 'rabbitmq:consume';

    /**
     * The RabbitMQ-specific options added on top of the inherited WorkCommand signature.
     * The leading name is a parser-required throwaway; only the options are used.
     */
    protected const RABBITMQ_SIGNATURE = self::NAME.'
                            {--max-priority=}
                            {--consumer-tag}
                            {--prefetch-size=0}
                            {--prefetch-count=1000}';

    protected $description = 'Consume messages';

    public function __construct(Worker $worker, Cache $cache)
    {
        // Inherit WorkCommand's signature so we pick up every option Laravel adds upstream.
        parent::__construct($worker, $cache);

        $this->setName(self::NAME);

        // Add our options, skipping any name the parent already defines to avoid a clash.
        foreach ($this->rabbitmqOptions() as $option) {
            if (! $this->getDefinition()->hasOption($option->getName())) {
                $this->getDefinition()->addOption($option);
            }
        }
    }

    /**
     * Parse the RabbitMQ signature DSL into InputOption objects.
     *
     * @return array<int, InputOption>
     */
    protected function rabbitmqOptions(): array
    {
        [, , $options] = Parser::parse(self::RABBITMQ_SIGNATURE);

        return $options;
    }

    public function handle(): void
    {
        /** @var Consumer $consumer */
        $consumer = $this->worker;

        $consumer->setContainer($this->laravel);
        $consumer->setName($this->option('name'));
        $consumer->setConsumerTag($this->consumerTag());
        $consumer->setMaxPriority((int) $this->option('max-priority'));
        $consumer->setPrefetchSize((int) $this->option('prefetch-size'));
        $consumer->setPrefetchCount((int) $this->option('prefetch-count'));

        parent::handle();
    }

    protected function consumerTag(): string
    {
        if ($consumerTag = $this->option('consumer-tag')) {
            return $consumerTag;
        }

        $consumerTag = implode('_', [
            Str::slug(config('app.name', 'laravel')),
            Str::slug($this->option('name')),
            md5(serialize($this->options()).Str::random(16).getmypid()),
        ]);

        return Str::substr($consumerTag, 0, 255);
    }
}
