<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Tests\Fixture;

use Attribute;
use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use Throwable;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ProtocolExceptionHandler extends ExceptionHandler
{
    /**
     * @param class-string<Throwable> $exception
     */
    public function __construct(
        public string $protocol = 'http',
        string        $exception = Throwable::class,
        int           $priority = 0,
    )
    {
        parent::__construct($exception, $priority);
    }
}
