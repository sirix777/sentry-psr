<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Helper;

use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

final class LoggerHelper
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public static function getLogger(ContainerInterface $container): ?LoggerInterface
    {
        $logger = match (true) {
            $container->has(LoggerInterface::class) => $container->get(LoggerInterface::class),
            $container->has(Logger::class) => $container->get(Logger::class),
            $container->has('logger') => $container->get('logger'),
            default => null,
        };

        return $logger instanceof LoggerInterface ? $logger : null;
    }
}
