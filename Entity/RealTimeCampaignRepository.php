<?php

namespace MauticPlugin\MauticContactSourceBundle\Entity;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;

class RealTimeCampaignRepository extends CampaignRepository
{
    /**
     * Constant to be defined to true, when you want to use RealTimeCampaignRepository.
     */
    const MAUTIC_PLUGIN_CONTACT_SOURCE_REALTIME = 'MAUTIC_PLUGIN_CONTACT_SOURCE_REALTIME';

    /**
     * @var bool
     */
    private $finished = false;

    /**
     * @var array
     */
    private $completedIDs = [];

    /**
     * @param EntityManager $em
     * @param ClassMetadata $class
     */
    public function __construct(EntityManager $em, ClassMetadata $class = null)
    {
        if (!$class) {
            $class = new ClassMetadata(Campaign::class);
        }
        parent::__construct($em, $class);
    }

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
        if (!(defined('MAUTIC_PLUGIN_CONTACT_SOURCE_REALTIME')
                && true === MAUTIC_PLUGIN_CONTACT_SOURCE_REALTIME)
            && null === $limiter->getContactId()) {
            return parent::getPendingContactIds($campaignId, $limiter);
        }

        if ($this->finished) {
            return [];
        }

        // Honor the parent class...
        if ($limiter->hasCampaignLimit() && 0 === $limiter->getCampaignLimitRemaining()) {
            return [];
        }

        $contacts = $limiter->getContactIdList();
        if ($limiter->getContactId()) {
            $contacts[] = $limiter->getContactId();
        }

        if ($limiter->hasCampaignLimit() && $limiter->getCampaignLimitRemaining() < $limiter->getBatchLimit()) {
            if (count($contacts) >= $limiter->getCampaignLimitRemaining()) {
                $contacts = array_slice($contacts, 0, $limiter->getCampaignLimitRemaining());
            }
        }

        if ($limiter->hasCampaignLimit()) {
            $limiter->reduceCampaignLimitRemaining(count($contacts));
        }

        $contacts = array_diff($contacts, $this->completedIDs);
        $contacts = array_slice($contacts, 0, $limiter->getBatchLimit());
        $this->completedIDs = array_merge($contacts, $this->completedIDs);

        if (empty($contacts)) {
            $this->finished = true;
        }

        return array_unique($contacts);
    }
}
