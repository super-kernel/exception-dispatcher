<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Provider;

use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\ExceptionDispatcherInterface;
use SuperKernel\Contract\StdoutLoggerInterface;
use SuperKernel\ExceptionDispatcher\Exception\InvalidExceptionHandlerDeclarationException;
use SuperKernel\ExceptionDispatcher\ExceptionDispatcher;
use SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerProvider;
use SuperKernel\ExceptionDispatcher\Reporter\FallbackExceptionReporter;

#[FactoryProvider(ExceptionDispatcherInterface::class)]
final readonly class ExceptionDispatcherProvider
{
    /**
     * @throws InvalidExceptionHandlerDeclarationException when a generic handler does not implement its contract
     */
    public function __invoke(
        ExceptionHandlerProvider $provider,
        StdoutLoggerInterface    $logger,
    ): ExceptionDispatcherInterface
    {
        return new ExceptionDispatcher(
            $provider,
            new FallbackExceptionReporter($logger),
        );
    }
}
