<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Model;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;
use Mautic\CampaignBundle\Executioner\KickoffExecutioner;
use Mautic\CampaignBundle\Executioner\Result\Counter;

/**
 * Class CampaignExecutioner.
 */
class CampaignExecutioner
{
    /** @var KickoffExecutioner */
    private $kickoffExecutioner;

    /**
     * CampaignExecutioner constructor.
     *
     * @param KickoffExecutioner $kickoffExecutioner
     */
    public function __construct(
        KickoffExecutioner $kickoffExecutioner
    ) {
        $this->kickoffExecutioner = $kickoffExecutioner;
    }

    /**
     * Kicks off real-time campaign events for a single contact.
     *
     * @param Campaign $campaign
     * @param array    $contactIdList
     * @param int      $batchLimit
     *
     * @return Counter
     *
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function execute(Campaign $campaign, array $contactIdList, $batchLimit = 100)
    {
        $limiter = new ContactLimiter($batchLimit, null, null, null, $contactIdList);

        // Make sure these events show up as system triggered for summary counts to be accurate.
        defined('MAUTIC_CAMPAIGN_SYSTEM_TRIGGERED') or define('MAUTIC_CAMPAIGN_SYSTEM_TRIGGERED', 1);

        /** @var Counter $counter */
        $counter = $this->kickoffExecutioner->execute($campaign, $limiter);

        // All events not included in the kickoff are scheduled or decisions waiting on input.

        return $counter;
    }
}
