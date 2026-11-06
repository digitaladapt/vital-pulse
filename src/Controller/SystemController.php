<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AppVersion;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SystemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%app.reading_warnings_enabled%')]
        private bool $readingWarningsEnabled,
        private readonly AppVersion $appVersion,
    ) {
    }

    #[Route('/api/about', name: 'api_about', methods: ['GET'])]
    public function about(): JsonResponse
    {
        return new JsonResponse([
            'name' => 'vital-pulse',
            'version' => $this->appVersion->getVersion(),
            'reading_warnings_enabled' => $this->readingWarningsEnabled,
        ]);
    }

    /**
     * Liveness probe (GUIDING-LIGHT §8.4).
     *
     * Deliberately does NOT touch the database. It answers exactly one
     * question — "is this process up?" — and nothing else.
     *
     * The previous arrangement had a single health endpoint that ran a DB
     * query, which inverts the failure mode: a locked or missing SQLite file
     * made the container report unhealthy and the orchestrator dutifully
     * killed and restarted it, over and over, for a problem a restart cannot
     * fix. Dependency trouble is a readiness concern, not a liveness one.
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'healthy',
        ]);
    }

    /**
     * Readiness probe (GUIDING-LIGHT §8.4).
     *
     * This is where the dependency check belongs: dependencies reachable
     * (here, SQLite), so may query the database. A failing readiness probe
     * takes the instance out of rotation; it does not get it killed.
     */
    #[Route('/ready', name: 'ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1')->fetchOne();
        } catch (Exception) {
            return new JsonResponse([
                'status' => 'unready',
                'error' => 'Database connection failed',
            ], 503);
        }

        return new JsonResponse([
            'status' => 'ready',
        ]);
    }

    /**
     * @deprecated Use /ready. Kept so existing deployments whose HEALTHCHECK
     *             still points here do not break mid-upgrade; it now behaves
     *             as a readiness probe. Remove once nothing references it.
     */
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function healthDeprecated(): JsonResponse
    {
        try {
            $this->entityManager->getConnection()->executeQuery('SELECT 1')->fetchOne();
        } catch (Exception) {
            return new JsonResponse([
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
            ], 503);
        }

        return new JsonResponse([
            'status' => 'healthy',
        ]);
    }
}
