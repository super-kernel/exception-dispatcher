<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Tests\Unit;

use Attribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use Throwable;

final class ExceptionHandlerAttributeTest extends TestCase
{
    public function testDeclaresAClassOnlyNonRepeatableAttribute(): void
    {
        $reflection = new ReflectionClass(ExceptionHandler::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        self::assertCount(1, $attributes);
        self::assertSame(Attribute::TARGET_CLASS, $attributes[0]->newInstance()->flags);
        self::assertFalse($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }

    public function testDefaultsToEveryThrowableAtPriorityZero(): void
    {
        $declaration = new ExceptionHandler();

        self::assertSame(Throwable::class, $declaration->exception);
        self::assertSame(0, $declaration->priority);
    }
}
