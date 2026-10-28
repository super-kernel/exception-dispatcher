<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Registry;

use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use Throwable;

use function is_a;

/**
 * @template TDeclaration of ExceptionHandler
 */
final readonly class ExceptionHandlerRegistration
{
    /**
     * @param TDeclaration $declaration
     */
    public function __construct(public ExceptionHandler $declaration, public object $handler)
    {
    }

    public function matches(Throwable $throwable): bool
    {
        return is_a($throwable, $this->declaration->exception);
    }
}
