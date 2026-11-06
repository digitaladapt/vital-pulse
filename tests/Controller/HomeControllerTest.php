<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function test_get_homepage_renders_dashboard(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(200);
        self::assertStringStartsWith('text/html', $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('VitalPulse Health Tracker', (string) $client->getResponse()->getContent());
    }

    public function test_get_homepage_links_stylesheet(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        // The stylesheet is a plain static file in public/ served by the web
        // server — the page only needs to reference it.
        self::assertStringContainsString('/app.css', (string) $client->getResponse()->getContent());
    }

    public function test_get_unknown_path_returns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/does-not-exist.txt');

        // No catch-all route any more: unknown paths are Symfony's ordinary
        // 404, and static files are served by the web server, not PHP.
        self::assertResponseStatusCodeSame(404);
    }
}
