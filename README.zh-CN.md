<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Exception Dispatcher

`super-kernel/exception-dispatcher` 提供 SuperKernel 运行时边界所用的、可直接接入 DI 的异常处理器注册表和通用分发器。

## 声明处理器

处理器是一个带有 `#[ExceptionHandler]` 并实现 `Contract\ExceptionHandlerInterface` 的类，由其 `handle(Throwable): void` 方法执行与协议无关的异常处理。这个注解声明要处理的 `Throwable` 类（默认 `Throwable::class`）和整数优先级（默认 `0`）。处理器从 DI 中解析；优先级高的先执行，优先级相同时保持发现顺序。

如需自定义异常输出，可以声明一个 `#[ExceptionHandler(exception: Throwable::class)]` 处理器，或通过 `StdoutLoggerInterface` 契约替换 stdout 日志格式。

## 分发

`SuperKernel\Contract\ExceptionDispatcherInterface::dispatch()` 只接收一个 `Throwable`，返回 `void`。通用分发器会调用所有匹配的处理器；处理器匹配其声明的异常类及其子类。某个处理器失败时会进行兜底报告，但不会阻止后续匹配的处理器执行。如果没有任何匹配的处理器成功完成，原始异常会通过 `SuperKernel\Contract\StdoutLoggerInterface` 写出一次。分发器不会选择 HTTP 响应、进程退出、任务结果或其他协议动作。

兜底记录始终通过 `StdoutLoggerInterface` 写出完整的 `(string) $throwable`，包括 PHP 提供的文件、行号、调用栈和前序异常链；应用无需为本组件做任何配置。

只应在当前进程的 DI 容器就绪之后使用分发器；对于在此之前发生的失败，它不提供任何处理器。

## 协议专用处理器

协议组件可以从 `Attribute\ExceptionHandler` 派生自己的注解，并校验自己的处理器契约。`Registry\ExceptionHandlerProvider::getHandlersForAttribute()` 按优先级顺序返回某一个确切注解类的注册项，因此通用分发器只处理确切的 `#[ExceptionHandler]` 声明。`ExceptionHandlerRegistration::matches(Throwable $throwable): bool` 提供共享的匹配规则：

```php
foreach ($handlerProvider->getHandlersForAttribute($attributeClass) as $registration) {
    if ($registration->matches($throwable)) {
        // Apply the protocol's handler contract and response/propagation rules.
    }
}
```
