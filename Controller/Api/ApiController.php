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
use Mautic\CoreBundle\Helper\InputHelper;
use MauticPlugin\MauticContactServerBundle\Model\Cache;
use MauticPlugin\MauticContactServerBundle\Model\CampaignSettings;
use MauticPlugin\MauticContactServerBundle\Model\ContactModel;
use Symfony\Component\HttpFoundation\Request;
use MauticPlugin\MauticContactServerBundle\Entity\Stat;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\LeadBundle\Model\ListModel as LeadListModel;
use MauticPlugin\MauticContactServerBundle\Exception\ContactServerException;

/**
 * Class ContactServerApiController.
 */
class ApiController extends CommonApiController
{

    /** @var integer */
    protected $status;

    /** @var ContactModel */
    protected $contactModel;

    /** @var boolean */
    protected $valid;

    /** @var Contact */
    protected $contact;

    /** @var array */
    protected $errors;

    /**
     * Primary API endpoint for servers to post contacts.
     *
     * @param Request $request
     * @param null $serverId
     * @param null $campaignId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addContactAction(Request $request, $serverId = null, $campaignId = null)
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
            $contactServer = $serverModel->getEntity($serverId);
            if (!$contactServer) {
                throw new ContactServerException(
                    'The serverId specified does not exist.',
                    0,
                    null,
                    Stat::TYPE_INVALID,
                    'serverId'
                );
            } elseif ($contactServer->getIsPublished() === false) {
                throw new ContactServerException(
                    'The serverId specified has been unpublished (deactivated).',
                    0,
                    null,
                    Stat::TYPE_INVALID,
                    'serverId'
                );
            }

            // Check authentication token against the server.
            if ($token !== $contactServer->getToken()) {
                throw new ContactServerException(
                    'The token specified is invalid.', 0, null, Stat::TYPE_INVALID, 'token'
                );
            }

            // Check that the campaign is in the whitelist for this server.
            /** @var CampaignSettings $campaignSettingsModel */
            $campaignSettingsModel = $this->get('mautic.contactserver.model.campaign_settings');
            $campaignSettingsModel->setContactServer($contactServer);
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

            // Check Campaign existence and published status.
            $campaignModel = $this->get('mautic.campaign.model.campaign');
            $campaign = $campaignModel->getEntity($campaignId);
            if (!$campaign) {
                throw new ContactServerException(
                    'The campaignId specified does not exist.',
                    0,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            } elseif ($campaign->getIsPublished() === false) {
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
                    'Not enough valid data to create a contact.',
                    0,
                    null,
                    Stat::TYPE_INVALID
                );
            }
            $this->contact->setNew(true);

            // @todo - Evaluate required fields based on all the fields that are used in the campaign.
            // (This would best be done by cron, and cached somewhere as a list like campaign_required_fields)
            // (presuppose the overridden fields, if any)

            // @todo - Evaluate Server+Campaign limits using the Cache
            // /** @var Cache $cacheModel */
            // $cacheModel = $this->get('mautic.contactserver.model.cache');
            // $cacheModel->setContactServer($contactServer);

            // @todo - Evaluate Server duplicates.

            // @todo - Evaluate scrub rate, and if scrubbed return negative status.

            // Save the new contact.
            $this->getContactModel()->saveEntity($this->contact);
            $this->status = Stat::TYPE_QUEUED;
            $data = $this->contact->getUpdatedFields();
            $data['id'] = $this->contact->getId();
            $this->valid = true;

            // Add the contact to the campaign specified.
            /** @var LeadListModel $leadListModel */
            $leadListModel = $this->get('mautic.lead.model.list');
            $leadListModel->addLead($this->contact, [$campaignId], false, false, -1);

            // @todo - Create cache entry if all was successful.

            // @todo - Async: Accept the contact by return status.

            // @todo - Sync: If this Server+Campaign is set to synchronous (and wasn't scrubbed), push the contact through the campaign now.

            // @todo - Sync: Evaluate the result of the campaign workflow and return status.

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

    // @todo - method to get a single request field and fall back to alternative names/locations.
}
