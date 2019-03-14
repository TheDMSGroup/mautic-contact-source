<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignLeadChangeEvent;
use MauticPlugin\MauticContactSourceBundle\Model\Api as ApiModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    /**
     * @var ApiModel
     */
    protected $apiModel;

    /**
     * CampaignSubscriber constructor.
     *
     * @param ApiModel $apiModel
     */
    public function __construct(
        ApiModel $apiModel
    ) {
        $this->apiModel = $apiModel;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_LEADCHANGE     => 'onChange',
            CampaignEvents::LEAD_CAMPAIGN_BATCH_CHANGE => 'onBatchChange',
        ];
    }

    /**
     * @param CampaignLeadChangeEvent $event
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function onChange(CampaignLeadChangeEvent $event)
    {
        if (!$event->wasAdded()) {
            return;
        }
        if (!$contact = $event->getLead()) {
            return;
        }
        if (!$campaign = $event->getCampaign()) {
            return;
        }
        if (
            !$campaign->isPublished()
            || !$contact->getId()
            || defined('MAUTIC_SOURCE_INGESTION')
            || defined('MAUTIC_SOURCE_FORKED_CHILD')
            || !boolval($this->apiModel->getIntegrationSetting('parallel_schedule', false))
        ) {
            return;
        }
        $campaignIds = [
            $campaign->getId() => false,
        ];
        $this->apiModel->kickoffParallelCampaigns($contact, $campaignIds);

        return;
    }

    /**
     * @param CampaignLeadChangeEvent $event
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function onBatchChange(CampaignLeadChangeEvent $event)
    {
        if (!$event->wasAdded()) {
            return;
        }
        if (!$contacts = $event->getLeads()) {
            return;
        }
        if (!$campaign = $event->getCampaign()) {
            return;
        }
        if (
            !$campaign->isPublished()
            || defined('MAUTIC_SOURCE_INGESTION')
            || defined('MAUTIC_SOURCE_FORKED_CHILD')
            || !boolval($this->apiModel->getIntegrationSetting('parallel_batch', false))
        ) {
            return;
        }
        foreach ($contacts as $contact) {
            if (!$contact->getId()) {
                continue;
            }
            $campaignIds = [
                $campaign->getId() => false,
            ];
            $this->apiModel->kickoffParallelCampaigns($contact, $campaignIds);
        }

        return;
    }
}
