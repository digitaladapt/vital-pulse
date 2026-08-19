<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiKeySubscriber implements EventSubscriberInterface
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

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

        if ($headerKey === '') {
            // Fall back to ?api_key= query parameter
            $queryKey = trim($request->query->get('api_key') ?? '');

            if ($queryKey === '') {
                $this->rejectUnauthorized($event, 'Missing API key. Provide it via X-API-Key header.');

                return;
            }

            $headerKey = $queryKey;
        }

        $provided = $headerKey;

        // hash_equals to avoid leaking the key via timing.
        if (!hash_equals($this->apiKey, $provided)) {
            $this->rejectUnauthorized($event, 'Invalid API key.');
        }
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

    private function rejectUnauthorized(RequestEvent $event, string $message): void
    {
        $event->setResponse(new JsonResponse(['error' => $message], 401));
    }
}
