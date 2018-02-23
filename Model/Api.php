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

use FOS\RestBundle\Util\Codes;
use Mautic\CampaignBundle\Entity\Campaign;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Mautic\LeadBundle\Entity\Lead as Contact;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use MauticPlugin\MauticContactSourceBundle\Entity\Stat;
use MauticPlugin\MauticContactSourceBundle\Exception\ContactSourceException;
use Mautic\LeadBundle\Entity\UtmTag;

/**
 * Class Api
 * @package MauticPlugin\MauticContactSourceBundle\Model
 */
class Api
{

    /** @var string */
    protected $status;

    /** @var integer */
    protected $statusCode;

    /** @var ContactModel */
    protected $contactModel;

    /** @var ContactSource */
    protected $contactSource;

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

    /** @var array */
    protected $fieldsProvided;

    /** @var Request */
    protected $request;

    /** @var integer */
    protected $sourceId;

    /** @var integer */
    protected $campaignId;

    /** @var boolean */
    protected $debug;

    /** @var string */
    protected $token;

    /** @var array */
    protected $events;

    /** @var Container */
    protected $container;

    /**
     * Setting container instead of making this container aware for performance (DDoS mitigation)
     *
     * @param $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param integer $sourceId
     * @return $this
     */
    public function setSourceId($sourceId = null)
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    /**
     * @param integer $campaignId
     * @return $this
     */
    public function setCampaignId($campaignId = null)
    {
        $this->campaignId = $campaignId;

        return $this;
    }

