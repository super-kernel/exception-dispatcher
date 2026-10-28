<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher;

use SuperKernel\Contract\ExceptionDispatcherInterface;
use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use SuperKernel\ExceptionDispatcher\Contract\ExceptionHandlerInterface;
use SuperKernel\ExceptionDispatcher\Exception\InvalidExceptionHandlerDeclarationException;
use SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerProvider;
use SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerRegistration;
use SuperKernel\ExceptionDispatcher\Reporter\FallbackExceptionReporter;
use Throwable;

use function get_debug_type;
use function sprintf;

final readonly class ExceptionDispatcher implements ExceptionDispatcherInterface
{
    /**
     * @var list<array{
     *     registration: ExceptionHandlerRegistration<ExceptionHandler>,
     *     handler: ExceptionHandlerInterface,
     * }>
     */
    private array $handlers;

    public function __construct(
        ExceptionHandlerProvider          $handlerProvider,
        private FallbackExceptionReporter $fallbackReporter,
    )
    {
        $handlers = [];

        foreach ($handlerProvider->getHandlersForAttribute(ExceptionHandler::class) as $registration) {
            if (!$registration->handler instanceof ExceptionHandlerInterface) {
                throw new InvalidExceptionHandlerDeclarationException(sprintf(
                    'Exception handler %s marked with %s must implement %s; %s provided.',
                    $registration->handler::class,
                    ExceptionHandler::class,
                    ExceptionHandlerInterface::class,
                    get_debug_type($registration->handler),
                ));
            }

            $handlers[] = [
                'registration' => $registration,
                'handler'      => $registration->handler,
            ];
        }

        $this->handlers = $handlers;
    }

    public function dispatch(Throwable $throwable): void
    {
        $handled = false;

        foreach ($this->handlers as $registration) {
            if (!$registration['registration']->matches($throwable)) {
                continue;
            }

            try {
                $registration['handler']->handle($throwable);
                $handled = true;
            } catch (Throwable $handlerFailure) {
                $this->fallbackReporter->reportHandlerFailure(
                    $registration['handler']::class,
                    $throwable,
                    $handlerFailure,
                );
            }
        }

        if (!$handled) {
            $this->fallbackReporter->report($throwable);
        }
    }
}
