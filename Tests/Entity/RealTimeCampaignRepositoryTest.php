<?php

namespace MauticPlugin\MauticContactSourceBundle\Tests\Entity;

use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;
use Mautic\CoreBundle\Test\AbstractMauticTestCase;
use MauticPlugin\MauticContactSourceBundle\Entity\RealTimeCampaignRepository;

class RealTimeCampaignRepositoryTest extends AbstractMauticTestCase
{
    // Having Datbase connection issues in test config,

    /** @test */
    /* public function it_doesnt_use_real_time_if_contact_is_not_defined() */
    /* { */
    /*     $this->applyMigrations(); */
    /*     $repo    = new RealTimeCampaignRepository($this->em); */

    /*     $contactIds = [1, 2, 3, 4, 5]; */
    /*     $limiter    = new ContactLimiter(1, null, null, null, $contactIds, null, null, 5); */

    /*     $ids     = $repo->getPendingContactIds(1, $limiter); */
    /*     dump($ids); */
    /* } */

    /** @test */
    public function it_pulls_contact_ids_by_batches()
    {
        define('MAUTIC_PLUGIN_CONTACT_SOURCE_REALTIME', true);
        $repo    = new RealTimeCampaignRepository($this->em);

        $contactIds = [1, 2, 3, 4, 5];
        $limiter    = new ContactLimiter(1, null, null, null, $contactIds, null, null, 5);

        $ids     = $repo->getPendingContactIds(1, $limiter);
        $this->assertEquals($contactIds, $ids);

        $ids     = $repo->getPendingContactIds(1, $limiter);
        $this->assertEmpty($ids);
    }
}
