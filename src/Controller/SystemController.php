<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SystemController
{
    private const FALLBACK_VERSION = '1.3.0';

    #[Route('/api/about', name: 'api_about', methods: ['GET'])]
    public function about(): JsonResponse
    {
        return new JsonResponse([
            'name' => 'vital-pulse',
            'version' => $this->getVersion(),
        ]);
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'healthy',
        ]);
    }

    /**
     * Read the latest git tag and strip the "v" prefix.
     * Falls back to a hardcoded constant if git is unavailable.
     */
    private function getVersion(): string
    {
        $tag = @shell_exec('git describe --tags --abbrev=0 2>/dev/null');

        if ($tag !== null && $tag !== '') {
            $tag = trim($tag);
            // Strip leading "v" if present
            if (str_starts_with($tag, 'v')) {
                $tag = substr($tag, 1);
            }

            return $tag;
        }

        return self::FALLBACK_VERSION;
    }
}
