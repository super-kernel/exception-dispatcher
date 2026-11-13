<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Exception Dispatcher

`super-kernel/exception-dispatcher` sends the exceptions that reach SuperKernel's boundaries, such as a failed console command, to the handlers that classes declare with its `#[ExceptionHandler]` Attribute, and logs the ones that no handler takes.

## Installation

```bash
composer require super-kernel/exception-dispatcher
```

The dispatcher writes the exceptions that no handler takes through `SuperKernel\Contract\StdoutLoggerInterface` and needs a provider of it, such as `super-kernel/logger`, which the package suggests but does not require; install it with `composer require super-kernel/logger` unless the application provides its own. Without a provider, the dispatcher cannot be created, so with `super-kernel/framework` installed `skernel build` and the application's start fail with `Unable to resolve method parameter SuperKernel\ExceptionDispatcher\Provider\ExceptionDispatcherProvider::__invoke($logger) of type SuperKernel\Contract\StdoutLoggerInterface.` Handlers are created through the container and found in the metadata that `skernel build` generates, so a project needs SKERNEL to build it.

## What it provides

- Handlers declared with `#[ExceptionHandler]` for one throwable class and its subclasses, ordered by priority.
- `SuperKernel\Contract\ExceptionDispatcherInterface`, which calls every matching handler.
- A record of the complete exception through the stdout logger when no handler completes.
- A failing handler logged and skipped, without stopping the others.
- Derived Attributes and a handler registry, so another boundary can keep its own handler family.

It does not:

- choose an HTTP response, a process exit, a retry, or another recovery action;
- stop at the first handler that matches;
- handle exceptions before the container is ready;
- catch exceptions by itself; the boundaries, such as `super-kernel/framework`, dispatch them.

## Quick start

Declare a handler. Save it as `src/ExceptionHandler/ReportFailure.php`:

```php
<?php

declare(strict_types=1);

namespace Src\ExceptionHandler;

use RuntimeException;
use SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler;
use SuperKernel\ExceptionDispatcher\Contract\ExceptionHandlerInterface;
use Throwable;

#[ExceptionHandler(RuntimeException::class)]
final readonly class ReportFailure implements ExceptionHandlerInterface
{
    public function handle(Throwable $throwable): void
    {
        echo 'Failed: ', $throwable->getMessage(), PHP_EOL;
    }
}
```

Dispatch an exception from a script. Save it as `bin/report.php`:

```php
<?php

declare(strict_types=1);

use SuperKernel\Context\ApplicationContext;
use SuperKernel\Contract\ExceptionDispatcherInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$exceptions = ApplicationContext::getContainer()->get(ExceptionDispatcherInterface::class);

$exceptions->dispatch(new RuntimeException('The invoice feed is unavailable.'));
```

Build the project and run the script:

```bash
skernel build
php bin/report.php
```

It prints `Failed: The invoice feed is unavailable.`.

## Public surface

| Name | Kind | Purpose |
| --- | --- | --- |
| `SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler` | Attribute | Registers the annotated class as a handler of `exception`, `Throwable` by default, with a `priority`, `0` by default; other boundaries derive their Attributes from it. |
| `SuperKernel\ExceptionDispatcher\Contract\ExceptionHandlerInterface` | Interface | Implemented by a handler: `handle()` receives the throwable. |
| `SuperKernel\Contract\ExceptionDispatcherInterface` | Interface | Provided by the container; `dispatch()` sends a throwable to every matching handler. |
| `SuperKernel\Contract\StdoutLoggerInterface` | Interface | Receives the complete exception when no handler completes, and each handler failure. |
| `SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerProvider` | Class | Provided by the container; `getHandlersForAttribute()` returns the handlers of one Attribute class in order. |
| `SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerRegistration` | Class | A handler with its declaration; `matches()` tells whether it takes a throwable. |

## Behavior to know

- Every matching handler runs, higher priorities first and equal priorities in the order the build found them; `dispatch()` returns nothing.
- A handler that throws is logged as `Exception handler <handler> failed while processing <exception class>: <failure>`, and the next handler still runs. When no matching handler completes, the original throwable is logged once, with its file, line, trace, and previous exceptions.
- The handlers are created with the dispatcher. An invalid declaration raises `SuperKernel\ExceptionDispatcher\Exception\InvalidExceptionHandlerDeclarationException` then, so nothing can be dispatched; with `super-kernel/framework` installed, `skernel build` and the application start fail.
- Handlers are found in the build metadata: run `skernel build` again after adding, removing, or changing a handler, or start with `skernel run`, which builds first.

## Documentation

The developer documentation describes ordering handlers, the fallback record, customizing the output, handler families for other boundaries, and every error message: [SuperKernel Exception Dispatcher](https://github.com/super-kernel/.github/blob/main/docs/components/exception-dispatcher.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).
