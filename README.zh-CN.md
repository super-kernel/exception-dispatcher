<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Exception Dispatcher

`super-kernel/exception-dispatcher` 把到达 SuperKernel 边界的异常（例如失败的控制台命令）交给类用它的 `#[ExceptionHandler]` 注解声明的处理器，并记录没有处理器接手的异常。

## 安装

```bash
composer require super-kernel/exception-dispatcher
```

分发器通过 `SuperKernel\Contract\StdoutLoggerInterface` 写出没有处理器接手的异常，该接口由 `super-kernel/logger` 提供；本包并不依赖它，因此除非应用自己提供该接口，否则也要安装它。处理器通过容器创建，并从 `skernel build` 生成的元数据中发现，因此项目需要用 SKERNEL 构建。

## 提供的能力

- 用 `#[ExceptionHandler]` 声明、处理某个异常类及其子类、按优先级排序的处理器。
- `SuperKernel\Contract\ExceptionDispatcherInterface`，调用每个匹配的处理器。
- 没有处理器完成时，通过标准输出日志记录器记录完整的异常。
- 失败的处理器会被记录并跳过，不会阻止其他处理器。
- 派生注解和处理器注册表，使其他边界可以维护自己的处理器家族。

它不会：

- 选择 HTTP 响应、进程退出、重试或其他恢复操作；
- 在第一个匹配的处理器处停止；
- 在容器就绪之前处理异常；
- 自己捕获异常，这由边界（例如 `super-kernel/framework`）分发。

## 快速开始

声明一个处理器。将它保存为 `src/ExceptionHandler/ReportFailure.php`：

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

从脚本中分发一个异常。将它保存为 `bin/report.php`：

```php
<?php

declare(strict_types=1);

use SuperKernel\Context\ApplicationContext;
use SuperKernel\Contract\ExceptionDispatcherInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$exceptions = ApplicationContext::getContainer()->get(ExceptionDispatcherInterface::class);

$exceptions->dispatch(new RuntimeException('The invoice feed is unavailable.'));
```

构建项目并运行脚本：

```bash
skernel build
php bin/report.php
```

它会打印 `Failed: The invoice feed is unavailable.`。

## 公开接口

| 名称 | 类别 | 用途 |
| --- | --- | --- |
| `SuperKernel\ExceptionDispatcher\Attribute\ExceptionHandler` | 注解 | 把被注解的类注册为 `exception`（默认为 `Throwable`）的处理器，并带有 `priority`（默认为 `0`）；其他边界从它派生自己的注解。 |
| `SuperKernel\ExceptionDispatcher\Contract\ExceptionHandlerInterface` | 接口 | 由处理器实现：`handle()` 接收异常。 |
| `SuperKernel\Contract\ExceptionDispatcherInterface` | 接口 | 由容器提供；`dispatch()` 把异常交给每个匹配的处理器。 |
| `SuperKernel\Contract\StdoutLoggerInterface` | 接口 | 在没有处理器完成时接收完整的异常，并接收每次处理器失败。 |
| `SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerProvider` | 类 | 由容器提供；`getHandlersForAttribute()` 按顺序返回某个注解类的处理器。 |
| `SuperKernel\ExceptionDispatcher\Registry\ExceptionHandlerRegistration` | 类 | 一个处理器及其声明；`matches()` 判断它是否接手某个异常。 |

## 需要了解的行为

- 每个匹配的处理器都会运行，优先级高的先运行，优先级相同的按构建发现它们的顺序运行；`dispatch()` 不返回任何值。
- 抛出异常的处理器会被记录为 `Exception handler <handler> failed while processing <exception class>: <failure>`，下一个处理器仍会运行。没有匹配的处理器完成时，原始异常会被记录一次，包括它的文件、行号、调用栈和前一个异常。
- 处理器与分发器一起创建。无效的声明会在那时抛出 `SuperKernel\ExceptionDispatcher\Exception\InvalidExceptionHandlerDeclarationException`，导致无法分发任何异常；安装了 `super-kernel/framework` 时，`skernel build` 和应用启动都会失败。
- 处理器从构建元数据中发现：添加、删除或修改处理器之后，请重新运行 `skernel build`，或者用会先构建的 `skernel run` 启动。

## 文档

开发者文档介绍了处理器的排序、兜底记录、自定义输出、其他边界的处理器家族，以及每条错误消息：[SuperKernel Exception Dispatcher](https://github.com/super-kernel/.github/blob/main/docs/components/exception-dispatcher.zh-CN.md)。

## 许可证

基于 MIT 许可证发布，详见 [LICENSE](LICENSE)。
