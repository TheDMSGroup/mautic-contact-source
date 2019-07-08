<?php

namespace MauticPlugin\MauticContactSourceBundle\Tests\Command;

use DateTime;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\MauticContactSourceBundle\Entity\Cache;
use MauticPlugin\MauticContactSourceBundle\Entity\CacheRepository;

class MaintenanceCommandTest extends MauticMysqlTestCase
{
    /** @test */
    public function it_deletes_old_caches_from_one_month_and_one_day_ago_by_default()
    {
        $expiredCaches = [];
        for ($i = 0; $i < 20; ++$i) {
            $cache = $this->createCache(new DateTime('-2 months'));
            $this->em->persist($cache);
        }
        $this->em->flush();
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
        $repo = $this->em->getRepository(Cache::class);
        $count = $repo->createQueryBuilder('c')
                   ->select('COUNT(c.id)')
                   ->getQuery()
                   ->getSingleScalarResult();

        return $count;
    }
}
