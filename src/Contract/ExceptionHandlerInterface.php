<?php

declare(strict_types=1);

namespace SuperKernel\ExceptionDispatcher\Contract;

use Throwable;

interface ExceptionHandlerInterface
{
    public function handle(Throwable $throwable): void;
}
