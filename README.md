<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Exception Dispatcher

`super-kernel/exception-dispatcher` provides the DI-ready exception-handler registry and generic dispatcher used by SuperKernel runtime boundaries.

## Declaring handlers

A handler is a class carrying `#[ExceptionHandler]` and implementing `Contract\ExceptionHandlerInterface`, whose `handle(Throwable): void` method performs non-protocol exception handling. The Attribute declares the handled `Throwable` class, `Throwable::class` by default, and an integer priority, `0` by default. Handlers are resolved from DI; higher priorities run first and equal priorities keep discovery order.

To customize exception output, declare an `#[ExceptionHandler(exception: Throwable::class)]` handler or replace stdout log formatting through the `StdoutLoggerInterface` contract.

## Dispatching

`SuperKernel\Contract\ExceptionDispatcherInterface::dispatch()` accepts only a `Throwable` and returns `void`. The generic dispatcher invokes every matching handler; a handler matches the declared exception class and its subclasses. A failed handler is fallback-reported and does not prevent a later matching handler from running. If no matching handler completes successfully, the original throwable is written once through `SuperKernel\Contract\StdoutLoggerInterface`. The dispatcher does not select an HTTP response, process exit, task result, or another protocol action.

Fallback records always contain the complete `(string) $throwable`, including PHP's file, line, stack, and previous-exception chain, through `StdoutLoggerInterface`; applications need no configuration for this component.

Use the dispatcher only after the current process's DI container is ready; it provides no handler for failures that occur before then.

## Protocol-specific handlers

A protocol component may derive its own Attribute from `Attribute\ExceptionHandler` and validate its own handler contract. `Registry\ExceptionHandlerProvider::getHandlersForAttribute()` returns the registrations for one exact Attribute class in priority order, so the generic dispatcher handles only exact `#[ExceptionHandler]` declarations. `ExceptionHandlerRegistration::matches(Throwable $throwable): bool` exposes the shared matching rule:

```php
foreach ($handlerProvider->getHandlersForAttribute($attributeClass) as $registration) {
    if ($registration->matches($throwable)) {
        // Apply the protocol's handler contract and response/propagation rules.
    }
}
```
