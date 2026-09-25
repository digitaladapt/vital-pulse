<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\SchemaSetupTrait;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SystemControllerTest extends WebTestCase
{
    use SchemaSetupTrait;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->setUpSchema();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownSchema();
        parent::tearDown();
    }

    // ── /api/about ───────────────────────────────────────────────

    public function test_about_endpoint_returns200(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_about_endpoint_returns_application_name(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame('vital-pulse', $data['name']);
    }

    public function test_about_endpoint_returns_version_string(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('version', $data);
        self::assertIsString($data['version']);
        self::assertNotSame('', $data['version']);
    }

    public function test_about_endpoint_returns_reading_warnings_enabled_flag(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('reading_warnings_enabled', $data);
        self::assertIsBool($data['reading_warnings_enabled']);
    }

    public function test_about_endpoint_reflects_reading_warnings_setting(): void
    {
        // Construct the controller directly (like the health test below) to
        // verify the flag is passed straight through to the response.
        $mockEm = $this->createMock(EntityManagerInterface::class);

        $controller = new \App\Controller\SystemController($mockEm, true);
        $controller->setContainer(static::getContainer());
        $data = json_decode($controller->about()->getContent(), true);
        self::assertTrue($data['reading_warnings_enabled']);

        $controller = new \App\Controller\SystemController($mockEm, false);
        $controller->setContainer(static::getContainer());
        $data = json_decode($controller->about()->getContent(), true);
        self::assertFalse($data['reading_warnings_enabled']);
    }

    public function test_about_endpoint_accessible_without_api_key(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        // /api/about is not under the api_logs_* guard, so it must be
        // publicly accessible (used by the dashboard health indicator).
        self::assertResponseStatusCodeSame(200);
    }

    public function test_about_endpoint_returns_json_content_type(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function test_about_endpoint_response_has_exactly_three_keys(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(['name', 'version', 'reading_warnings_enabled'], array_keys($data));
    }

    public function test_about_endpoint_route_only_allows_get(): void
    {
        // The route is registered as GET-only. A POST falls through to the
        // catch-all asset controller which returns 404 — not a 405, but
        // certainly not a 200 either. The key assertion is that POST is
        // not accepted as a valid method.
        $client = $this->client;
        $client->request('POST', '/api/about');

        self::assertResponseStatusCodeSame(404);
    }

    // ── /health (liveness) and /ready (readiness) ────────────────
    //
    // GUIDING-LIGHT §8.4 splits the two probes. The distinction is the whole
    // point, so it is asserted directly: /health must stay green while the
    // database is broken (a restart cannot fix a locked SQLite file, so
    // reporting unhealthy there gets the container killed for no reason),
    // and /ready must go red at the same moment.

    public function test_liveness_returns200_without_touching_database(): void
    {
        $client = $this->client;
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('healthy', $data['status']);
    }

    public function test_liveness_returns_only_status_key(): void
    {
        $client = $this->client;
        $client->request('GET', '/health');

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(['status'], array_keys($data));
    }

    public function test_liveness_accessible_without_api_key(): void
    {
        $client = $this->client;
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_liveness_is_green_even_when_database_is_unavailable(): void
    {
        // The entity manager is mocked to throw, exactly as in the readiness
        // test below — but liveness must not care, because it never asks.
        $mockEm = $this->createMock(EntityManagerInterface::class);
        $mockConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $mockConnection->method('executeQuery')->willThrowException(new Exception('Database is down'));
        $mockEm->method('getConnection')->willReturn($mockConnection);

        $controller = new \App\Controller\SystemController($mockEm, true);

        self::assertSame(200, $controller->health()->getStatusCode());
    }

    public function test_readiness_returns200_with_ready_status(): void
    {
        $client = $this->client;
        $client->request('GET', '/ready');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ready', $data['status']);
    }

    public function test_readiness_returns503_when_database_is_unavailable(): void
    {
        $mockEm = $this->createMock(EntityManagerInterface::class);
        $mockConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $mockConnection->method('executeQuery')->willThrowException(new Exception('Database is down'));
        $mockEm->method('getConnection')->willReturn($mockConnection);

        $controller = new \App\Controller\SystemController($mockEm, true);
        $response = $controller->ready();

        self::assertSame(503, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('unready', $data['status']);
        self::assertSame('Database connection failed', $data['error']);
    }

    public function test_readiness_accessible_without_api_key(): void
    {
        $client = $this->client;
        $client->request('GET', '/ready');

        self::assertResponseStatusCodeSame(200);
    }

    // ── /api/health (deprecated alias for /ready) ────────────────

    public function test_health_endpoint_returns200_with_healthy_status(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('healthy', $data['status']);
    }

    public function test_health_endpoint_returns_json_content_type(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function test_health_endpoint_accessible_without_api_key(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        // /api/health is used by Docker HEALTHCHECK — must not require auth.
        self::assertResponseStatusCodeSame(200);
    }

    public function test_health_endpoint_returns_only_status_key_when_healthy(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(['status'], array_keys($data));
    }

    public function test_health_endpoint_route_only_allows_get(): void
    {
        // Same as /api/about — GET-only route, POST falls through to the
        // catch-all asset controller and returns 404.
        $client = $this->client;
        $client->request('POST', '/api/health');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_deprecated_api_health_alias_still_reports_database_failure(): void
    {
        // /api/health is retained as a deprecated alias so deployments whose
        // HEALTHCHECK still points at it do not break mid-upgrade. It keeps the
        // dependency check, which is what it always did — the new liveness
        // endpoint that deliberately does NOT touch the database is /health,
        // and it is tested above.
        //
        // The controller is constructed directly with a mocked EntityManager
        // that throws on query execution, simulating a corrupted/locked/missing
        // database. The container's own EM cannot be overridden because it is
        // already initialized from schema setup.
        $mockEm = $this->createMock(EntityManagerInterface::class);
        $mockConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $mockConnection->method('executeQuery')->willThrowException(new Exception('Database is down'));
        $mockEm->method('getConnection')->willReturn($mockConnection);

        $controller = new \App\Controller\SystemController($mockEm, true);
        $response = $controller->healthDeprecated();

        self::assertSame(503, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame('unhealthy', $data['status']);
        self::assertSame('Database connection failed', $data['error']);
    }
}
