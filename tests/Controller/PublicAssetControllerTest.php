<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicAssetControllerTest extends WebTestCase
{
    public function testGetHomepageServesIndexHtml(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(200);
        // BinaryFileResponse streams content — getContent() is empty in tests,
        // so we assert on status and a header that proves it's HTML.
        self::assertStringStartsWith('text/html', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testGetAssetServesJsFile(): void
    {
        $client = static::createClient();
        $client->request('GET', '/app.js');

        self::assertResponseStatusCodeSame(200);
        self::assertEquals('application/javascript', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testGetAssetServesSvgFile(): void
    {
        $client = static::createClient();
        $client->request('GET', '/favicon.svg');

        self::assertResponseStatusCodeSame(200);
        self::assertEquals('image/svg+xml', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testGetPhpFileReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/index.php');

        // PHP files must not be served by the asset controller — should fall through to 404
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetNonExistentAssetReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/does-not-exist.txt');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDirectoryTraversalBlocked(): void
    {
        $client = static::createClient();
        $client->request('GET', '/../config/services.yaml');

        self::assertResponseStatusCodeSame(404);
    }
}
