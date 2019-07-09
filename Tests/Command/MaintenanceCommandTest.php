<?php

namespace MauticPlugin\MauticContactSourceBundle\Tests\Command;

use DateTime;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\MauticContactSourceBundle\Entity\Cache;
use MauticPlugin\MauticContactSourceBundle\Entity\CacheRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class MaintenanceCommandTest extends MauticMysqlTestCase
{
    /** @test */
    public function it_deletes_old_caches_from_one_month_and_one_day_ago_by_default()
    {
        $expiredCaches = [];
        $expiredCount  = 42;
        for ($i = 0; $i < $expiredCount; ++$i) {
            $cache = $this->createCache(new DateTime('-2 months'));
            $this->em->persist($cache);
        }
        $this->em->flush();

        /** @var CacheRepository $repo */
        $repo = $this->em->getRepository(Cache::class);
        $q    = $repo->createQueryBuilder('c')
                    ->select('COUNT(c.id)')->getQuery();
        $this->assertEquals($expiredCount, $q->getSingleScalarResult());

        $freshCaches = [];

        $cmd    =  (new Application(static::$kernel))->find('mautic:contactsource:maintenance');
        $tester = new CommandTester($cmd);
        $tester->execute([
            'command'   => $cmd->getName(),
        ]);

        $output = $tester->getDisplay();

        $this->assertContains("Deleted {$expiredCount} expired cache entries", $output);
        $this->assertEquals(0, $q->getSingleScalarResult());
    }

    /**
     * Helper function to create a Cache entity.
     *
     * @param DateFrom $date
     *
     * @return Cache
     */
    private function createCache(DateTime $date)
    {
        $cache = new Cache();
        $cache->setContactSource(1);
        $cache->setContact(1);
        $cache->setDateAdded($date);

        return $cache;
    }

    /**
     * Get the current count of Cache entities.
     *
     * @return int
     */
    private function getCacheCount()
    {
        /** @var CacheRepository $repo */
        $repo  = $this->em->getRepository(Cache::class);
        $count = $repo->createQueryBuilder('c')
                   ->select('COUNT(c.id)')
                   ->getQuery()
                   ->getSingleScalarResult();

        return $count;
    }
}
