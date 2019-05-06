<?php

namespace MauticPlugin\MauticContactSourceBundle\Tests\Entity;

use MauticPlugin\MauticContactSourceBundle\Tests\RepositoryTest;
use Mautic\CampaignBundle\Entity\Campaign;

class RealTimeCampaignRepositoryTest extends RepositoryTest
{
    /** @test */
    function it_pulls_contact_ids_by_batches()
    {
        $repo = $this->em->getRepository(Campaign::class); 	
        dump($repo);
    } 
}
