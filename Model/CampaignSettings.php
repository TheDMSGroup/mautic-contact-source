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

use Mautic\LeadBundle\Entity\Lead as Contact;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use MauticPlugin\MauticContactSourceBundle\Helper\JSONHelper;

/**
 * Class CampaignSettings
 * For business logic regarding the Campaign Settings field within the ContactSource model.
 */
class CampaignSettings
{
    /** @var ContactSource */
    protected $contactSource;

    /** @var Contact */
    protected $contact;

    /** @var array */
    protected $campaignSettings;

    /** @var array */
    protected $logs = [];

    /** @var bool */
    protected $valid = true;

    /** @var contactSourceModel */
    protected $contactSourceModel;

    /**
     * CampaignSettings constructor.
     *
     * @param contactSourceModel $contactSourceModel
     */
    public function __construct(
        contactSourceModel $contactSourceModel
    ) {
        $this->contactSourceModel = $contactSourceModel;
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
     * @return ContactSource
     */
    public function getContactSource()
    {
        return $this->contactSource;
    }

    /**
     * @param ContactSource $contactSource
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setContactSource(ContactSource $contactSource)
    {
        $this->contactSource = $contactSource;
        $this->setCampaignSettings($this->contactSource->getCampaignSettings());

        return $this;
    }

    /**
     * Take the stored JSON string and parse for use.
     *
     * @param string $campaignSettings
     *
     * @return mixed
     *
     * @throws \Exception
     */
    private function setCampaignSettings(string $campaignSettings = null)
    {
        if (!$campaignSettings) {
            throw new \Exception('Campaigns have not been mapped to this source.');
        }

        $jsonHelper             = new JSONHelper();
        $this->campaignSettings = $jsonHelper->decodeObject($campaignSettings, 'CampaignSettings');

        return $this;
    }

    /**
     * @return array
     */
    public function getCampaignSettings()
    {
        return $this->campaignSettings;
    }

    /**
     * Returns an array of campaign objects (as multiple can match).
     *
     * @param $campaignId
     *
     * @return array
     */
    public function getCampaignSettingsById($campaignId)
    {
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
