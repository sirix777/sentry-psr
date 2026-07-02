<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\ExceptionFilter;

use Throwable;

interface ExceptionFilterInterface
{
    public function shouldCapture(Throwable $throwable, ExceptionFilterContext $exceptionFilterContext): bool;
}
