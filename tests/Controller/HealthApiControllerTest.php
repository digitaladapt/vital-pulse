<?php

namespace App\Tests\Controller;

use App\Entity\HealthLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthApiControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private const API_KEY = 'test_api_key_12345'; // matches .env.test

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Ensure schema is created for in-memory SQLite
        $schemaTool = new SchemaTool($this->em);
        $metadataFactory = $this->em->getMetadataFactory();
        $classes = [];
        foreach ($metadataFactory->getAllMetadata() as $class) {
            // Only include HealthLog to keep schema minimal and fast
            if ($class->getName() === HealthLog::class) {
                $classes[] = $class;
            }
        }
        $schemaTool->createSchema($classes);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clear the in-memory database for each test
        foreach ($this->em->getConnection()->createSchemaManager()->listTableNames() as $table) {
            $this->em->getConnection()->executeStatement("DELETE FROM {$table}");
        }
    }

    public function testPostLogMissingApiKeyReturns401(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/v1/logs', [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode(['systolic' => 120]));

        self::assertResponseStatusCodeSame(401);
    }

    public function testPostLogInvalidApiKeyReturns401(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => 'wrong_key',
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode(['systolic' => 120]));

        self::assertResponseStatusCodeSame(401);
    }

    public function testPostLogWithEmptyBodyReturns400(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], '');

        self::assertResponseStatusCodeSame(400);
    }

    public function testPostLogWithOnlyEmojiReturns400(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode(['emoji' => '😀']));

        self::assertResponseStatusCodeSame(400);
    }

    public function testPostLogSystolicOnlyReturns400(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode(['systolic' => 120]));

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertStringContainsString('both systolic and diastolic', strtolower((string) ($data['error'] ?? '')));
    }

    public function testPostLogMinimalValidEntrySucceeds(): void
    {
        $client = $this->client;
        $payload = ['heart_rate' => 72];
        $client->request('POST', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals(72, $data['heart_rate']);
        self::assertEquals('😐', $data['emoji']); // default emoji
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('timestamp', $data);
    }

    public function testPostLogFullEntrySucceeds(): void
    {
        $client = $this->client;
        $payload = [
            'systolic' => 128,
            'diastolic' => 84,
            'heart_rate' => 76,
            'weight' => 185.4,
            'emoji' => '🙂',
        ];
        $client->request('POST', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals(128, $data['systolic']);
        self::assertEquals(84, $data['diastolic']);
        self::assertEquals(76, $data['heart_rate']);
        self::assertEquals(185.4, $data['weight'], 0.01);
        self::assertEquals('🙂', $data['emoji']);
    }

    public function testPostLogWithCustomTimestampSucceeds(): void
    {
        $client = $this->client;
        $payload = [
            'heart_rate' => 65,
            'timestamp' => '2025-03-15T08:30:00Z',
        ];
        $client->request('POST', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        // format('c') outputs '+00:00' instead of 'Z'; compare as DateTimeImmutable
        $ts = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['timestamp']);
        self::assertNotNull($ts);
        self::assertEquals(new \DateTimeImmutable('2025-03-15T08:30:00Z'), $ts);
    }

    public function testGetLogsReturnsEmptyArrayWhenNoData(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertCount(0, $data);
    }

    public function testGetLogsReturnsCreatedEntries(): void
    {
        // Create some logs first
        $this->em->persist(new HealthLog());
        $log1 = new HealthLog();
        $log1->setSystolic(120)->setDiastolic(80)->setEmoji('😀');
        $this->em->persist($log1);
        $log2 = new HealthLog();
        $log2->setHeartRate(75)->setWeight(190.0)->setEmoji('🙂');
        $this->em->persist($log2);
        $this->em->flush();

        $client = $this->client;
        $client->request('GET', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(3, $data); // includes the one we persisted without measurements for schema test; should still appear
    }

    public function testGetLogsFilteredByDateRange(): void
    {
        // Insert logs with specific timestamps
        $old = new HealthLog(new \DateTimeImmutable('2025-01-01T10:00:00Z'));
        $old->setHeartRate(70);
        $this->em->persist($old);

        $new = new HealthLog(new \DateTimeImmutable('2025-06-01T14:00:00Z'));
        $new->setHeartRate(80);
        $this->em->persist($new);

        $this->em->flush();

        // Query only June onwards
        $client = $this->client;
        $client->request('GET', '/api/v1/logs?from=2025-06-01', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $data);
        self::assertEquals(80, $data[0]['heart_rate']);
    }

    public function testGetLogsFilteredByEmoji(): void
    {
        $log1 = new HealthLog();
        $log1->setHeartRate(70)->setEmoji('😀');
        $this->em->persist($log1);

        $log2 = new HealthLog();
        $log2->setHeartRate(80)->setEmoji('🙂');
        $this->em->persist($log2);

        $log3 = new HealthLog();
        $log3->setHeartRate(90)->setEmoji('😀');
        $this->em->persist($log3);

        $this->em->flush();

        $client = $this->client;
        $emojiEncoded = rawurlencode('😀');
        $client->request("GET", "/api/v1/logs?emoji={$emojiEncoded}", [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $data); // only the 😀 ones
    }

    public function testGetLogsOrderedByTimestampDescending(): void
    {
        $old = new HealthLog(new \DateTimeImmutable('2025-01-01T00:00:00Z'));
        $old->setHeartRate(70);
        $this->em->persist($old);

        $newer = new HealthLog(new \DateTimeImmutable('2025-06-01T00:00:00Z'));
        $newer->setHeartRate(80);
        $this->em->persist($newer);

        $this->em->flush();

        $client = $this->client;
        $client->request('GET', '/api/v1/logs', [], [], [
            'HTTP_X-API-KEY' => self::API_KEY,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $data);
        // First result should be the newer one
        self::assertEquals('2025-06-01T00:00:00+00:00', $data[0]['timestamp']);
    }

    public function testApiKeyViaQueryParamAlsoWorks(): void
    {
        $client = $this->client;
        $payload = ['heart_rate' => 68];
        $client->request('POST', '/api/v1/logs?api_key=' . self::API_KEY, [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        self::assertResponseStatusCodeSame(201);
    }
}
