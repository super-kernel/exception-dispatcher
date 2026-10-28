<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Reporter;

use SuperKernel\Contract\StdoutLoggerInterface;
use Throwable;

use function sprintf;

/** Records PHP's complete throwable string, including file, line, stack, and previous-exception chain. */
final readonly class FallbackExceptionReporter
{
    public function __construct(private StdoutLoggerInterface $logger)
    {
    }

    public function report(Throwable $throwable): void
    {
        $this->logger->error((string) $throwable);
    }

    /**
     * @param class-string $handler
     */
    public function reportHandlerFailure(string $handler, Throwable $original, Throwable $failure): void
    {
        $this->logger->error(sprintf(
            'Exception handler %s failed while processing %s: %s',
            $handler,
            $original::class,
            $failure,
        ));
    }
}
