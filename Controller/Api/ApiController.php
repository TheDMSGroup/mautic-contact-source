<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactServerBundle\Controller\Api;

use FOS\RestBundle\Util\Codes;
use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\CampaignBundle\Entity\Campaign;
// use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Entity\Lead as Contact;
use MauticPlugin\MauticContactServerBundle\Entity\ContactServer;
use MauticPlugin\MauticContactServerBundle\Entity\Stat;
use MauticPlugin\MauticContactServerBundle\Exception\ContactServerException;
use MauticPlugin\MauticContactServerBundle\Model\Cache;
use MauticPlugin\MauticContactServerBundle\Model\CampaignEventModel;
use MauticPlugin\MauticContactServerBundle\Model\CampaignModel;
use MauticPlugin\MauticContactServerBundle\Model\CampaignSettings;
use MauticPlugin\MauticContactServerBundle\Model\ContactModel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ContactServerApiController.
 *
 * @todo - This controller now contains too much business logic. Refactor offloading the logic to a model.
 */
class ApiController extends CommonApiController
{

    /** @var integer */
    protected $status;

    /** @var ContactModel */
    protected $contactModel;

    /** @var ContactServer */
    protected $contactServer;

    /** @var Cache */
    protected $cacheModel;

    /** @var boolean */
    protected $valid;

    /** @var Contact */
    protected $contact;

    /** @var array */
    protected $errors;

    /** @var boolean */
    protected $realTime;

    /** @var boolean */
    protected $scrub;

    /** @var CampaignModel */
    protected $campaignModel;

    /** @var Campaign */
    protected $campaign;

