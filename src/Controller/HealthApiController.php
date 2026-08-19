<?php

declare(strict_types=1);

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
    private const EMOJI_PATTERN = '/^[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F900}-\x{1F9FF}\x{2700}-\x{27BF}\x{FE0F}\x{200D}]+$/u';
    private const MAX_PAGE_SIZE = 200;
    private const DEFAULT_PAGE_SIZE = 50;

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

        // Validate emoji length
        if (mb_strlen((string) $emoji) > 10) {
            return new JsonResponse(['error' => 'Emoji must be 10 characters or fewer.'], 400);
        }

        // Validate emoji is actually an emoji (not arbitrary text)
        // Empty string is allowed — setEmoji() falls back to the default
        if ($emoji !== '' && !preg_match(self::EMOJI_PATTERN, (string) $emoji)) {
            return new JsonResponse(['error' => 'Emoji must be a valid emoji character.'], 400);
        }

        // Timestamp defaults to now if not provided
        if (isset($data['timestamp'])) {
            try {
                $timestamp = new \DateTimeImmutable($data['timestamp'], new \DateTimeZone('UTC'));
            } catch (\Exception) {
                return new JsonResponse(['error' => 'Invalid timestamp format. Use ISO 8601 or a recognized date string.'], 400);
            }

            // Reject future timestamps (with 5-minute tolerance for clock skew)
            $now = new \DateTimeImmutable('UTC');
            if ($timestamp > $now->modify('+5 minutes')) {
                return new JsonResponse(['error' => 'Timestamp cannot be in the future.'], 400);
            }
        } else {
            $timestamp = null; // Will default to now in constructor
        }

        $log = new HealthLog($timestamp);
        $log->setEmoji($emoji);

        // Safely coerce input values — reject non-numeric input instead of silently casting to 0
        $coercionErrors = [];

        if (isset($data['systolic'])) {
            $val = filter_var($data['systolic'], FILTER_VALIDATE_INT);
            if ($val === false) {
                $coercionErrors['systolic'] = ['Systolic must be a valid integer.'];
            } else {
                $log->setSystolic($val);
            }
        }
        if (isset($data['diastolic'])) {
            $val = filter_var($data['diastolic'], FILTER_VALIDATE_INT);
            if ($val === false) {
                $coercionErrors['diastolic'] = ['Diastolic must be a valid integer.'];
            } else {
                $log->setDiastolic($val);
            }
        }
        if (isset($data['heart_rate'])) {
            $val = filter_var($data['heart_rate'], FILTER_VALIDATE_INT);
            if ($val === false) {
                $coercionErrors['heart_rate'] = ['Heart rate must be a valid integer.'];
            } else {
                $log->setHeartRate($val);
            }
        }
        if (isset($data['weight'])) {
            $val = filter_var($data['weight'], FILTER_VALIDATE_FLOAT);
            if ($val === false) {
                $coercionErrors['weight'] = ['Weight must be a valid number.'];
            } else {
                $log->setWeight($val);
            }
        }

        if (!empty($coercionErrors)) {
            return new JsonResponse(['error' => 'Validation failed', 'details' => $coercionErrors], 400);
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
            $details = [];
            foreach ($violations as $violation) {
                $field = $violation->getPropertyPath();
                $details[$field][] = $violation->getMessage();
            }
            return new JsonResponse(['error' => 'Validation failed', 'details' => $details], 400);
        }

        // Persist
        try {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception) {
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

            // Pagination
            $page = max(1, (int) ($request->query->get('page', 1)));
            $limit = min(self::MAX_PAGE_SIZE, max(1, (int) ($request->query->get('limit', self::DEFAULT_PAGE_SIZE))));
            $offset = ($page - 1) * $limit;

            $repo = $this->entityManager->getRepository(HealthLog::class);
            $logs = $repo->findByDateRange($dateFrom, $dateTo, $emoji, $limit, $offset);
            $total = $repo->countByDateRange($dateFrom, $dateTo, $emoji);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD or ISO 8601 string.'], 400);
        }

        $pages = (int) ceil($total / $limit);

        return new JsonResponse([
            'data' => array_map(fn (HealthLog $log) => $this->serializeLog($log), $logs),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ]);
    }

    #[Route('/stats', methods: ['GET'])]
    public function getStats(Request $request): JsonResponse
    {
        try {
            $dateFrom = $this->parseDate($request->query->get('from'));
            $dateTo = $this->parseDate($request->query->get('to'));

            if ($dateTo !== null && $dateFrom !== null && $dateFrom > $dateTo) {
                return new JsonResponse(['error' => 'Invalid date range: "from" must be before or equal to "to".'], 400);
            }
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Invalid date format. Use YYYY-MM-DD or ISO 8601 string.'], 400);
        }

        $repo = $this->entityManager->getRepository(HealthLog::class);
        $stats = $repo->getStatsForDateRange($dateFrom, $dateTo);
        $count = $repo->countByDateRange($dateFrom, $dateTo);

        $result = [
            'count' => $count,
            'systolic' => $this->formatMetricStats($stats, 'Systolic'),
            'diastolic' => $this->formatMetricStats($stats, 'Diastolic'),
            'heart_rate' => $this->formatMetricStats($stats, 'HeartRate'),
            'weight' => $this->formatMetricStats($stats, 'Weight'),
        ];

        return new JsonResponse($result);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function getLog(int $id): JsonResponse
    {
        $log = $this->entityManager->getRepository(HealthLog::class)->find($id);

        if (!$log) {
            return new JsonResponse(['error' => 'Log entry not found'], 404);
        }

        return new JsonResponse($this->serializeLog($log));
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function updateLog(int $id, Request $request): JsonResponse
    {
        $log = $this->entityManager->getRepository(HealthLog::class)->find($id);

        if (!$log) {
            return new JsonResponse(['error' => 'Log entry not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (null === $data || !is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $coercionErrors = [];

        if (array_key_exists('systolic', $data)) {
            if ($data['systolic'] === null) {
                $log->setSystolic(null);
            } else {
                $val = filter_var($data['systolic'], FILTER_VALIDATE_INT);
                if ($val === false) {
                    $coercionErrors['systolic'] = ['Systolic must be a valid integer.'];
                } else {
                    $log->setSystolic($val);
                }
            }
        }
        if (array_key_exists('diastolic', $data)) {
            if ($data['diastolic'] === null) {
                $log->setDiastolic(null);
            } else {
                $val = filter_var($data['diastolic'], FILTER_VALIDATE_INT);
                if ($val === false) {
                    $coercionErrors['diastolic'] = ['Diastolic must be a valid integer.'];
                } else {
                    $log->setDiastolic($val);
                }
            }
        }
        if (array_key_exists('heart_rate', $data)) {
            if ($data['heart_rate'] === null) {
                $log->setHeartRate(null);
            } else {
                $val = filter_var($data['heart_rate'], FILTER_VALIDATE_INT);
                if ($val === false) {
                    $coercionErrors['heart_rate'] = ['Heart rate must be a valid integer.'];
                } else {
                    $log->setHeartRate($val);
                }
            }
        }
        if (array_key_exists('weight', $data)) {
            if ($data['weight'] === null) {
                $log->setWeight(null);
            } else {
                $val = filter_var($data['weight'], FILTER_VALIDATE_FLOAT);
                if ($val === false) {
                    $coercionErrors['weight'] = ['Weight must be a valid number.'];
                } else {
                    $log->setWeight($val);
                }
            }
        }
        if (array_key_exists('emoji', $data)) {
            $emoji = $data['emoji'] ?? '😐';
            if (mb_strlen((string) $emoji) > 10) {
                $coercionErrors['emoji'] = ['Emoji must be 10 characters or fewer.'];
            } elseif ($emoji !== '' && !preg_match(self::EMOJI_PATTERN, (string) $emoji)) {
                $coercionErrors['emoji'] = ['Emoji must be a valid emoji character.'];
            } else {
                $log->setEmoji($emoji);
            }
        }
        if (array_key_exists('timestamp', $data)) {
            try {
                $timestamp = new \DateTimeImmutable($data['timestamp'], new \DateTimeZone('UTC'));
                $now = new \DateTimeImmutable('UTC');
                if ($timestamp > $now->modify('+5 minutes')) {
                    $coercionErrors['timestamp'] = ['Timestamp cannot be in the future.'];
                } else {
                    $log->setTimestamp($timestamp);
                }
            } catch (\Exception) {
                $coercionErrors['timestamp'] = ['Invalid timestamp format. Use ISO 8601.'];
            }
        }

        if (!empty($coercionErrors)) {
            return new JsonResponse(['error' => 'Validation failed', 'details' => $coercionErrors], 400);
        }

        // BP consistency check
        $sys = $log->getSystolic();
        $dia = $log->getDiastolic();
        if (($sys !== null && $dia === null) || ($sys === null && $dia !== null)) {
            return new JsonResponse(['error' => 'If providing blood pressure, both systolic and diastolic values must be set.'], 400);
        }

        $violations = $this->validator->validate($log, null, ['Default', 'health_check']);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $violation) {
                $field = $violation->getPropertyPath();
                $details[$field][] = $violation->getMessage();
            }
            return new JsonResponse(['error' => 'Validation failed', 'details' => $details], 400);
        }

        try {
            $this->entityManager->flush();
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Failed to update log entry'], 500);
        }

        return new JsonResponse($this->serializeLog($log));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function deleteLog(int $id): JsonResponse
    {
        $log = $this->entityManager->getRepository(HealthLog::class)->find($id);

        if (!$log) {
            return new JsonResponse(['error' => 'Log entry not found'], 404);
        }

        try {
            $this->entityManager->remove($log);
            $this->entityManager->flush();
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Failed to delete log entry'], 500);
        }

        return new JsonResponse(null, 204);
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

    /**
     * Format aggregate stats for a single metric from the repository result.
     *
     * @param array  $stats   Raw repository result from getStatsForDateRange()
     * @param string $metric  Metric name in camelCase (e.g. 'Systolic', 'HeartRate')
     */
    private function formatMetricStats(array $stats, string $metric): array
    {
        $avg = $stats['avg' . $metric] ?? null;
        $min = $stats['min' . $metric] ?? null;
        $max = $stats['max' . $metric] ?? null;

        $isFloat = $metric === 'Weight';

        return [
            'avg' => $avg !== null ? round((float) $avg, 2) : null,
            'min' => $min !== null ? ($isFloat ? round((float) $min, 2) : (int) $min) : null,
            'max' => $max !== null ? ($isFloat ? round((float) $max, 2) : (int) $max) : null,
        ];
    }

    private function parseDate(?string $dateString): ?\DateTimeImmutable
    {
        if ($dateString === null || trim($dateString) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable(trim($dateString), new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid date format: ' . $dateString);
        }
    }
}
