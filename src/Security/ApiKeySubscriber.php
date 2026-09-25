<?php

declare(strict_types=1);

namespace App\Security;

use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiKeySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $readOnlyApiKey = null,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only guard API routes; static assets and the dashboard are public.
        if (!$this->isApiRoute($event)) {
            return;
        }

        $request = $event->getRequest();
        $headerKey = trim($request->headers->get('X-API-Key') ?? '');

        if ('' === $headerKey) {
            $this->rejectUnauthorized($event, 'Missing API key. Provide it via X-API-Key header.');

            return;
        }

        $provided = $headerKey;

        // Admin (full-access) key — always valid.
        if (hash_equals($this->apiKey, $provided)) {
            return;
        }

        // Read-only key — valid only for safe HTTP methods.
        if ($this->readOnlyApiKey && hash_equals($this->readOnlyApiKey, $provided)) {
            if ($this->isSafeMethod($request)) {
                return;
            }

            $this->rejectUnauthorized($event, 'Read-only API key cannot perform write operations.');

            return;
        }

        // hash_equals avoids leaking the key via timing.
        $this->rejectUnauthorized($event, 'Invalid API key.');
    }

    /**
     * True when this request targets an API route. Requests that match no
     * route (e.g. "/" or an unknown path) are left alone — the router will
     * produce the appropriate 404 and static files are served directly.
     */
    private function isApiRoute(RequestEvent $event): bool
    {
        $routeName = $event->getRequest()->attributes->get('_route');

        // No route matched (404 in progress) → not an API route.
        if (null === $routeName) {
            return false;
        }

        return str_starts_with((string) $routeName, 'api_logs_');
    }

    /**
     * HTTP methods that are safe (no state change): GET and HEAD.
     * OPTIONS is handled by the framework/CORS layer before this point;
     * anything else (POST, PUT, DELETE, PATCH…) is a write.
     */
    private function isSafeMethod(Request $request): bool
    {
        return \in_array($request->getMethod(), ['GET', 'HEAD'], true);
    }

    private function rejectUnauthorized(RequestEvent $event, string $message): void
    {
        $event->setResponse(new JsonResponse(['error' => $message], Response::HTTP_UNAUTHORIZED));
    }
}
