<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\ApiKeySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class ApiKeySubscriberTest extends TestCase
{
    private const VALID_KEY = 'my_secret_api_key_123';
    private const READ_ONLY_KEY = 'my_read_only_api_key_456';

    public function test_api_key_valid_via_header(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
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

    public function test_api_key_via_query_param_is_rejected(): void
    {
        // Query parameter is no longer accepted — only X-API-Key header.
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
        $request = Request::create('/api/v1/logs?api_key='.self::VALID_KEY, 'GET');
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

    public function test_api_key_missing_returns401(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
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

    public function test_api_key_invalid_returns401(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
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

    public function test_non_api_routes_are_skipped(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
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

    public function test_valid_header_succeeds_even_with_query_params_present(): void
    {
        // Query params are now ignored entirely; only the header matters.
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
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

    public function test_empty_header_and_query_params_treated_as_missing(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
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

    public function test_read_only_key_allowed_on_get(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
        $request = Request::create('/api/v1/logs', 'GET');
        $request->headers->set('X-API-Key', self::READ_ONLY_KEY);
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);
        $event->expects($this->never())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function test_read_only_key_rejected_on_post(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
        $request = Request::create('/api/v1/logs', 'POST');
        $request->headers->set('X-API-Key', self::READ_ONLY_KEY);
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);
        $event->expects($this->once())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function test_read_only_key_rejected_on_put(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
        $request = Request::create('/api/v1/logs/1', 'PUT');
        $request->headers->set('X-API-Key', self::READ_ONLY_KEY);
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);
        $event->expects($this->once())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function test_read_only_key_rejected_on_delete(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
        $request = Request::create('/api/v1/logs/1', 'DELETE');
        $request->headers->set('X-API-Key', self::READ_ONLY_KEY);
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);
        $event->expects($this->once())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function test_read_only_key_not_configured_means_no_read_only_access(): void
    {
        // When no read-only key is configured (null), even a valid-looking
        // read-only key must be rejected.
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, null);
        $request = Request::create('/api/v1/logs', 'GET');
        $request->headers->set('X-API-Key', self::READ_ONLY_KEY);
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);
        $event->expects($this->once())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }

    public function test_admin_key_allowed_on_write_when_read_only_configured(): void
    {
        $subscriber = new ApiKeySubscriber(self::VALID_KEY, self::READ_ONLY_KEY);
        $request = Request::create('/api/v1/logs', 'POST');
        $request->headers->set('X-API-Key', self::VALID_KEY);
        $request->attributes->set('_route', 'api_logs_');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->atLeastOnce())
            ->method('getRequest')
            ->willReturn($request);
        $event->expects($this->never())
            ->method('setResponse');

        $subscriber->onKernelRequest($event);
    }
}
