<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Exception;

use InvalidArgumentException;

final class InvalidExceptionHandlerDeclarationException extends InvalidArgumentException implements
    ExceptionDispatcherExceptionInterface
{
}
