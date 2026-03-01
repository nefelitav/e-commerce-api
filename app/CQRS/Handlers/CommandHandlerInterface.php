<?php

namespace App\CQRS\Handlers;

use App\CQRS\Commands\CommandInterface;

/**
 * @template TCommand of CommandInterface
 * @template TResult
 */
interface CommandHandlerInterface
{
    /**
     * @param TCommand $command
     * @return TResult
     */
    public function handle(CommandInterface $command): mixed;
}

