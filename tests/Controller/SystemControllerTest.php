<?php

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

    public function testAboutEndpointReturns200(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/about');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('vital-pulse', $data['name']);
        self::assertArrayHasKey('version', $data);
    }

    public function testHealthEndpointReturns200WithHealthyStatus(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/health');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('healthy', $data['status']);
    }
}
