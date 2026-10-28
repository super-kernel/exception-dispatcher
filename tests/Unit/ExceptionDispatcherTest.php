<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Tests\Unit;

use ArrayObject;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;
use SuperKernel\Annotator\AnnotationCollector;
use SuperKernel\Annotator\Model\AttributeMetadata;
use SuperKernel\Annotator\Model\PackageAnnotations;
use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use SuperKernel\ExceptionDispatcher\Contract\ExceptionHandlerInterface;
use SuperKernel\ExceptionDispatcher\Exception\InvalidExceptionHandlerDeclarationException;
use SuperKernel\ExceptionDispatcher\ExceptionDispatcher;
use SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerProvider;
use SuperKernel\ExceptionDispatcher\Reporter\FallbackExceptionReporter;
use SuperKernel\ExceptionDispatcher\Tests\Fixture\ArrayContainer;
use SuperKernel\ExceptionDispatcher\Tests\Fixture\ProtocolExceptionHandler;
use SuperKernel\ExceptionDispatcher\Tests\Fixture\RecordingStdoutLogger;
use Throwable;

use function array_map;

final class ExceptionDispatcherTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRunsEveryMatchingBaseHandlerInPriorityOrder(): void
    {
        $calls = new ArrayObject();
        $general = new readonly class($calls) implements ExceptionHandlerInterface {
            public function __construct(private ArrayObject $calls)
            {
            }

            public function handle(Throwable $throwable): void
            {
                $this->calls->append('general');
            }
        };
        $specific = new readonly class($calls) implements ExceptionHandlerInterface {
            public function __construct(private ArrayObject $calls)
            {
            }

            public function handle(Throwable $throwable): void
            {
                $this->calls->append('specific');
            }
        };
        $unmatched = new readonly class($calls) implements ExceptionHandlerInterface {
            public function __construct(private ArrayObject $calls)
            {
            }

            public function handle(Throwable $throwable): void
            {
                $this->calls->append('unmatched');
            }
        };
        $logger = new RecordingStdoutLogger();
        $dispatcher = self::dispatcher([
            $general::class => new AttributeMetadata(new ExceptionHandler(
                priority : 10,
            )),
            $specific::class => new AttributeMetadata(new ExceptionHandler(
                exception : RuntimeException::class,
                priority  : 100,
            )),
            $unmatched::class => new AttributeMetadata(new ExceptionHandler(
                exception : LogicException::class,
                priority  : 200,
            )),
        ], [
            $general::class   => $general,
            $specific::class  => $specific,
            $unmatched::class => $unmatched,
        ], $logger);

        $dispatcher->dispatch(new RuntimeException('Failure.'));

        self::assertSame([
            'specific',
            'general',
        ], (array) $calls);
        self::assertSame([], $logger->records);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testIgnoresHandlersMarkedWithADerivedAttribute(): void
    {
        $logger = new RecordingStdoutLogger();
        $dispatcher = self::dispatcher([
            'ProtocolHandler' => new AttributeMetadata(new ProtocolExceptionHandler(
                protocol : 'http',
            ), [
                    ExceptionHandler::class,
                ]),
        ], [
            'ProtocolHandler' => new stdClass(),
        ], $logger);
        $throwable = new RuntimeException('HTTP failure.');

        $dispatcher->dispatch($throwable);

        self::assertSame([
            [
                LogLevel::ERROR,
                (string) $throwable,
                [],
            ],
        ], $logger->records);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testAlwaysReportsTheCompleteThrowableWhenNoHandlerMatches(): void
    {
        $logger = new RecordingStdoutLogger();
        $previous = new LogicException('Previous failure.');
        $throwable = new RuntimeException('Unhandled failure.', previous: $previous);
        $handler = $this->createMock(ExceptionHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $dispatcher = self::dispatcher([
            $handler::class => new AttributeMetadata(new ExceptionHandler(exception: LogicException::class)),
        ], [
            $handler::class => $handler,
        ], $logger);

        $dispatcher->dispatch($throwable);

        self::assertSame([
            [
                LogLevel::ERROR,
                (string) $throwable,
                [],
            ],
        ], $logger->records);
        self::assertStringContainsString('#0 ', (string) $logger->records[0][1]);
        self::assertStringContainsString((string) $previous, (string) $logger->records[0][1]);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReportsHandlerFailureAndContinuesDispatching(): void
    {
        $calls = new ArrayObject();
        $failure = new RuntimeException('Handler failure.');
        $failing = new readonly class($failure) implements ExceptionHandlerInterface {
            public function __construct(private Throwable $failure)
            {
            }

            public function handle(Throwable $throwable): void
            {
                throw $this->failure;
            }
        };
        $successful = self::recordingHandler($calls);
        $logger = new RecordingStdoutLogger();
        $dispatcher = self::dispatcher([
            $failing::class => new AttributeMetadata(new ExceptionHandler(
                priority : 100,
            )),
            $successful::class => new AttributeMetadata(new ExceptionHandler()),
        ], [
            $failing::class    => $failing,
            $successful::class => $successful,
        ], $logger);

        $dispatcher->dispatch(new RuntimeException('Original.'));

        self::assertSame([
            'successful',
        ], (array) $calls);
        self::assertCount(1, $logger->records);
        self::assertSame(
            'Exception handler ' . $failing::class . ' failed while processing ' . RuntimeException::class . ': '
            . $failure,
            $logger->records[0][1],
        );
        self::assertStringContainsString('#0 ', (string) $logger->records[0][1]);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsABaseHandlerThatDoesNotImplementTheLocalContract(): void
    {
        $logger = new RecordingStdoutLogger();

        $this->expectException(InvalidExceptionHandlerDeclarationException::class);
        $this->expectExceptionMessageIsOrContains(ExceptionHandlerInterface::class);

        self::dispatcher([
            'Handler' => new AttributeMetadata(new ExceptionHandler()),
        ], [
            'Handler' => new stdClass(),
        ], $logger);
    }

    private static function recordingHandler(ArrayObject $calls): ExceptionHandlerInterface
    {
        return new readonly class($calls) implements ExceptionHandlerInterface {
            public function __construct(private ArrayObject $calls)
            {
            }

            public function handle(Throwable $throwable): void
            {
                $this->calls->append('successful');
            }
        };
    }

    /**
     * @param array<class-string, AttributeMetadata<object>> $declarations
     * @param array<class-string, object>                    $handlers
     *
     * @throws Throwable When fixture setup or a supplied test callback fails
     */
    private static function dispatcher(
        array                 $declarations,
        array                 $handlers,
        RecordingStdoutLogger $logger,
    ): ExceptionDispatcher
    {
        $classAttributes = array_map(
            static fn(AttributeMetadata $declaration): array => [
                $declaration,
            ],
            $declarations,
        );

        $provider = new ExceptionHandlerProvider(
            new AnnotationCollector([
                new PackageAnnotations('acme/fixture', null, $classAttributes, [], []),
            ]),
            new ArrayContainer($handlers),
        );

        return new ExceptionDispatcher(
            $provider,
            new FallbackExceptionReporter($logger),
        );
    }
}
