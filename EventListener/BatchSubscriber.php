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

use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\ImportModel;
use MauticPlugin\MauticContactSourceBundle\Model\Api as ApiModel;
use MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel;

/**
 * Class BatchSubscriber.
 */
class BatchSubscriber extends CommonSubscriber
{
    /**
     * @var ContactSourceModel
     */
    protected $sourceModel;

    /**
     * @var CampaignModel
     */
    protected $campaignModel;

    /**
     * @var ApiModel
     */
    protected $apiModel;

    /**
     * @var ImportModel
     */
    protected $importModel;

    /**
     * FormSubscriber constructor.
     *
     * @param ContactSourceModel $sourceModel
     * @param CampaignModel      $campaignModel
     * @param ApiModel           $apiModel
     * @param ImportModel        $importModel
     */
    public function __construct(
        ContactSourceModel $sourceModel,
        CampaignModel $campaignModel,
        ApiModel $apiModel,
        ImportModel $importModel
    ) {
        $this->sourceModel   = $sourceModel;
        $this->campaignModel = $campaignModel;
        $this->apiModel      = $apiModel;
        $this->importModel   = $importModel;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            LeadEvents::LEAD_PRE_SAVE  => 'onLeadPreSave',
            LeadEvents::LEAD_POST_SAVE => 'onLeadPostSave',
        ];
    }

    /**
     * @param LeadEvent $leadEvent
     */
    public function onLeadPreSave(LeadEvent $leadEvent)
    {
        try {
            if ($this->prepForContactImport($leadEvent)) {
                $this->apiModel->setUtmSourceTag();
                $this->apiModel->processOffline();
                $this->apiModel->applyAttribution();
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @param $leadEvent
     *
     * @return bool
     *
     * @throws \MauticPlugin\MauticContactSourceBundle\Exception\ContactSourceException
     */
    private function prepForContactImport($leadEvent)
    {
        $importing = false;
        $contact   = $leadEvent->getLead();
        if (
            true === $contact->imported
            && ($identityMap = $this->em->getUnitOfWork()->getIdentityMap())
            && isset($identityMap['Mautic\LeadBundle\Entity\Import'])
            && ($identityArray = $identityMap['Mautic\LeadBundle\Entity\Import'])
        ) {
            $this->apiModel->setImported(true);
            $this->apiModel->setRealtime(false);

            $import           = array_shift($identityArray);
            $importProperties = $import->getProperties();

            $campaignId = $importProperties['parser']['campaign'];
            $sourceId   = $importProperties['parser']['source'];

            $this->apiModel->setContact($contact);
            if ($sourceId !== $this->apiModel->getSourceId()) {
                $this->apiModel->setSourceId($sourceId);
                $this->apiModel->setContactSource($this->apiModel->getContactSourceModel()->getEntity($sourceId));
                if ($campaignId !== $this->apiModel->getCampaignId()) {
                    $this->apiModel->setCampaignId($campaignId);
                    $this->apiModel->setCampaign($this->campaignModel->getEntity($campaignId));
                }
            }
            $this->apiModel->parseSourceCampaignSettings();
            $importing = true;
        }

        return $importing;
    }

    /**
     * @param LeadEvent $leadEvent
     */
    public function onLeadPostSave(LeadEvent $leadEvent)
    {
        try {
            if ($this->prepForContactImport($leadEvent)) {
                $this->apiModel->addContactToCampaign();
                $this->apiModel->processParallel();
                $this->apiModel->logResults();
            }
        } catch (\Exception $e) {
        }
    }
}
