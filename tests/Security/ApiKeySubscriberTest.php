<?php

namespace App\Tests\Security;

use App\Security\ApiKeySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ApiKeySubscriberTest extends TestCase
{
    private const VALID_KEY = 'my_secret_api_key_123';

    public function testApiKeyValidViaHeader(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY);
        $request = Request::create('/api/v1/logs', 'GET');
        $request->headers->set('X-API-Key', self::VALID_KEY);
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);

        // No exception should be thrown, and setResponse should NOT be called for valid key
        $event->expects($this->never())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function testApiKeyViaQueryParamIsRejected(): void
    {
        // Query parameter is no longer accepted — only X-API-Key header.
        $subscriber = new ApiKeySubscriber(self::VALID_KEY);
        $request = Request::create('/api/v1/logs?api_key=' . self::VALID_KEY, 'GET');
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);

        // Even a valid key via query param should be rejected
        $event->expects($this->once())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function testApiKeyMissingReturns401(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY);
        $request = Request::create('/api/v1/logs', 'GET');
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);

        // Missing key should call setResponse with 401 response
        $event->expects($this->once())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function testApiKeyInvalidReturns401(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY);
        $request = Request::create('/api/v1/logs', 'GET');
        $request->headers->set('X-API-Key', 'wrong_key');
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);

        // Invalid key should call setResponse with 401 response
        $event->expects($this->once())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function testNonApiRoutesAreSkipped(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY);
        $request = Request::create('/index.html', 'GET');
        // Simulate non-API route (no api_logs_ prefix)
        $request->attributes->set('_route', '');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);

        // Non-API route should NOT require API key (no rejection)
        $event->expects($this->never())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function testValidHeaderSucceedsEvenWithQueryParamsPresent(): void
    {
        // Query params are now ignored entirely; only the header matters.
        $subscriber = new ApiKeySubscriber(self::VALID_KEY);
        $request = Request::create('/api/v1/logs?api_key=wrong', 'GET');
        $request->headers->set('X-API-Key', self::VALID_KEY);
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);

        // Header is valid, should not reject
        $event->expects($this->never())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function testEmptyHeaderAndQueryParamsTreatedAsMissing(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY);
        $request = Request::create('/api/v1/logs', 'GET');
        // Set empty values that should be treated as missing
        $request->headers->set('X-API-Key', '');
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);

        // Empty key is treated as missing → should reject
        $event->expects($this->once())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }
}
