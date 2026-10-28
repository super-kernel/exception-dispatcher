<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Provider;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\ExceptionDispatcher\Exception\InvalidExceptionHandlerDeclarationException;
use SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerProvider;

#[FactoryProvider(ExceptionHandlerProvider::class)]
final readonly class ExceptionHandlerProviderProvider
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * @throws NotFoundExceptionInterface                  when the collector or a discovered handler is not registered
     * @throws ContainerExceptionInterface                 when the collector or a discovered handler cannot be resolved
     * @throws InvalidExceptionHandlerDeclarationException when a discovered handler has an invalid declaration
     */
    public function __invoke(): ExceptionHandlerProvider
    {
        return new ExceptionHandlerProvider(
            $this->container->get(AnnotationCollectorInterface::class),
            $this->container,
        );
    }
}
