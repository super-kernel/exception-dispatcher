<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use SuperKernel\Annotator\AnnotationCollector;
use SuperKernel\Annotator\Model\AttributeMetadata;
use SuperKernel\Annotator\Model\PackageAnnotations;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\AttributeMetadataInterface;
use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use SuperKernel\ExceptionDispatcher\Exception\InvalidExceptionHandlerDeclarationException;
use SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerProvider;
use SuperKernel\ExceptionDispatcher\Tests\Fixture\ArrayContainer;
use SuperKernel\ExceptionDispatcher\Tests\Fixture\InvalidProtocolExceptionHandler;
use SuperKernel\ExceptionDispatcher\Tests\Fixture\ProtocolExceptionHandler;
use Throwable;

use function iterator_to_array;

final class ExceptionHandlerProviderTest extends TestCase
{
    /** @throws Throwable When independent metadata or a discovered handler cannot be resolved */
    public function testRetainsDerivedDeclarationsFromIndependentMetadata(): void
    {
        $handler = new stdClass();
        $declaration = new ProtocolExceptionHandler(protocol: 'http');
        $metadata = $this->createStub(AttributeMetadataInterface::class);
        $metadata->method('attribute')->willReturn($declaration);
        $collector = $this->createMock(AnnotationCollectorInterface::class);
        $collector->expects($this->once())->method('getClassesWithAttributeAssignableTo')
            ->with(ExceptionHandler::class)->willReturn([
                'CustomHandler' => [
                    $metadata,
                ],
            ]);
        $provider = new ExceptionHandlerProvider($collector, new ArrayContainer([
            'CustomHandler' => $handler,
        ]));

        $registrations = iterator_to_array($provider->getHandlersForAttribute(ProtocolExceptionHandler::class));

        self::assertCount(1, $registrations);
        self::assertSame($declaration, $registrations[0]->declaration);
        self::assertSame($handler, $registrations[0]->handler);
        self::assertSame([], iterator_to_array($provider->getHandlersForAttribute(ExceptionHandler::class)));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testIndexesExactAttributeFamiliesInStablePriorityOrder(): void
    {
        $base = new stdClass();
        $firstDerived = new stdClass();
        $secondDerived = new stdClass();
        $provider = self::provider([
            'BaseHandler' => [
                new AttributeMetadata(new ExceptionHandler(
                    priority : 10,
                )),
            ],
            'FirstDerivedHandler' => [
                new AttributeMetadata(new ProtocolExceptionHandler(
                    protocol : 'http',
                    priority : 100,
                ), [
                    ExceptionHandler::class,
                ]),
            ],
            'SecondDerivedHandler' => [
                new AttributeMetadata(new ProtocolExceptionHandler(
                    protocol : 'rpc',
                    priority : 100,
                ), [
                    ExceptionHandler::class,
                ]),
            ],
        ], [
            'BaseHandler'          => $base,
            'FirstDerivedHandler'  => $firstDerived,
            'SecondDerivedHandler' => $secondDerived,
        ]);

        $baseRegistrations = iterator_to_array($provider->getHandlersForAttribute(ExceptionHandler::class), false);
        $derivedRegistrations = iterator_to_array(
            $provider->getHandlersForAttribute(ProtocolExceptionHandler::class),
            false,
        );

        self::assertCount(1, $baseRegistrations);
        self::assertSame($base, $baseRegistrations[0]->handler);
        self::assertSame([
            $firstDerived,
            $secondDerived,
        ], [
            $derivedRegistrations[0]->handler,
            $derivedRegistrations[1]->handler,
        ]);
        $firstDeclaration = $derivedRegistrations[0]->declaration;
        $secondDeclaration = $derivedRegistrations[1]->declaration;
        self::assertInstanceOf(ProtocolExceptionHandler::class, $firstDeclaration);
        self::assertInstanceOf(ProtocolExceptionHandler::class, $secondDeclaration);
        self::assertSame([
            'http',
            'rpc',
        ], [
            $firstDeclaration->protocol,
            $secondDeclaration->protocol,
        ]);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsMultipleDeclarationsFromTheExceptionHandlerAttributeFamily(): void
    {
        $this->expectException(InvalidExceptionHandlerDeclarationException::class);
        $this->expectExceptionMessageIsOrContains('must declare exactly one Attribute');

        self::provider([
            'Handler' => [
                new AttributeMetadata(new ExceptionHandler()),
                new AttributeMetadata(new ProtocolExceptionHandler(), [
                    ExceptionHandler::class,
                ]),
            ],
        ], [
            'Handler' => new stdClass(),
        ]);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsADerivedDeclarationThatIsNotANativeAttribute(): void
    {
        $this->expectException(InvalidExceptionHandlerDeclarationException::class);
        $this->expectExceptionMessageIsOrContains('must itself declare');

        self::provider([
            'Handler' => [
                new AttributeMetadata(new InvalidProtocolExceptionHandler(), [
                    ExceptionHandler::class,
                ]),
            ],
        ], [
            'Handler' => new stdClass(),
        ]);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsADeclarationThatDoesNotNameAThrowableClass(): void
    {
        $this->expectException(InvalidExceptionHandlerDeclarationException::class);
        $this->expectExceptionMessageIsOrContains('must declare a Throwable class');

        self::provider([
            'Handler' => [
                new AttributeMetadata(new ExceptionHandler(
                    exception : RuntimeException::class . 'Missing',
                )),
            ],
        ], [
            'Handler' => new stdClass(),
        ]);
    }

    /**
     * @param array<class-string, list<AttributeMetadata<object>>> $declarations
     * @param array<class-string, object>                          $handlers
     *
     * @throws Throwable When fixture setup or a supplied test callback fails
     */
    private static function provider(array $declarations, array $handlers): ExceptionHandlerProvider
    {
        return new ExceptionHandlerProvider(
            new AnnotationCollector([
                new PackageAnnotations('acme/fixture', null, $declarations, [], []),
            ]),
            new ArrayContainer($handlers),
        );
    }
}