    /**
     * Primary API endpoint for servers to post contacts.
     *
     * @param Request $request
     * @param null $serverId
     * @param null $campaignId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function contactAction(Request $request, $serverId = null, $campaignId = null)
    {

        $response = [];
        $data = [];
        $this->valid = false;
        $this->status = null;
        $start = microtime(true);

        try {
            // Basic field pre-validation.
            $fieldData = $request->request->all();
            unset($fieldData['token']);
            if (!count($fieldData)) {
                throw new ContactServerException(
                    'No fields were posted. Did you mean to do that?',
                    0,
                    null,
                    Stat::TYPE_INVALID
                );
            }
            $token = InputHelper::clean($request->get('token'));
            if (!$token) {
                $token = InputHelper::clean($request->headers->get('token'));
                if (!$token) {
                    $token = InputHelper::clean($request->headers->get('X-Auth-Token'));
                    if (!$token) {
                        throw new ContactServerException(
                            'The token was not supplied. Please provide your authentication token.',
                            0,
                            null,
                            Stat::TYPE_INVALID,
                            'token'
                        );
                    }
                }
            }
            $serverId = intval($serverId);
            if (!$serverId) {
                $serverId = intval(InputHelper::clean($request->get('serverId')));
                if (!$serverId) {
                    throw new ContactServerException(
                        'The serverId was not supplied. Please provide your serverId.',
                        0,
                        null,
                        Stat::TYPE_INVALID,
                        'serverId'
                    );
                }
            }
            $campaignId = intval($campaignId);
            if (!$campaignId) {
                $campaignId = intval(InputHelper::clean($request->get('campaignId')));
                if (!$campaignId) {
                    throw new ContactServerException(
                        'The campaignId was not supplied. Please provide your campaignId.',
                        0,
                        null,
                        Stat::TYPE_INVALID,
                        'campaignId'
                    );
                }
            }

            // Check Server existence and published status.
            $serverModel = $this->get('mautic.contactserver.model.contactserver');
            $this->contactServer = $serverModel->getEntity($serverId);
            if (!$this->contactServer) {
                throw new ContactServerException(
                    'The serverId specified does not exist.',
                    0,
                    null,
                    Stat::TYPE_INVALID,
                    'serverId'
                );
            } elseif ($this->contactServer->getIsPublished() === false) {
                throw new ContactServerException(
                    'The serverId specified has been unpublished (deactivated).',
                    0,
                    null,
                    Stat::TYPE_INVALID,
                    'serverId'
                );
            }

            // Check authentication token against the server.
            if ($token !== $this->contactServer->getToken()) {
                throw new ContactServerException(
                    'The token specified is invalid.', 0, null, Stat::TYPE_INVALID, 'token'
                );
            }

            // Check that the campaign is in the whitelist for this server.
            /** @var CampaignSettings $campaignSettingsModel */
            $campaignSettingsModel = $this->get('mautic.contactserver.model.campaign_settings');
            $campaignSettingsModel->setContactServer($this->contactServer);
            $campaignSettings = $campaignSettingsModel->getCampaignSettingsById($campaignId);
            // @todo - Support or thwart multiple copies of the same campaign, should it occur. In the meantime...
            $campaignSettings = reset($campaignSettings);
            if (!$campaignSettings) {
                throw new ContactServerException(
                    'The campaignId supplied is not in the permitted list of campaigns for this server.',
                    0,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            }
            $this->realTime = (boolean)isset($campaignSettings->realTime) && $campaignSettings->realTime;

            // Check Campaign existence and published status.
            /** @var Campaign $campaign */
            $this->campaign = $this->getCampaignModel()->getEntity($campaignId);
            if (!$this->campaign) {
                throw new ContactServerException(
                    'The campaignId specified does not exist.',
                    0,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            } elseif ($this->campaign->getIsPublished() === false) {
                throw new ContactServerException(
                    'The campaignId specified has been unpublished (deactivated).',
                    0,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            }

            // Generate a new contact entity (not yet saved so that we can use it for validations).
            $this->contact = $this->import($fieldData);
            if (!$this->contact) {
                throw new ContactServerException(
                    'Not enough valid data was provided to create a contact.',
                    0,
                    null,
                    Stat::TYPE_INVALID
                );
            }
            $this->contact->setNew(true);

            // @todo - Evaluate required fields based on all the fields that are used in the campaign.
            // (This would best be done by cron, and cached somewhere as a list like campaign_required_fields)
            // (presuppose the overridden fields, if any)

            // @todo - Evaluate Server+Campaign limits using the Cache (method doesn't yet exist for either contact/client).
            // $this->getCacheModel()->evaluateLimits();

            // @todo - Evaluate Server duplicates against the cache. This is different than contact duplicates,
            // as we only care about duplicates within the server. It is unsustainable to check against all contacts
            // ever received by Mautic, so we only check for duplicates received by this server within a time frame.

            // @todo - Evaluate scrub rate, and if scrubbed we'll return negative status.
            // @todo - Apply values(and scrub) to the contact for the mautic-contact-ledger plugin, if available.
            $this->scrub = false;
            if ($this->scrub) {
                $this->status = Stat::TYPE_SCRUB;
            }

            // Save the new contact.
            $this->getContactModel()->saveEntity($this->contact);
            $this->status = Stat::TYPE_SAVED;
            $data = $this->contact->getUpdatedFields();
            $data['id'] = $this->contact->getId();
            $this->valid = true;

            // @todo - Optionally allow a segment to be targeted instead of a campaign in the future? No problem...
            // /** @var \Mautic\LeadBundle\Model\ListModel $leadListModel */
            // $leadListModel = $this->get('mautic.lead.model.list');
            // $leadListModel->addLead($this->contact, [LeadList $list], true, !$this->realTime, -1);

            // Add the contact directly to the campaign without duplicate checking.
            $this->getCampaignModel()->addContact($this->campaign, $this->contact, false, $this->realTime);
            $this->status = Stat::TYPE_QUEUED;

            // Create cache entry if all was successful for duplicate checking and limits.
            $this->getCacheModel()->create();

            // @todo - Async (not real time): Accept the contact by return status.
            if (!$this->realTime && !$this->scrub) {
                $this->status = Stat::TYPE_ACCEPT;
            }

            // @todo - Sync (real time): If this Server+Campaign is set to synchronous (and wasn't scrub), push the contact through the campaign now.
            if ($this->realTime && !$this->scrub) {
                // Step through the campaign model events.
                $totalEventCount = 0;
                /** @var CampaignEventModel $campaignEventModel */
                $campaignEventModel = $this->get('mautic.contactserver.model.campaign_event');
                $campaignResult = $campaignEventModel->triggerStartingEvents($this->campaign, $totalEventCount, [$this->contact]);

                // @todo - Sync (real time): Evaluate the result of the campaign workflow and return status.
            }

        } catch (\Exception $e) {
            $field = null;
            if ($e instanceof ContactServerException) {
                if ($this->contact) {
                    $e->setContact($this->contact);
                }
                $status = $e->getStatType();
                if ($status) {
                    $this->status = $status;
                }
                $field = $e->getField();
            }
            if ($field) {
                $this->errors[$field] = $e->getMessage();
            } else {
                $this->errors[] = $e->getMessage();
            }
        }

        $response['success'] = $this->valid;
        $response['status'] = $this->status;
        $response['executionTime'] = microtime(true) - $start;
        $response['data'] = $data;
        if (count($this->errors)) {
            $response['errors'] = $this->errors;
        }

        $view = $this->view($response, Codes::HTTP_OK);

        // By default we'll always respond with JSON.
        // @todo - Support any inbound format automatically.
        $view->setFormat('json');

        return $this->handleView($view);
    }

    /**
     * Get our customized Campaign model.
     *
     * @return CampaignModel
     */
    private function getCampaignModel()
    {
        if (!$this->campaignModel) {
            /** @var CampaignModel */
            $this->campaignModel = $this->get('mautic.contactserver.model.campaign');
        }

        return $this->campaignModel;
    }

    /**
     * Create contact from request data, but do not save it yet.
     *
     * @param array $fieldData
     * @return bool|null
     * @throws \Exception
     */
    private function import($fieldData = [])
    {

        $fieldKeys = array_keys($fieldData);
        $fieldValues = array_values($fieldData);
        $fieldMap = array_combine($fieldKeys, $fieldKeys);

        // Pre-filter keys and values to follow the "import" method's pattern.
        $fieldKeys = array_map('trim', $fieldKeys);
        if (!array_filter($fieldKeys)) {
            return null;
        }
        $fieldValues = array_map('trim', $fieldValues);
        if (!array_filter($fieldValues)) {
            return null;
        }
        $data = array_combine($fieldKeys, $fieldValues);

        return $this->getContactModel()->import(
            $fieldMap,
            $data
        // @todo - get some default values for these.
        // $owner,
        // $list,
        // $tags,
        // false,
        // null
        );
    }

    /**
     * Return our extended contact model.
     *
     * @return ContactModel|object
     */
    private function getContactModel()
    {
        if (!$this->contactModel) {
            $this->contactModel = $this->get('mautic.contactserver.model.contact');
        }

        return $this->contactModel;
    }

    /**
     * @return Cache
     * @throws \Exception
     */
    private function getCacheModel()
    {
        if (!$this->cacheModel) {
            /** @var \MauticPlugin\MauticContactServerBundle\Model\Cache $cacheModel */
            $this->cacheModel = $this->get('mautic.contactserver.model.cache');
            $this->cacheModel->setContact($this->contact);
            $this->cacheModel->setContactServer($this->contactServer);
        }

        return $this->cacheModel;
    }


    // @todo - method to get a single request field and fall back to alternative names/locations.
}
