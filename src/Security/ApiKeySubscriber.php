<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiKeySubscriber implements EventSubscriberInterface
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public static function getSubscribedEvents(): array
    {
        return [RequestEvent::class => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only enforce on API routes, skip the main dashboard page and assets
        if (!$this->isApiRoute($event)) {
            return;
        }

        $request = $event->getRequest();
        $headerKey = trim($request->headers->get('X-API-Key') ?? '');
        $paramKey  = trim($request->query->get('api_key', ''));

        if ($headerKey === '' && $paramKey === '') {
            $this->rejectUnauthorized($event, 'Missing API key. Provide it via X-API-Key header or api_key query parameter.');
            return;
        }

        $provided = $headerKey !== '' ? $headerKey : $paramKey;

        // Use hash_equals to prevent timing attacks when comparing keys
        if (!hash_equals($this->apiKey, $provided)) {
            $this->rejectUnauthorized($event, 'Invalid API key.');
        }
    }

    private function isApiRoute(RequestEvent $event): bool
    {
        // Check route name or path prefix; adjust as needed once routes are loaded
        $routeName = $event->getRequest()->attributes->get('_route') ?? '';
        return str_starts_with($routeName, 'api_logs_');
    }

    private function rejectUnauthorized(RequestEvent $event, string $message): void
    {
        $response = new JsonResponse(['error' => $message], 401);
        $event->setResponse($response);
    }
}
