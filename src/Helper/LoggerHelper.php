<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Helper;

use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;
use Sirix\ContainerResolver\ContainerResolver;

final class LoggerHelper
{
    /**
     * @throws ContainerExceptionInterface
     */
    public static function getLogger(ContainerResolver $containerResolver): ?LoggerInterface
    {
        foreach ([LoggerInterface::class, 'Monolog\Logger', 'logger'] as $serviceId) {
            $logger = $containerResolver->optionalExisting($serviceId);

            if ($logger instanceof LoggerInterface) {
                return $logger;
            }
        }

        return null;
    }
}
