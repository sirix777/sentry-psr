<?php

declare(strict_types=1);

namespace Sirix\SentryPsr\Test\Helper;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sirix\SentryPsr\Helper\SentryHelper;

/**
 * @internal
 */
#[CoversClass(SentryHelper::class)]
class SentryHelperTest extends TestCase
{
    private HubInterface $hubMock;
    private Scope $scopeMock;

    protected function setUp(): void
    {
        $this->hubMock   = $this->createMock(HubInterface::class);
        $this->scopeMock = $this->createMock(Scope::class);

        $this->hubMock->method('withScope')->willReturnCallback(
            function($callback) {
                $callback($this->scopeMock);
            }
        );

        $this->hubMock->method('configureScope')->willReturnCallback(
            function($callback) {
                $callback($this->scopeMock);
            }
        );

        SentryHelper::setHub($this->hubMock);
    }

    public function testCaptureException(): void
    {
        $exception = new Exception('Test exception');

        $this->hubMock->expects($this->once())
            ->method('captureException')
            ->with($exception)
        ;

        $this->scopeMock->expects($this->once())
            ->method('setContext')
            ->with('additional_context', [
                'foo' => 'bar',
            ])
        ;

        $this->scopeMock->expects($this->once())
            ->method('setLevel')
            ->with(Severity::error())
        ;

        SentryHelper::captureException($exception, [
            'foo' => 'bar',
        ]);
    }

    public function testCaptureMessage(): void
    {
        $message = 'Test message';

        $this->hubMock->expects($this->once())
            ->method('captureMessage')
            ->with($message)
        ;

        $this->scopeMock->expects($this->once())
            ->method('setContext')
            ->with('additional_context', [
                'key' => 'value',
            ])
        ;

        $this->scopeMock->expects($this->once())
            ->method('setLevel')
            ->with(Severity::warning())
        ;

        SentryHelper::captureMessage($message, 'warning', [
            'key' => 'value',
        ]);
    }

    public function testAddBreadcrumb(): void
    {
        $this->hubMock->expects($this->once())
            ->method('addBreadcrumb')
            ->with($this->callback(function(Breadcrumb $breadcrumb) {
                return 'Test breadcrumb' === $breadcrumb->getMessage()
                    && Breadcrumb::LEVEL_INFO === $breadcrumb->getLevel()
                    && 'custom' === $breadcrumb->getCategory()
                    && $breadcrumb->getMetadata() === [
                        'foo' => 'bar',
                    ];
            }))
        ;

        SentryHelper::addBreadcrumb('Test breadcrumb', 'custom', 'info', [
            'foo' => 'bar',
        ]);
    }

    public function testSetUser(): void
    {
        $user = [
            'id'    => 123,
            'email' => 'test@example.com',
        ];

        $this->scopeMock->expects($this->once())
            ->method('setUser')
            ->with($user)
        ;

        SentryHelper::setUser($user);
    }

    public function testSetTag(): void
    {
        $this->scopeMock->expects($this->once())
            ->method('setTag')
            ->with('feature', 'new')
        ;

        SentryHelper::setTag('feature', 'new');
    }

    public function testSetContext(): void
    {
        $context = [
            'a' => 1,
        ];

        $this->scopeMock->expects($this->once())
            ->method('setContext')
            ->with('my_context', $context)
        ;

        SentryHelper::setContext('my_context', $context);
    }
}
