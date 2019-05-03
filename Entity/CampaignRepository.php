<?php

namespace MauticPlugin\MauticContactSourceBundle\Entity;

use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;

class FakeCampaignRepository extends CampaignRepository
{
    /**
     * Get pending contact IDs for a campaign through ContactLimiter, skipping
     * an unnecesarry query when doing real time lead processing.
     *
     * @param                $campaignId
     * @param ContactLimiter $limiter
     *
     * @return array
     */
    public function getPendingContactIds($campaignId, ContactLimiter $limiter)
    { 
        // Honor the parent class...
        if ($limiter->hasCampaignLimit() && 0 === $limiter->getCampaignLimitRemaining()) {
            return [];
        }

        $contacts = $limiter->getContactIdList();

        if ($limiter->hasCampaignLimit() && $limiter->getCampaignLimitRemaining() < $limiter->getBatchLimit()) { 
            $pulled = [];
            //TODO: fix this
            for($i = count($contacts); $i >= $limiter->getCampaignLimitRemaining(); $i-- {
                unset($contacts[$i]); 
            }
        }

        if ($limiter->hasCampaignLimit()) {
            $limiter->reduceCampaignLimitRemaining(count($contacts));
        }

        return $contacts;
    }
}
