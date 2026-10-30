# SuperKernel Exception Dispatcher

`super-kernel/exception-dispatcher` provides the DI-ready exception-handler registry and generic dispatcher used by
SuperKernel runtime boundaries.

`Attribute\ExceptionHandler` is the base class-only Attribute. It declares the handled `Throwable` class and numeric
priority. A handler carrying that exact Attribute implements the component-local
`Contract\ExceptionHandlerInterface`, whose `handle(Throwable): void` method performs non-protocol exception handling.

`Registry\ExceptionHandlerProvider` discovers the base Attribute and every derived native Attribute once, resolves the marked
objects from DI, consumes the trusted concrete declaration instances returned by Annotator, and keeps them in
descending, stable priority order. It does not reconstruct declarations from metadata arguments. Consumers query an
exact Attribute class through `getHandlersForAttribute()`: the generic `ExceptionDispatcher` therefore consumes only
exact `Attribute\ExceptionHandler` declarations, while another component may derive its own Attribute and validate its
own handler contract against registrations returned by the same provider.

`ExceptionHandlerRegistration::matches(Throwable $throwable): bool` exposes the shared `is_a` matching rule to
protocol dispatchers. It accepts subclasses of the declared exception and leaves ordering with the provider.
Protocol consumers use the shared rule:

```php
foreach ($handlerProvider->getHandlersForAttribute($attributeClass) as $registration) {
    if ($registration->matches($throwable)) {
        // Apply the protocol's handler contract and response/propagation rules.
    }
}
```

`SuperKernel\Contract\ExceptionDispatcherInterface::dispatch()` accepts only a `Throwable` and returns `void`. The
generic dispatcher invokes every matching base handler. A failed handler is fallback-reported and does not prevent a
later matching handler from running. If no matching handler completes successfully, the original throwable is written
once through `SuperKernel\Contract\StdoutLoggerInterface`. The dispatcher does not select an HTTP response, process
exit, task result, or another protocol action.

Fallback records always contain the complete `(string) $throwable`, including PHP's file, line, stack, and
previous-exception chain, through `StdoutLoggerInterface`; applications need no configuration for this component.
To customize exception output, declare an `#[ExceptionHandler(exception: Throwable::class)]` handler or replace stdout
log formatting through the `StdoutLoggerInterface` contract.

The dispatcher is intended only after the current process DI graph is ready.

## Source organization

Paths below are relative to `src/` and the package namespace.

| Directory  | Responsibility                                                                                                     |
|------------|--------------------------------------------------------------------------------------------------------------------|
| `Registry` | `ExceptionHandlerProvider` and `ExceptionHandlerRegistration`: discovery, ordering, and exact declaration queries. |
| `Reporter` | `FallbackExceptionReporter`: terminal exception logging.                                                           |
| `Provider` | DI factories for the registry and dispatcher.                                                                      |

### Public namespaces

Use the following component declarations:

- `Registry\ExceptionHandlerProvider`
- `Registry\ExceptionHandlerRegistration`
- `Reporter\FallbackExceptionReporter`

## Development

Use a PHP 8.4 Composer in this package's checkout:

```bash
composer install
composer validate --strict
composer test
composer format:check
composer format
```
