<?php

namespace App\CQRS;

use App\CQRS\Commands\CommandInterface;
use App\CQRS\Handlers\CommandHandlerInterface;
use Illuminate\Contracts\Container\Container;
use LogicException;

final readonly class CommandBus
{
    /**
     * @param array<class-string<CommandInterface>, class-string> $handlers
     */
    public function __construct(
        private Container $container,
        private array     $handlers,
    ) {}

    public function dispatch(CommandInterface $command): mixed
    {
        $commandClass = $command::class;

        if (!isset($this->handlers[$commandClass])) {
            throw new LogicException("No handler registered for command [{$commandClass}].");
        }

        /** @var CommandHandlerInterface<CommandInterface, mixed> $handler */
        $handler = $this->container->make($this->handlers[$commandClass]);

        return $handler->handle($command);
    }
}

