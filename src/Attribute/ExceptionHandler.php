<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Attribute;

use Attribute;
use Throwable;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class ExceptionHandler
{
    /**
     * @param class-string<Throwable> $exception
     */
    public function __construct(public string $exception = Throwable::class, public int $priority = 0)
    {
    }
}
