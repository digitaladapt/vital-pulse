<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SystemController extends AbstractController
{
    private const FALLBACK_VERSION = '1.4.2';

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
     * Determine the application version.
     *
     * Priority:
     *   1. VERSION file (baked into Docker image at build time)
     *   2. git describe (works in dev checkout where .git is available)
     *   3. Hardcoded fallback constant
     */
    private function getVersion(): string
    {
        $versionFile = $this->getParameter('kernel.project_dir') . '/VERSION';
        if (file_exists($versionFile)) {
            $version = trim(file_get_contents($versionFile));
            if ($version !== '' && $version !== 'dev') {
                // Strip leading "v" if present (consistent with git describe path)
                return str_starts_with($version, 'v') ? substr($version, 1) : $version;
            }
        }

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
