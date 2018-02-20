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
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\CampaignBundle\Model\CampaignModel as OriginalCampaignModel;
use Mautic\CampaignBundle\Entity\Lead as CampaignContact;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignLeadChangeEvent;

/**
 * Class CampaignModel
 *
 * Extension of the original Campaign Model to allow swift addition to a campaign,
 * and to optionally *push* a contact through a campaign in real-time.
 */
class CampaignModel extends OriginalCampaignModel
{

    /**
     * Add contact to the campaign.
     *
     * Added realTime parameter.
     *
     * @param Campaign $campaign
     * @param Contact $contact
     * @param bool $manuallyAdded
     * @param bool $realTime
     */
    public function addContact(Campaign $campaign, Contact $contact, $manuallyAdded = false, $realTime = false)
    {
        $this->addContacts($campaign, [$contact], $manuallyAdded, $realTime);

        unset($campaign, $lead);
    }

    /**
     * Add contact to a campaign, and optionally run in real-time.
     *
     * @param Campaign $campaign
     * @param array $contacts
     * @param bool $manuallyAdded
     * @param bool $realTime
     */
    public function addContacts(
        Campaign $campaign,
        $contacts = [],
        $manuallyAdded = false,
        $realTime = false
    ) {

        foreach ($contacts as $contact) {
            $campaignContact = new CampaignContact();
            $campaignContact->setCampaign($campaign);
            $campaignContact->setDateAdded(new \DateTime());
            $campaignContact->setLead($contact);
            $campaignContact->setManuallyAdded($manuallyAdded);
            $saved = $this->saveCampaignLead($campaignContact);

            if (!$realTime) {
                // Only trigger events if not in realtime where events would be followed directly.
                if ($saved && $this->dispatcher->hasListeners(CampaignEvents::CAMPAIGN_ON_LEADCHANGE)) {
                    $event = new CampaignLeadChangeEvent($campaign, $contact, 'added');
                    $this->dispatcher->dispatch(CampaignEvents::CAMPAIGN_ON_LEADCHANGE, $event);
                    unset($event);
                }

                // Detach to save memory
                $this->em->detach($campaignContact);
                unset($campaignContact);
            }
        }
        unset($campaign, $campaignContact, $contacts);
    }

}
