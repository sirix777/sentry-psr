<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Container;

use Psr\Container\ContainerInterface;

use function array_key_exists;

final readonly class InMemoryContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(private array $services = []) {}

    public function get(string $id): mixed
    {
        if (! $this->has($id)) {
            throw new NotFoundException($id);
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
