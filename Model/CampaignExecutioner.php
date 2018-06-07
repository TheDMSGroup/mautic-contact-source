<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Model;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;
use Mautic\CampaignBundle\Executioner\InactiveExecutioner;
use Mautic\CampaignBundle\Executioner\KickoffExecutioner;
use Mautic\CampaignBundle\Executioner\Result\Counter;
use Mautic\CampaignBundle\Executioner\ScheduledExecutioner;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class CampaignExecutioner
 */
class CampaignExecutioner
{

    /** @var KickoffExecutioner */
    private $kickoffExecutioner;

    /** @var ScheduledExecutioner */
    private $scheduledExecutioner;

    /** @var InactiveExecutioner */
    private $inactiveExecutioner;

    /**
     * CampaignExecutioner constructor.
     *
     * @param KickoffExecutioner   $kickoffExecutioner
     * @param ScheduledExecutioner $scheduledExecutioner
     * @param InactiveExecutioner  $inactiveExecutioner
     */
    public function __construct(
        KickoffExecutioner $kickoffExecutioner,
        ScheduledExecutioner $scheduledExecutioner,
        InactiveExecutioner $inactiveExecutioner
    ) {
        $this->kickoffExecutioner   = $kickoffExecutioner;
        $this->scheduledExecutioner = $scheduledExecutioner;
        $this->inactiveExecutioner  = $inactiveExecutioner;
    }

    /**
     * Kicks off real-time campaign events for a single contact.
     *
     * Returns an array with aggregated output, and counters.
     *
     * @param Campaign $campaign
     * @param array    $contactIdList
     *
     * @return array
     * @throws \Doctrine\ORM\Query\QueryException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function execute(Campaign $campaign, array $contactIdList)
    {
        $output  = new BufferedOutput();
        $limiter = new ContactLimiter(null, null, null, null, $contactIdList);

        /** @var Counter $kickoff */
        $kickoff = $this->kickoffExecutioner->execute($campaign, $limiter, $output);

        /** @var Counter $schedule */
        $schedule = $this->scheduledExecutioner->execute($campaign, $limiter, $output);

        /** @var Counter $inactive */
        $inactive = $this->inactiveExecutioner->execute($campaign, $limiter, $output);

        return [
            'output'   => $output,
            'kickoff'  => $kickoff,
            'schedule' => $schedule,
            'inactive' => $inactive,
        ];
    }

}