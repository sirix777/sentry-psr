<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Container;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

use function sprintf;

final class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Service "%s" was not found.', $id));
    }
}
