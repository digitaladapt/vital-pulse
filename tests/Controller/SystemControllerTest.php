<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\SchemaSetupTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SystemControllerTest extends WebTestCase
{
    use SchemaSetupTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->setUpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownSchema();
        parent::tearDown();
    }

    // ── /api/about ───────────────────────────────────────────────

    public function testAboutEndpointReturns200(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        self::assertResponseStatusCodeSame(200);
    }

    public function testAboutEndpointReturnsApplicationName(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame('vital-pulse', $data['name']);
    }

    public function testAboutEndpointReturnsVersionString(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('version', $data);
        self::assertIsString($data['version']);
        self::assertNotSame('', $data['version']);
    }

    public function testAboutEndpointAccessibleWithoutApiKey(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        // /api/about is not under the api_logs_* guard, so it must be
        // publicly accessible (used by the dashboard health indicator).
        self::assertResponseStatusCodeSame(200);
    }

    public function testAboutEndpointReturnsJsonContentType(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testAboutEndpointResponseHasExactlyTwoKeys(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(['name', 'version'], array_keys($data));
    }

    public function testAboutEndpointRouteOnlyAllowsGet(): void
    {
        // The route is registered as GET-only. A POST falls through to the
        // catch-all asset controller which returns 404 — not a 405, but
        // certainly not a 200 either. The key assertion is that POST is
        // not accepted as a valid method.
        $client = $this->client;
        $client->request('POST', '/api/about');

        self::assertResponseStatusCodeSame(404);
    }

    // ── /api/health ──────────────────────────────────────────────

    public function testHealthEndpointReturns200WithHealthyStatus(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('healthy', $data['status']);
    }

    public function testHealthEndpointReturnsJsonContentType(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testHealthEndpointAccessibleWithoutApiKey(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        // /api/health is used by Docker HEALTHCHECK — must not require auth.
        self::assertResponseStatusCodeSame(200);
    }

    public function testHealthEndpointReturnsOnlyStatusKeyWhenHealthy(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(['status'], array_keys($data));
    }

    public function testHealthEndpointRouteOnlyAllowsGet(): void
    {
        // Same as /api/about — GET-only route, POST falls through to the
        // catch-all asset controller and returns 404.
        $client = $this->client;
        $client->request('POST', '/api/health');

        self::assertResponseStatusCodeSame(404);
    }

    public function testHealthEndpointReturns503WhenDatabaseIsUnavailable(): void
    {
        // Test the controller directly with a mocked EntityManager that
        // throws on query execution, simulating a corrupted/locked/missing
        // database. We can't override the container's EM because it's
        // already initialized from schema setup.
        $mockEm = $this->createMock(EntityManagerInterface::class);
        $mockConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $mockConnection->method('executeQuery')->willThrowException(new \Exception('Database is down'));
        $mockEm->method('getConnection')->willReturn($mockConnection);

        $controller = new \App\Controller\SystemController($mockEm);
        $response = $controller->health();

        self::assertSame(503, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('unhealthy', $data['status']);
        self::assertSame('Database connection failed', $data['error']);
    }
}
