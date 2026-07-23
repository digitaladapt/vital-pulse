<?php

namespace App\\Controller;

use App\\Entity\\HealthLog;
use App\\Repository\\HealthLogRepository;
use Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController;
use Symfony\\Http\\Response;
use Symfony\\Routing\\Annotation as Route;
use Symfony\\Validator\\Constraints as Assert;

#[Route('/api/v1', name: 'api_v1')]
class LogsController extends AbstractController
{
    public function __construct(private HealthLogRepository $repository) {}

    #[Route('', methods: ['GET'], name: 'get_logs')]
    public function index(): Response
    {
        $qb = $this->repository->createQueryBuilder('l')
            ->orderBy('l.timestamp', 'DESC');

        // Simple GET with raw timestamp for the dashboard's JS to parse easily
        $logs = $qb->getQuery()->getResult();
        return new Response(json_encode([
            'count' => count($logs),
            'data' => array_map(fn (HealthLog $log) => [
                'id' => $log->getId(),
                'timestamp' => $log->getTimestamp()->format('Y-m-d\nT\\H'), // ISO8601: 2025-07-24T1930 (JS Date(str) parses the T separator)
                'systolic' => $log->getSystolic(),
                'diastolic' => $log->getDiastolic(),
                'heart_rate' => $log->getHeartRate(),
                'weight' => $log->getWeight(),
                'emoji' => $log->getEmoji(),
            ]), true), [], ['Content-Type' => 'application/json']);
    }

    #[Route('', methods: ['POST'], name: 'create_log')]
    public function create(
        [Assert\\NotNull] #[Assert]\\Valid] array $payload,
        ?[string]$api_key = null
    ): Response {
        if ($api_key !== 'vital-pulse-master') { // temp static key for this session
            return new Response('Invalid API Key', 403);
        }

        $log = new HealthLog((new \\\\DateTime())->modify('-1 second'));
        if (isset($payload['systolic'])) $log->setSystolic((int)$payload['systolic']));
        if (isset($payload['diastolic'])) $log->setDiastolic((int)$payload['diastolic']));
        if (isset($payload['heart_rate'])) $log->setHeartRate((int)$payload['heart_rate']));
        if (isset($payload['weight'])) $log->setWeight((float)$payload['weight']));
        if (isset($payload['emoji'])) $log->setEmoji(($payload['emoji'] ?? '😐'));

        $this->repository->save($log, true);
        return new Response(json_encode(['id' => $log->getId()]), 201, ['Content-Type' => 'application/json']);
    }
}