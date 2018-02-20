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
// use Mautic\CoreBundle\Helper\InputHelper;
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
use Mautic\LeadBundle\Entity\UtmTag;

/**
 * Class ContactServerApiController.
 *
 * @todo - This controller now contains too much business logic. Refactor offloading the logic to a model.
 */
class ApiController extends CommonApiController
{

    /** @var string */
    protected $status;

    /** @var integer */
    protected $statusCode;

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

    /** @var integer */
    protected $scrubRate;

    /** @var integer */
    protected $attribution;

    /** @var string */
    protected $utmSource;

    /** @var boolean */
    protected $scrubbed;

    /** @var CampaignModel */
    protected $campaignModel;

    /** @var Campaign */
    protected $campaign;

    /** @var array */
    protected $fieldsAccepted;

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
        $events = [];
        $this->valid = false;
        $this->status = null;
        $startFloat = microtime(true);
        $start = new \DateTime();
        $debug = $request->headers->get('debug');

        try {
            // Get a clean array input trimming all keys and values, excluding empties.
            $fieldData = [];
            foreach ($request->request->all() as $k => $v) {
                $k = trim($k);
                if ($k !== '') {
                    $v = trim($v);
                    if ($v !== '') {
                        $fieldData[$k] = $v;
                    }
                }
            }
            // Basic field pre-validation.
            unset($fieldData['token'], $fieldData['serverId'], $fieldData['campaignId']);
            if (!count($fieldData)) {
                throw new ContactServerException(
                    'No fields were posted. Did you mean to do that?',
                    Codes::HTTP_I_AM_A_TEAPOT,
                    null,
                    Stat::TYPE_INVALID
                );
            }
            $token = $request->get('token');
            if (!$token) {
                $token = $request->headers->get('token');
                if (!$token) {
                    $token = $request->headers->get('X-Auth-Token');
                    if (!$token) {
                        throw new ContactServerException(
                            'The token was not supplied. Please provide your authentication token.',
                            Codes::HTTP_UNAUTHORIZED,
                            null,
                            Stat::TYPE_INVALID,
                            'token'
                        );
                    }
                }
            }
            $serverId = intval($serverId);
            if (!$serverId) {
                $serverId = intval($request->get('serverId'));
                if (!$serverId) {
                    throw new ContactServerException(
                        'The serverId was not supplied. Please provide your serverId.',
                        Codes::HTTP_BAD_REQUEST,
                        null,
                        Stat::TYPE_INVALID,
                        'serverId'
                    );
                }
            }
            $campaignId = intval($campaignId);
            if (!$campaignId) {
                $campaignId = intval($request->get('campaignId'));
                if (!$campaignId) {
                    throw new ContactServerException(
                        'The campaignId was not supplied. Please provide your campaignId.',
                        Codes::HTTP_BAD_REQUEST,
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
                    Codes::HTTP_NOT_FOUND,
                    null,
                    Stat::TYPE_INVALID,
                    'serverId'
                );
            } elseif ($this->contactServer->getIsPublished() === false) {
                throw new ContactServerException(
                    'The serverId specified has been unpublished (deactivated).',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'serverId'
                );
            }

            // Check authentication token against the server.
            if ($token !== $this->contactServer->getToken()) {
                throw new ContactServerException(
                    'The token specified is invalid. Please request a new token.',
                    Codes::HTTP_UNAUTHORIZED,
                    null,
                    Stat::TYPE_INVALID,
                    'token'
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
                    'The campaignId supplied is not currently in the permitted list of campaigns for this server.',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            }
            // Establish parameters from campaign settings.
            $this->realTime = (boolean)isset($campaignSettings->realTime) && $campaignSettings->realTime;
            $this->scrubRate = isset($campaignSettings->scrubRate) ? intval($campaignSettings->scrubRate) : 0;
            $this->attribution = isset($campaignSettings->attribution) ? intval($campaignSettings->attribution) : 0;
            $this->utmSource = !empty($campaignSettings->utmSource) ? $campaignSettings->utmSource : null;
            // Apply field overrides
            if ($this->attribution) {
                $fieldData['attribution'] = $this->attribution;
            }
            if ($this->utmSource) {
                $fieldData['utm_source'] = $this->utmSource;
            }

            // Check Campaign existence and published status.
            /** @var Campaign $campaign */
            $this->campaign = $this->getCampaignModel()->getEntity($campaignId);
            if (!$this->campaign) {
                throw new ContactServerException(
                    'The campaignId specified does not exist.',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            } elseif ($this->campaign->getIsPublished() === false) {
                throw new ContactServerException(
                    'The campaignId specified has been unpublished (deactivated).',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            }

            // Generate a new contact entity (not yet saved so that we can use it for validations).
            $this->contact = $this->import($fieldData, $debug);
            if (!$this->contact) {
                throw new ContactServerException(
                    'Not enough valid data was provided to create a contact for this campaign.',
                    Codes::HTTP_BAD_REQUEST,
                    null,
                    Stat::TYPE_INVALID
                );
            }
            $this->contact->setNew(true);

            // @todo - Evaluate required fields based on all the fields that are used in the campaign.
            // (This would best be done by cron, and cached somewhere as a list like campaign_required_fields)
            // (presuppose the overridden fields, if any)

            // @todo - Evaluate Server & Campaign limits using the Cache (method doesn't yet exist for either contact/client).
            // $this->getCacheModel()->evaluateLimits();

            // @todo - Evaluate Server duplicates against the cache. This is different than contact duplicates,
            // as we only care about duplicates within the server. It is unsustainable to check against all contacts
            // ever received by Mautic, so we only check for duplicates received by this server within a time frame.

            // Save the new contact since it is valid by this point.
            $this->getContactModel()->saveEntity($this->contact);
            $this->status = Stat::TYPE_SAVED;

            // @todo - Optionally allow a segment to be targeted instead of a campaign in the future? No problem...
            // /** @var \Mautic\LeadBundle\Model\ListModel $leadListModel */
            // $leadListModel = $this->get('mautic.lead.model.list');
            // $leadListModel->addLead($this->contact, [LeadList $list], true, !$this->realTime, -1);

            // Add the contact directly to the campaign without duplicate checking.
            $this->getCampaignModel()->addContact($this->campaign, $this->contact, false, $this->realTime);
            $this->status = Stat::TYPE_QUEUED;

            // Create cache entry if all was successful for duplicate checking and limits.
            $this->getCacheModel()->create();

            // Asynchronous (not real time): Accept the contact by return status, or scrub.
            if (!$this->realTime) {
                // Establish scrub now.
                if ($this->isScrubbed()) {
                    // Asynchronous rejection (scrubbed)
                    $this->status = Stat::TYPE_SCRUB;
                    $this->valid = false;
                } else {
                    // Asynchronous acceptance.
                    $this->status = Stat::TYPE_ACCEPT;
                    $this->valid = true;
                }
            }

            // Synchronous (real time): If this Server & Campaign is set to synchronous (and wasn't scrub), push the contact through the campaign now.
            if ($this->realTime) {
                // Synchronous acceptance or denial.
                // Step through the campaign model events to define status.
                $totalEventCount = 0;
                /** @var CampaignEventModel $campaignEventModel */
                $campaignEventModel = $this->get('mautic.contactserver.model.campaign_event');
                $campaignResult = $campaignEventModel->triggerContactStartingEvents($this->campaign, $totalEventCount, [$this->contact]);

                // Sync (real-time): Evaluate the result of the campaign workflow and return status.
                if (
                    $campaignResult
                    && !empty($campaignResult['contactClientEvents'])
                    && !empty($campaignResult['contactClientEvents'][$this->contact->getId()])
                ) {
                    $events = $campaignResult['contactClientEvents'][$this->contact->getId()];
                    foreach ($campaignResult['contactClientEvents'][$this->contact->getId()] as $event) {
                        if ($event['integration'] == 'Client') {
                            if (isset($event['valid']) && $event['valid']) {
                                // One valid Contact Client was found to accept the lead.
                                $this->status = Stat::TYPE_ACCEPT;
                                $this->valid = true;
                                break;
                            }
                        }
                    }
                }
                // There was no accepted client hit, consider this a rejection.
                // @todo - This is highly dependent on the contact client plugin, thus should be made configurable, or we can make the realTime mode only available to those with both plugins.
                if (!$this->valid) {
                    // If one or more clients were found, but none accepted.
                    // This should be considered a rejected contact.
                    $this->status = Stat::TYPE_REJECT;
                }

                // Apply scrub only to accepted contacts in real-time mode after evaluation.
                if ($this->valid && $this->isScrubbed()) {
                    $this->status = Stat::TYPE_SCRUB;
                    $this->valid = false;
                }
            }

            // Parse the response.
            $response['attribution'] = $this->valid ? ($this->attribution ? $this->attribution : 0) : 0;
            if ($this->campaign) {
                $response['campaignId'] = $this->campaign->getId();
            }
            if ($this->fieldsAccepted) {
                $response['fields'] = $this->fieldsAccepted;
            }
            if ($this->contactServer) {
                $response['serverId'] = $this->contactServer->getId();
            }
            $response['success'] = $this->valid;
            $response['time'] = [
                'completed' => new \DateTime(),
                'duration' => microtime(true) - $startFloat,
                'started' => $start,
            ];
            /**
             * Optionally include debug data.
             * @deprecated
             */
            if ($debug) {
                $response['status'] = $this->status;
                if ($events) {
                    $response['events'] = $events;
                }
            }

        } catch (\Exception $e) {

            $field = null;
            $code = $e->getCode();
            if ($e instanceof ContactServerException) {
                if ($this->contact) {
                    $e->setContact($this->contact);
                }
                $status = $e->getStatType();
                if ($status) {
                    $this->status = $status;
                }
                $field = $e->getField();
                if ($code) {
                    // We'll use these as HTTP status codes.
                    $this->statusCode = $code;
                }
            } elseif (!$this->statusCode) {
                // Unexpected exceptions should send 500.
                $this->statusCode = Codes::HTTP_INTERNAL_SERVER_ERROR;
            }
            $this->errors[$field ? $field : $code] = $e->getMessage();
        }

        // Append errors to the response if given.
        if ($this->errors) {
            $response['errors'] = $this->errors;
        }

        // This is a simplified output of the "Contact"
        // It is a flat array, containing only the fields that we accepted and used to create the contact.
        // It does not include the same entity structure as you would find in the core API.
        // This is intentional, since we do not necessarily want the third party to have access to all data,
        // that has been appended to the contact during the ingestion process.
        if ($this->contact && $this->valid && $this->fieldsAccepted) {
            $response['contact'] = $this->fieldsAccepted;
            $response['contact']['id'] = $this->contact->getId();
        }

        ksort($response);
        $view = $this->view($response, $this->statusCode ? $this->statusCode : Codes::HTTP_OK);

        // By default we'll always respond with JSON.
        // @todo - Support any inbound format automatically.
        $view->setFormat('json');

        return $this->handleView($view);
    }

    /**
     * Establish scrub status on first execution and keep it.
     *
     * @return bool
     */
    private function isScrubbed(){
        if ($this->scrubbed === null) {
            $this->scrubbed = $this->scrubRate > rand(0, 99);
        }
        return $this->scrubbed;
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
     * @param bool $debug
     * @return bool|Contact|null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    private function import($fieldData = [], $debug = false)
    {
        // By this point we have already filtered empty values/keys.

        // Filter out disallowed fields to prevent errors with queries down the line.
        /** @var \Mautic\LeadBundle\Model\FieldModel $fieldModel */
        $fieldModel = $this->get('mautic.lead.model.field');
        $allowedFields = $fieldModel->getFieldList(true, true, ['isPublished' => true]);
        // Flatten the array.
        $allowedFields = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($allowedFields)));

        // Add IP as an allowed import field.
        $allowedFields['ip'] = 'IP Address';

        // Get available UTM fields and their setters.
        $utmTags = new UtmTag();
        $utmSetters = $utmTags->getFieldSetterList();
        unset($utmSetters['query']);
        foreach ($utmSetters as $q => $v) {
            $allowedFields[$q] = str_replace('Utm', 'UTM', ucwords(str_replace('_', ' ', $q)));
        }

        $disallowedFields = array_diff(array_keys($fieldData), array_keys($allowedFields));
        foreach ($disallowedFields as $key) {
            // Help them out with a suggestion.
            $closest = null;
            $shortest = -1;
            foreach (array_keys($allowedFields) as $allowedKey) {
                $lev = levenshtein($key, $allowedKey);
                if ($lev <= $shortest || $shortest < 0) {
                    $closest  = $allowedKey;
                    $shortest = $lev;
                }
            }
            unset($fieldData[$key]);
            $msg = 'This field is not currently supported and was ignored.';
            if ($closest && isset($allowedKey)) {
                $msg .= ' Did you mean \''.$closest.'\' (' . $allowedFields[$closest] . ')?';
            }
            $this->errors[$key] = $msg;

            /**
             * Provide the list of allowed fields in debug mode.
             * @deprecated
             */
            if ($debug && !isset($this->errors['allowedFields'])) {
                ksort($allowedFields);
                $this->errors['allowedFields'] = $allowedFields;
            }
        }

        // Move UTM tags to another array to avoid use in import, since it doesn't support them.
        $utmTagData = [];
        foreach ($utmSetters as $k => $v) {
            if (isset($fieldData[$k])) {
                $utmTagData[$k] = $fieldData[$k];
                unset($fieldData[$k]);
            }
        }

        // Must have at least ONE valid contact field (some are to be ignored since we provide them or they are core).
        $ignore = ['ip', 'attribution', 'utm_source'];
        if (!count(array_diff_key($fieldData, array_flip($ignore)))) {
            return null;
        }

        // Dynamically generate the field map and import.
        // @todo - Discern and assign owner.
        $contact = $this->getContactModel()->import(
            array_combine(array_keys($fieldData), array_keys($fieldData)),
            $fieldData
            // $owner,
            // $list,
            // $tags,
            // false,
            // null
        );

        if (!$contact) {
            return null;
        }

        // Accepted fields straight from the contact entity.
        $this->fieldsAccepted = $contact->getUpdatedFields();
        if (isset($fieldData['ip'])) {
            $this->fieldsAccepted['ip'] = $fieldData['ip'];
        }

        // Cycle through calling appropriate setters if there is utm data.
        if (count($utmTagData)) {
            foreach ($utmSetters as $q => $setter) {
                if (isset($utmTagData[$q])) {
                    $utmTags->$setter($utmTagData[$q]);
                    $this->fieldsAccepted[$q] = $utmTagData[$q];
                }
            }

            // Set the UTM query from the URL if provided.
            if (isset($utmTagData['url'])) {
                parse_url($utmTagData['url'], PHP_URL_QUERY);
                parse_str(parse_url($utmTagData['url'], PHP_URL_QUERY), $query);
                $utmTags->setQuery($query);
            }

            // Add date added, critical for inserts.
            $utmTags->setDateAdded(new \DateTime());

            // Apply to the contact for save later.
            $utmTags->setLead($contact);
            $contact->setUtmTags($utmTags);
        }

        // Exclude fields from the accepted array that we overrode.
        if ($this->attribution) {
            unset($this->fieldsAccepted['attribution']);
        }
        if ($this->utmSource) {
            unset($this->fieldsAccepted['utm_source']);
        }
        ksort($this->fieldsAccepted);

        return $contact;
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
