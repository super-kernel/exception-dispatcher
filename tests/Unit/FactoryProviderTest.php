<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SuperKernel\Annotator\AnnotationCollector;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\ExceptionDispatcherInterface;
use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use SuperKernel\ExceptionDispatcher\ExceptionDispatcher;
use SuperKernel\ExceptionDispatcher\Provider\ExceptionDispatcherProvider;
use SuperKernel\ExceptionDispatcher\Provider\ExceptionHandlerProviderProvider;
use SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerProvider;
use SuperKernel\ExceptionDispatcher\Tests\Fixture\ArrayContainer;
use SuperKernel\ExceptionDispatcher\Tests\Fixture\RecordingStdoutLogger;
use Throwable;

use function iterator_to_array;

final class FactoryProviderTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function mappings(): iterable
    {
        yield 'handler provider' => [
            ExceptionHandlerProviderProvider::class,
            ExceptionHandlerProvider::class,
        ];
        yield 'dispatcher' => [
            ExceptionDispatcherProvider::class,
            ExceptionDispatcherInterface::class,
        ];
    }

    /**
     * @param class-string $provider
     * @param class-string $service
     *
     * @throws Throwable When fixture setup or the operation under test throws
     */
    #[DataProvider('mappings')]
    public function testDeclaresFactoryProviderMappings(string $provider, string $service): void
    {
        $attributes = new ReflectionClass($provider)->getAttributes(FactoryProvider::class);

        self::assertCount(1, $attributes);
        self::assertSame($service, $attributes[0]->newInstance()->className);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCreatesTheGlobalHandlerProvider(): void
    {
        $collector = new AnnotationCollector([]);
        $container = new ArrayContainer([
            AnnotationCollectorInterface::class => $collector,
        ]);
        $provider = new ExceptionHandlerProviderProvider($container)();

        self::assertSame([], iterator_to_array($provider->getHandlersForAttribute(ExceptionHandler::class), false));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testCreatesTheBaseExceptionDispatcher(): void
    {
        $handlerProvider = new ExceptionHandlerProvider(new AnnotationCollector([]), new ArrayContainer([]));
        $dispatcher = new ExceptionDispatcherProvider()(
            $handlerProvider,
            new RecordingStdoutLogger(),
        );

        self::assertInstanceOf(ExceptionDispatcher::class, $dispatcher);
    }
}
