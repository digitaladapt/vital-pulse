<?php

namespace App\Controller;

use App\Entity\HealthLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[Route('/api/v1/logs', name: 'api_logs_')]
class HealthApiController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route(methods: ['POST'])]
    public function createLog(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (null === $data || !is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        // Default emoji to neutral
        $emoji = $data['emoji'] ?? '😐';

        // Timestamp defaults to now if not provided
        if (isset($data['timestamp'])) {
            try {
                $timestamp = new \DateTimeImmutable($data['timestamp'], new \DateTimeZone('UTC'));
            } catch (\Exception) {
                return new JsonResponse(['error' => 'Invalid timestamp format. Use ISO 8601 or a recognized date string.'], 400);
            }
        } else {
            $timestamp = null; // Will default to now in constructor
        }

        $log = new HealthLog($timestamp);
        $log->setEmoji($emoji);

        if (isset($data['systolic'])) {
            $log->setSystolic((int) $data['systolic']);
        }
        if (isset($data['diastolic'])) {
            $log->setDiastolic((int) $data['diastolic']);
        }
        if (isset($data['heart_rate'])) {
            $log->setHeartRate((int) $data['heart_rate']);
        }
        if (isset($data['weight'])) {
            $log->setWeight((float) $data['weight']);
        }

        // Enforce: at least one measurement field must be present
        if (!$log->hasMeasurements()) {
            return new JsonResponse(['error' => 'At least one measurement is required (systolic, diastolic, heart_rate, or weight).'], 400);
        }

        // Basic consistency: if systolic provided without diastolic (or vice versa), prompt user to include both
        $sys = $log->getSystolic();
        $dia = $log->getDiastolic();

        if (($sys !== null && $dia === null) || ($sys === null && $dia !== null)) {
            return new JsonResponse(['error' => 'If providing blood pressure, both systolic and diastolic values must be set.'], 400);
        }

        // Validate using Symfony validator with both Default and health_check groups
        $violations = $this->validator->validate($log, null, ['Default', 'health_check']);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }
            return new JsonResponse(['error' => implode(', ', $errors)], 400);
        }

        // Persist
        try {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception) {
            // Log the full exception internally; return a generic message to avoid leaking internals
            return new JsonResponse(['error' => 'Failed to save log entry'], 500);
        }

        return new JsonResponse($this->serializeLog($log), 201);
    }

    #[Route(methods: ['GET'])]
    public function listLogs(Request $request): JsonResponse
    {
        try {
            $dateFrom = $this->parseDate($request->query->get('from'));
            $dateTo = $this->parseDate($request->query->get('to'));
            $emoji = $request->query->all()['emoji'] ?? [];
            if (!is_array($emoji)) {
                $emoji = [$emoji];
            }

            if ($dateTo !== null && $dateFrom !== null && $dateFrom > $dateTo) {
                return new JsonResponse(['error' => 'Invalid date range: "from" must be before or equal to "to".'], 400);
            }

            $logs = $this->entityManager->getRepository(HealthLog::class)->findByDateRange($dateFrom, $dateTo, $emoji);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD or ISO 8601 string.'], 400);
        }

        return new JsonResponse(array_map(fn (HealthLog $log) => $this->serializeLog($log), $logs));
    }

    /**
     * Serialize a HealthLog entity to a JSON-ready array.
     */
    private function serializeLog(HealthLog $log): array
    {
        return [
            'id' => $log->getId(),
            'timestamp' => $log->getTimestamp()->format('c'),
            'systolic' => $log->getSystolic(),
            'diastolic' => $log->getDiastolic(),
            'heart_rate' => $log->getHeartRate(),
            'weight' => $log->getWeight(),
            'emoji' => $log->getEmoji(),
        ];
    }

    private function parseDate(?string $dateString): ?\DateTimeImmutable
    {
        if ($dateString === null || trim($dateString) === '') {
            return null;
        }

        try {
            // Try ISO 8601 / DateTime constructor first
            return new \DateTimeImmutable(trim($dateString), new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid date format: ' . $dateString);
        }
    }
}
