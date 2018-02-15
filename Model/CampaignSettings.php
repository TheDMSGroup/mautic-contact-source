<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Model;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\Lead as Contact;
use MauticPlugin\MauticContactServerBundle\Entity\ContactServer;
use MauticPlugin\MauticContactServerBundle\Helper\JSONHelper;

/**
 * Class CampaignSettings
 * For business logic regarding the Campaign Settings field within the ContactServer model.
 *
 * @package MauticPlugin\MauticContactServerBundle\Model
 */
class CampaignSettings
{

    /** @var ContactServer */
    protected $contactServer;

    /** @var Contact */
    protected $contact;

    /** @var array */
    protected $campaignSettings;

    /** @var array */
    protected $logs = [];

    /** @var bool */
    protected $valid = true;

    /** @var contactServerModel */
    protected $contactServerModel;

    /**
     * CampaignSettings constructor.
     * @param contactServerModel $contactServerModel
     */
    public function __construct(
        contactServerModel $contactServerModel
    ) {
        $this->contactServerModel = $contactServerModel;
    }

    /**
     * @return Contact
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * @param Contact $contact
     *
     * @return $this
     */
    public function setContact(Contact $contact)
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * @return ContactServer
     */
    public function getContactServer()
    {
        return $this->contactServer;
    }

    /**
     * @param ContactServer $contactServer
     * @return $this
     * @throws \Exception
     */
    public function setContactServer(ContactServer $contactServer)
    {
        $this->contactServer = $contactServer;
        $this->setCampaignSettings($this->contactServer->getCampaignSettings());

        return $this;
    }

    /**
     * Take the stored JSON string and parse for use.
     *
     * @param string $campaignSettings
     * @return mixed
     * @throws \Exception
     */
    private function setCampaignSettings(string $campaignSettings)
    {
        if (!$campaignSettings) {
            throw new \Exception('Campaign Settings are blank.');
        }

        $jsonHelper = new JSONHelper();
        $this->campaignSettings = $jsonHelper->decodeObject($campaignSettings, 'CampaignSettings');
        
        return $this;
    }

    /**
     * @return array
     */
    public function getCampaignSettings() {
        return $this->campaignSettings;
    }

    /**
     * Returns an array of campaign objects (as multiple can match).
     *
     * @param $campaignId
     * @return array
     */
    public function getCampaignSettingsById($campaignId) {
        $results = [];
        if (
            $campaignId
            && isset($this->campaignSettings)
            && isset($this->campaignSettings->campaigns)
            && is_array($this->campaignSettings->campaigns)
        ) {
            // Note, there may be multiple matches for the same campaignId for future mapping purposes, so we aggregate.
            foreach ($this->campaignSettings->campaigns as $id => $campaign) {
                if (isset($campaign->campaignId) && $campaign->campaignId == $campaignId) {
                    $results[$id] = $campaign;
                }
            }
        }
        return $results;
    }
}