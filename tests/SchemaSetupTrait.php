<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\HealthLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Provides shared schema setup/teardown for tests that need a SQLite
 * database schema for HealthLog entities.
 *
 * Usage:
 *   class MyTest extends WebTestCase  // or KernelTestCase
 *   {
 *       use SchemaSetupTrait;
 *
 *       protected function setUp(): void
 *       {
 *           parent::setUp();
 *           $this->client = static::createClient(); // if WebTestCase
 *           $this->setUpSchema();
 *       }
 *
 *       protected function tearDown(): void
 *       {
 *           $this->tearDownSchema();
 *           parent::tearDown();
 *       }
 *   }
 */
trait SchemaSetupTrait
{
    protected EntityManagerInterface $em;

    protected function setUpSchema(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->em);
        $metadataFactory = $this->em->getMetadataFactory();
        $classes = [];
        foreach ($metadataFactory->getAllMetadata() as $class) {
            if ($class->getName() === HealthLog::class) {
                $classes[] = $class;
            }
        }

        // Drop existing schema if present (for re-entrant safety)
        if ($schemaTool->getSchemaFromMetadata($classes)->getTables()) {
            $schemaTool->dropDatabase($classes);
        }

        $schemaTool->createSchema($classes);
    }

    protected function tearDownSchema(): void
    {
        foreach ($this->em->getConnection()->createSchemaManager()->listTableNames() as $table) {
            $this->em->getConnection()->executeStatement("DELETE FROM {$table}");
        }
    }
}
