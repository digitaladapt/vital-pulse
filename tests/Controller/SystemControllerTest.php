<?php

namespace App\Tests\Controller;

use App\Entity\HealthLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SystemControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

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
            if ($class->getName() === HealthLog::class) {
                $classes[] = $class;
            }
        }
        $schemaTool->createSchema($classes);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->em->getConnection()->createSchemaManager()->listTableNames() as $table) {
            $this->em->getConnection()->executeStatement("DELETE FROM {$table}");
        }
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
