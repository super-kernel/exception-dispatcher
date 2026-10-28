<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Registry;

use Attribute;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionObject;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\AttributeMetadataInterface;
use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use SuperKernel\ExceptionDispatcher\Exception\InvalidExceptionHandlerDeclarationException;
use Throwable;

use function count;
use function is_a;
use function sprintf;
use function usort;

final readonly class ExceptionHandlerProvider
{
    /** @var list<ExceptionHandlerRegistration<ExceptionHandler>> */
    private array $registrations;

    /**
     * @throws NotFoundExceptionInterface                  when a discovered handler is not registered in the container
     * @throws ContainerExceptionInterface                 when a discovered handler cannot be resolved
     * @throws InvalidExceptionHandlerDeclarationException when a handler has multiple or invalid declarations
     */
    public function __construct(AnnotationCollectorInterface $collector, ContainerInterface $container)
    {
        $registrations = [];
        $order = 0;

        foreach (
            $collector->getClassesWithAttributeAssignableTo(ExceptionHandler::class) as $className => $attributes
        ) {
            if (1 !== count($attributes)) {
                throw new InvalidExceptionHandlerDeclarationException(sprintf(
                    '%s must declare exactly one Attribute derived from %s; %d found.',
                    $className,
                    ExceptionHandler::class,
                    count($attributes),
                ));
            }

            $declaration = self::declaration($attributes[0], $className);
            $registrations[] = [
                'registration' => new ExceptionHandlerRegistration(
                    $declaration,
                    $container->get($className),
                ),
                'priority' => $declaration->priority,
                'order'    => $order++,
            ];
        }

        usort(
            $registrations,
            static fn(array $left, array $right): int => $right['priority'] <=> $left['priority']
                ?: $left['order'] <=> $right['order'],
        );

        $orderedRegistrations = [];

        foreach ($registrations as $registration) {
            $orderedRegistrations[] = $registration['registration'];
        }

        $this->registrations = $orderedRegistrations;
    }

    /**
     * Returns handlers carrying the exact requested exception-handler Attribute class.
     *
     * @template TDeclaration of ExceptionHandler
     *
     * @param class-string<TDeclaration> $attribute
     *
     * @return iterable<ExceptionHandlerRegistration<TDeclaration>>
     */
    public function getHandlersForAttribute(string $attribute): iterable
    {
        foreach ($this->registrations as $registration) {
            if ($registration->declaration::class !== $attribute) {
                continue;
            }

            /* @var ExceptionHandlerRegistration<TDeclaration> $registration */
            yield $registration;
        }
    }

    /**
     * @param AttributeMetadataInterface<ExceptionHandler> $metadata
     * @param class-string                                 $className
     *
     * @throws InvalidExceptionHandlerDeclarationException when the native Attribute shape or exception type is invalid
     */
    private static function declaration(AttributeMetadataInterface $metadata, string $className): ExceptionHandler
    {
        /** @var ExceptionHandler $declaration */
        $declaration = $metadata->attribute();
        $attribute = $declaration::class;

        $nativeDeclaration = new ReflectionObject($declaration)->getAttributes(Attribute::class);

        if (1 !== count($nativeDeclaration)) {
            throw new InvalidExceptionHandlerDeclarationException(sprintf(
                '%s on %s must itself declare PHP\'s native %s metadata.',
                $attribute,
                $className,
                Attribute::class,
            ));
        }

        $flags = $nativeDeclaration[0]->newInstance()->flags;

        if (Attribute::TARGET_CLASS !== $flags) {
            throw new InvalidExceptionHandlerDeclarationException(sprintf(
                '%s on %s must be a non-repeatable class-only Attribute.',
                $attribute,
                $className,
            ));
        }

        if (!is_a($declaration->exception, Throwable::class, true)) {
            throw new InvalidExceptionHandlerDeclarationException(sprintf(
                '%s on %s must declare a Throwable class; %s provided.',
                $attribute,
                $className,
                $declaration->exception,
            ));
        }

        return $declaration;
    }
}