    /**
     * @param bool $debug
     * @return $this
     */
    public function setDebug($debug = false)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Given the needed parameters, import the contact if applicable.
     *
     * @return $this
     */
    public function validateAndImportContact()
    {
        $this->valid = false;

        try {
            $this->parseFieldsProvided();

            $this->parseParameters();

            $this->getSource();

            $this->parseSourceCampaignSettings();

            $this->getCampaign();

            $this->createContact();

            // @todo - Evaluate required fields based on all the fields that are used in the campaign.
            // (This would best be done by cron, and cached somewhere as a list like campaign_required_fields)
            // (presuppose the overridden fields, if any)

            // @todo - Evaluate Source & Campaign limits using the Cache (method doesn't yet exist for either contact/client).
            // $this->getCacheModel()->evaluateLimits();

            // @todo - Evaluate Source duplicates against the cache. This is different than contact duplicates,
            // as we only care about duplicates within the source. It is unsustainable to check against all contacts
            // ever received by Mautic, so we only check for duplicates received by this source within a time frame.

            $this->saveContact();

            // @todo - Optionally allow a segment to be targeted instead of a campaign in the future? No problem...
            // /** @var \Mautic\LeadBundle\Model\ListModel $leadListModel */
            // $leadListModel = $this->container->get('mautic.lead.model.list');
            // $leadListModel->addLead($this->contact, [LeadList $list], true, !$this->realTime, -1);

            $this->addContactToCampaign();

            $this->processOffline();

            $this->processRealTime();

            $this->reverseScrub();

            $this->createCache();

        } catch (\Exception $e) {

            $field = null;
            $code = $e->getCode();
            if ($e instanceof ContactSourceException) {
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

        return $this;
    }

    /**
     * Capture a clean array input trimming all keys and values, excluding empties.
     * Throw exception if no fields provided.
     *
     * @return array
     * @throws ContactSourceException
     */
    private function parseFieldsProvided()
    {
        if ($this->fieldsProvided === null) {
            $fieldsProvided = [];
            foreach ($this->request->request->all() as $k => $v) {
                $k = trim($k);
                if ($k !== '') {
                    $v = trim($v);
                    if ($v !== '') {
                        $fieldsProvided[$k] = $v;
                    }
                }
            }
            // Some inputs do not pertain to contact fields.
            unset($fieldsProvided['token'], $fieldsProvided['sourceId'], $fieldsProvided['campaignId']);
            $this->fieldsProvided = $fieldsProvided;
        }
        if (!count($this->fieldsProvided)) {
            throw new ContactSourceException(
                'No fields were posted. Did you mean to do that?',
                Codes::HTTP_I_AM_A_TEAPOT,
                null,
                Stat::TYPE_INVALID
            );
        }

        return $this->fieldsProvided;
    }

    /**
     * Ensure the required parameters were provided and not empty while parsing.
     * @throws ContactSourceException
     */
    private function parseParameters()
    {
        $this->token = $this->request->get('token');
        if (!$this->token) {
            $this->token = $this->request->headers->get('token');
            if (!$this->token) {
                $this->token = $this->request->headers->get('X-Auth-Token');
                if (!$this->token) {
                    throw new ContactSourceException(
                        'The token was not supplied. Please provide your authentication token.',
                        Codes::HTTP_UNAUTHORIZED,
                        null,
                        Stat::TYPE_INVALID,
                        'token'
                    );
                }
            }
        }
        $this->sourceId = intval($this->sourceId);
        if (!$this->sourceId) {
            $this->sourceId = intval($this->request->get('sourceId'));
            if (!$this->sourceId) {
                throw new ContactSourceException(
                    'The sourceId was not supplied. Please provide your sourceId.',
                    Codes::HTTP_BAD_REQUEST,
                    null,
                    Stat::TYPE_INVALID,
                    'sourceId'
                );
            }
        }
        $this->campaignId = intval($this->campaignId);
        if (!$this->campaignId) {
            $this->campaignId = intval($this->request->get('campaignId'));
            if (!$this->campaignId) {
                throw new ContactSourceException(
                    'The campaignId was not supplied. Please provide your campaignId.',
                    Codes::HTTP_BAD_REQUEST,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            }
        }
    }

    /**
     * Find and validate the source matching our parameters.
     *
     * @return ContactSource
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function getSource()
    {
        if ($this->contactSource == null) {
            // Check Source existence and published status.
            $sourceModel = $this->container->get('mautic.contactsource.model.contactsource');
            $this->contactSource = $sourceModel->getEntity($this->sourceId);
            if (!$this->contactSource) {
                throw new ContactSourceException(
                    'The sourceId specified does not exist.',
                    Codes::HTTP_NOT_FOUND,
                    null,
                    Stat::TYPE_INVALID,
                    'sourceId'
                );
            } elseif ($this->contactSource->getIsPublished() === false) {
                throw new ContactSourceException(
                    'The sourceId specified has been unpublished (deactivated).',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'sourceId'
                );
            } elseif ($this->token !== $this->contactSource->getToken()) {
                throw new ContactSourceException(
                    'The token specified is invalid. Please request a new token.',
                    Codes::HTTP_UNAUTHORIZED,
                    null,
                    Stat::TYPE_INVALID,
                    'token'
                );
            }
        }

        return $this->contactSource;
    }

    /**
     * Load the settings attached to the Source.
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function parseSourceCampaignSettings()
    {
        // Check that the campaign is in the whitelist for this source.
        /** @var CampaignSettings $campaignSettingsModel */
        $campaignSettingsModel = $this->container->get('mautic.contactsource.model.campaign_settings');
        $campaignSettingsModel->setContactSource($this->contactSource);
        $campaignSettings = $campaignSettingsModel->getCampaignSettingsById($this->campaignId);

        // @todo - Support or thwart multiple copies of the same campaign, should it occur. In the meantime...
        $campaignSettings = reset($campaignSettings);
        if (!$campaignSettings) {
            throw new ContactSourceException(
                'The campaignId supplied is not currently in the permitted list of campaigns for this source.',
                Codes::HTTP_GONE,
                null,
                Stat::TYPE_INVALID,
                'campaignId'
            );
        }
        // Establish parameters from campaign settings.
        $this->realTime = (boolean)isset($campaignSettings->realTime) && $campaignSettings->realTime;
        $this->scrubRate = isset($campaignSettings->scrubRate) ? intval($campaignSettings->scrubRate) : 0;
        $this->attribution = isset($campaignSettings->cost) ? (abs(intval($campaignSettings->cost)) * -1) : 0;
        $this->utmSource = !empty($campaignSettings->utmSource) ? $campaignSettings->utmSource : null;
        // Apply field overrides
        if ($this->attribution !== 0) {
            $this->fieldsProvided['attribution'] = $this->attribution;
        }
        if ($this->utmSource) {
            $this->fieldsProvided['utm_source'] = $this->utmSource;
        }
    }

    /**
     * Load and validate the campaign based on our parameters.
     *
     * @return Campaign|null
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function getCampaign()
    {
        if ($this->campaign == null) {
            // Check Campaign existence and published status.
            /** @var Campaign $campaign */
            $this->campaign = $this->getCampaignModel()->getEntity($this->campaignId);
            if (!$this->campaign) {
                throw new ContactSourceException(
                    'The campaignId specified does not exist.',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            } elseif ($this->campaign->getIsPublished() === false) {
                throw new ContactSourceException(
                    'The campaignId specified has been unpublished (deactivated).',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            }
        }

        return $this->campaign;

    }

    /**
     * Get our customized Campaign model.
     *
     * @return CampaignModel|object
     * @throws \Exception
     */
    private function getCampaignModel()
    {
        if (!$this->campaignModel) {
            /** @var CampaignModel */
            $this->campaignModel = $this->container->get('mautic.contactsource.model.campaign');
        }

        return $this->campaignModel;
    }

    /**
     * Generate a new contact entity (not yet saved so that we can use it for validations).
     *
     * @throws ContactSourceException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    private function createContact()
    {
        // By this point we have already filtered empty values/keys.
        // Filter out disallowed fields to prevent errors with queries down the line.
        /** @var \Mautic\LeadBundle\Model\FieldModel $fieldModel */
        $fieldModel = $this->container->get('mautic.lead.model.field');
        $allowedFields = $fieldModel->getFieldList(true, true, ['isPublished' => true]);
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

        $disallowedFields = array_diff(array_keys($this->fieldsProvided), array_keys($allowedFields));
        foreach ($disallowedFields as $key) {
            // Help them out with a suggestion.
            $closest = null;
            $shortest = -1;
            foreach (array_keys($allowedFields) as $allowedKey) {
                $lev = levenshtein($key, $allowedKey);
                if ($lev <= $shortest || $shortest < 0) {
                    $closest = $allowedKey;
                    $shortest = $lev;
                }
            }
            unset($this->fieldsProvided[$key]);
            $msg = 'This field is not currently supported and was ignored.';
            if ($closest && isset($allowedKey)) {
                $msg .= ' Did you mean \''.$closest.'\' ('.$allowedFields[$closest].')?';
            }
            $this->errors[$key] = $msg;

            /**
             * Provide the list of allowed fields in debug mode.
             * @deprecated
             */
            if ($this->debug && !isset($this->errors['allowedFields'])) {
                ksort($allowedFields);
                $this->errors['allowedFields'] = $allowedFields;
            }
        }

        // Move UTM tags to another array to avoid use in import, since it doesn't support them.
        $utmTagData = [];
        foreach ($utmSetters as $k => $v) {
            if (isset($this->fieldsProvided[$k])) {
                $utmTagData[$k] = $this->fieldsProvided[$k];
                unset($this->fieldsProvided[$k]);
            }
        }

        // Must have at least ONE valid contact field (some are to be ignored since we provide them or they are core).
        $ignore = ['ip', 'attribution', 'utm_source'];
        if (!count(array_diff_key($this->fieldsProvided, array_flip($ignore)))) {
            return null;
        }

        // Dynamically generate the field map and import.
        // @todo - Discern and assign owner.
        $contact = $this->getContactModel()->import(
            array_combine(array_keys($this->fieldsProvided), array_keys($this->fieldsProvided)),
            $this->fieldsProvided
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
        if (isset($this->fieldsProvided['ip'])) {
            $this->fieldsAccepted['ip'] = $this->fieldsProvided['ip'];
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

        // Done importing fields, let's make sure the entity was made.
        if (!$contact) {
            throw new ContactSourceException(
                'Not enough valid data was provided to create a contact for this campaign.',
                Codes::HTTP_BAD_REQUEST,
                null,
                Stat::TYPE_INVALID
            );
        }
        $contact->setNew(true);

        // Exclude fields from the accepted array that we overrode.
        if ($this->attribution !== 0) {
            unset($this->fieldsAccepted['attribution']);
        }
        if ($this->utmSource) {
            unset($this->fieldsAccepted['utm_source']);
        }

        // Sort the accepted fields for a nice output.
        ksort($this->fieldsAccepted);

        $this->contact = $contact;
    }

    /**
     * Return our extended contact model.
     *
     * @return ContactModel|object
     * @throws \Exception
     */
    private function getContactModel()
    {
        if (!$this->contactModel) {
            $this->contactModel = $this->container->get('mautic.contactsource.model.contact');
        }

        return $this->contactModel;
    }

    /**
     * Save the contact entity to the database.
     *
     * @return $this
     * @throws \Exception
     */
    private function saveContact()
    {
        $this->getContactModel()
            ->saveEntity($this->contact);
        $this->status = Stat::TYPE_SAVED;

        return $this;
    }

    /**
     * Feed a contact to a campaign. If real-time is enabled, skip event dispatching to prevent recursion.
     *
     * @return $this
     * @throws \Exception
     */
    private function addContactToCampaign()
    {
        if ($this->contact->getId()) {
            // Add the contact directly to the campaign without duplicate checking.
            $this->getCampaignModel()
                ->addContact($this->campaign, $this->contact, false, $this->realTime);
            $this->status = Stat::TYPE_QUEUED;
        }

        return $this;
    }

    /**
     * Assign the status for an offline (not real-time) contact acceptance/rejection.
     */
    private function processOffline()
    {
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
    }

    /**
     * Establish scrub status on first execution and keep it.
     *
     * @return bool
     */
    public function isScrubbed()
    {
        if ($this->scrubbed === null) {
            $this->scrubbed = $this->scrubRate > rand(0, 99);
        }

        return $this->scrubbed;
    }

    /**
     * If real-time mode is enabled, process the lead through the campaign now,
     * to discern acceptance or rejection based upon the Clients in the workflow.
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    private function processRealTime()
    {
        if ($this->realTime) {
            // Synchronous acceptance or denial.
            // Step through the campaign model events to define status.
            $totalEventCount = 0;
            /** @var CampaignEventModel $campaignEventModel */
            $campaignEventModel = $this->container->get('mautic.contactsource.model.campaign_event');
            $campaignResult = $campaignEventModel->triggerContactStartingEvents(
                $this->campaign,
                $totalEventCount,
                [$this->contact]
            );

            // Sync (real-time): Evaluate the result of the campaign workflow and return status.
            if (
                $campaignResult
                && !empty($campaignResult['contactClientEvents'])
                && !empty($campaignResult['contactClientEvents'][$this->contact->getId()])
            ) {
                $this->events = $campaignResult['contactClientEvents'][$this->contact->getId()];
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
    }

    /**
     * Invert the original attribution if we have been scrubbed and an attribution was given.
     * Not the end result may NOT balance out to 0, as we may have run through campaign actions that
     * had costs/values associated. We are only reversing the original value we applied.
     */
    private function reverseScrub()
    {
        if ($this->isScrubbed() && $this->attribution !== 0) {
            $originalAttribution = $this->contact->getAttribution();
            $newAttribution = $originalAttribution + ($this->attribution * -1);
            if ($newAttribution != $originalAttribution) {
                $this->contact->addUpdatedField(
                    'attribution',
                    $newAttribution
                );
                $this->getContactModel()->saveEntity($this->contact);
            }
        }
    }

    /**
     * Create cache entry if a contact was created, used for duplicate checking and limits (with final attribution)
     *
     * @throws \Exception
     */
    private function createCache()
    {
        if ($this->contact->getId()) {
            $this->getCacheModel()
                ->setContact($this->contact)
                ->setContactSource($this->contactSource)
                ->create();
        }
    }

    /**
     * @return Cache
     * @throws \Exception
     */
    private function getCacheModel()
    {
        if (!$this->cacheModel) {
            /** @var \MauticPlugin\MauticContactSourceBundle\Model\Cache $cacheModel */
            $this->cacheModel = $this->container->get('mautic.contactsource.model.cache');
            $this->cacheModel->setContact($this->contact);
            $this->cacheModel->setContactSource($this->contactSource);
        }

        return $this->cacheModel;
    }

    /**
     * Get the result array of the import process.
     * @return array
     */
    public function getResult()
    {
        $result = [];

        // Parse the response.
        if ($this->valid && $this->attribution) {
            // Attribution in this context is the revenue/cost for the third party.
            $result['attribution'] = $this->attribution;
        }
        if ($this->campaign) {
            $result['campaignId'] = $this->campaign->getId();
        }
        if ($this->fieldsAccepted) {
            $result['fields'] = $this->fieldsAccepted;
        }
        if ($this->contactSource) {
            $result['sourceId'] = $this->contactSource->getId();
        }
        if ($this->utmSource) {
            $result['utmSource'] = $this->utmSource;
        }
        $result['success'] = $this->valid;

        /**
         * Optionally include debug data.
         * @deprecated
         */
        if ($this->debug) {
            $result['status'] = $this->status;
            if ($this->events) {
                $result['events'] = $this->events;
            }
        }
        // Append errors to the response if given.
        if ($this->errors) {
            $result['errors'] = $this->errors;
        }

        if ($this->contact && $this->valid && $this->fieldsAccepted) {
            // This is a simplified output of the "Contact"
            // It is a flat array, containing only the fields that we accepted and used to create the contact.
            // It does not include the same entity structure as you would find in the core API.
            // This is intentional, since we do not necessarily want the third party to have access to all data,
            // that has been appended to the contact during the ingestion process.
            $result['contact'] = $this->fieldsAccepted;
            $result['contact']['id'] = $this->contact->getId();
        }

        $result['statusCode'] = $this->statusCode;

        return $result;
    }
}