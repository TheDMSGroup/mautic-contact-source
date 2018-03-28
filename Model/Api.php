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
use Mautic\CampaignBundle\Entity\Lead as CampaignContact;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\EmailBundle\Helper\EmailValidator;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\LeadBundle\Entity\UtmTag;
use Mautic\LeadBundle\Model\LeadModel as ContactModel;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use MauticPlugin\MauticContactSourceBundle\Entity\Stat;
use MauticPlugin\MauticContactSourceBundle\Event\ContactLedgerContextEvent;
use MauticPlugin\MauticContactSourceBundle\Exception\ContactSourceException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Api.
 */
class Api
{
    /** @var string */
    protected $status;

    /** @var int */
    protected $limits;

    /** @var int */
    protected $statusCode;

    /** @var ContactModel */
    protected $contactModel;

    /** @var ContactSource */
    protected $contactSource;

    /** @var Cache */
    protected $cacheModel;

    /** @var bool */
    protected $valid;

    /** @var Contact */
    protected $contact;

    /** @var array */
    protected $errors = [];

    /** @var array */
    protected $eventErrors = [];

    /** @var bool */
    protected $realTime;

    /** @var int */
    protected $scrubRate;

    /** @var int */
    protected $attribution;

    /** @var string */
    protected $utmSource;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    /** @var bool */
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

    /** @var int */
    protected $sourceId;

    /** @var int */
    protected $campaignId;

    /** @var bool */
    protected $verbose = false;

    /** @var string */
    protected $token;

    /** @var array */
    protected $events;

    /** @var Container */
    protected $container;

    /** @var array */
    protected $utmSetters;

    /** @var UtmTag */
    protected $utmTag;

    /** @var array */
    protected $allowedFields;

    /** @var \Mautic\EmailBundle\Helper\EmailValidator */
    protected $emailValidator;

    /** @var array */
    protected $allowedFieldEntities;

    /**
     * Api constructor.
     *
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Setting container instead of making this container aware for performance (DDoS mitigation).
     *
     * @param $container
     *
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * @param Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param int $sourceId
     *
     * @return $this
     */
    public function setSourceId($sourceId = null)
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    /**
     * @param int $campaignId
     *
     * @return $this
     */
    public function setCampaignId($campaignId = null)
    {
        $this->campaignId = $campaignId;

        return $this;
    }

    /**
     * @param bool $verbose
     *
     * @return $this
     */
    public function setVerbose($verbose = false)
    {
        $this->verbose = $verbose;

        return $this;
    }

    /**
     * Parse and validate for public access, without field and token validation.
     *
     * @return $this
     */
    public function handleInputPublic()
    {
        try {
            $this->parseSourceId();
            $this->parseSource();
            $this->parseSourceCampaignSettings();
            $this->parseCampaign();
        } catch (\Exception $exception) {
            $this->handleException($exception);
        }

        return $this;
    }

    /**
     * @throws ContactSourceException
     */
    private function parseSourceId()
    {
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

    /**
     * Find and validate the source matching our parameters.
     *
     * @return $this
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function parseSource()
    {
        if (null == $this->contactSource) {
            // Check Source existence and published status.
            $sourceModel         = $this->container->get('mautic.contactsource.model.contactsource');
            $this->contactSource = $sourceModel->getEntity($this->sourceId);
            if (!$this->contactSource) {
                throw new ContactSourceException(
                    'The sourceId specified does not exist.',
                    Codes::HTTP_NOT_FOUND,
                    null,
                    Stat::TYPE_INVALID,
                    'sourceId'
                );
            } elseif (false === $this->contactSource->getIsPublished()) {
                throw new ContactSourceException(
                    'The sourceId specified has been unpublished (deactivated).',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'sourceId'
                );
            }
        }

        return $this;
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
        $this->realTime    = (bool) isset($campaignSettings->realTime) && $campaignSettings->realTime;
        $this->limits      = isset($campaignSettings->limits) ? $campaignSettings->limits : [];
        $this->scrubRate   = isset($campaignSettings->scrubRate) ? intval($campaignSettings->scrubRate) : 0;
        $this->attribution = isset($campaignSettings->cost) ? (abs(floatval($campaignSettings->cost)) * -1) : 0;
        $this->utmSource   = !empty($this->contactSource->getUtmSource()) ? $this->contactSource->getUtmSource() : null;
        // Apply field overrides
        if (0 !== $this->attribution) {
            $this->fieldsProvided['attribution'] = $this->attribution;
        }
        if ($this->utmSource) {
            $this->fieldsProvided['utm_source'] = $this->utmSource;
        }
    }

    /**
     * Load and validate the campaign based on our parameters.
     *
     * @return $this
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function parseCampaign()
    {
        if (null == $this->campaign) {
            // Check Campaign existence and published status.
            /* @var Campaign $campaign */
            $this->campaign = $this->getCampaignModel()->getEntity($this->campaignId);
            if (!$this->campaign) {
                throw new ContactSourceException(
                    'The campaignId specified does not exist.',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            } elseif (false === $this->campaign->getIsPublished()) {
                throw new ContactSourceException(
                    'The campaignId specified has been unpublished (deactivated).',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
            }
        }

        return $this;
    }

    /**
     * @return CampaignModel|object
     *
     * @throws \Exception
     */
    private function getCampaignModel()
    {
        if (!$this->campaignModel) {
            /* @var CampaignModel */
            $this->campaignModel = $this->container->get('mautic.campaign.model.campaign');
        }

        return $this->campaignModel;
    }

    /**
     * @param \Exception $exception
     */
    private function handleException(\Exception $exception)
    {
        $field = null;
        $code  = $exception->getCode();
        if ($exception instanceof ContactSourceException) {
            if ($this->contact) {
                $exception->setContact($this->contact);
            }
            $status = $exception->getStatType();
            if ($status) {
                $this->status = $status;
            }
            $field = $exception->getField();
            if (is_integer($code) && $code) {
                // We'll use these as HTTP status codes.
                $this->statusCode = $code;
            }
        } elseif (!$this->statusCode) {
            // Unexpected exceptions should send 500.
            $this->statusCode = Codes::HTTP_INTERNAL_SERVER_ERROR;
        }
        $key = $field ? $field : $code;
        if ($key) {
            $this->errors[$key] = $exception->getMessage();
        } else {
            $this->errors[] = $exception->getMessage();
        }
    }

    /**
     * Given the needed parameters, import the contact if applicable.
     *
     * @return $this
     */
    public function validateAndImportContact()
    {
        $this->valid  = false;
        $this->status = Stat::TYPE_ERROR;

        try {
            $this->handleInputPrivate();
            $this->createContact();

            // @todo - Requirements - Evaluate required fields based on all the fields that are used in the campaign.
            // (This would best be done by cron, and cached somewhere as a list like campaign_required_fields)
            // (presuppose the overridden fields, if any)

            $this->evaluateLimits();

            // @todo - Duplicates - Evaluate Source duplicates against the cache. This is different than contact duplicates,
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
            $this->refund();
            $this->createCache();
        } catch (\Exception $exception) {
            $this->handleException($exception);
        }
        $this->logResults();

        return $this;
    }

    /**
     * Parse and validate all input for the API.
     *
     * @return $this
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function handleInputPrivate()
    {
        $this->parseFieldsProvided();
        $this->parseSourceId();
        $this->parseCampaignId();
        $this->parseToken();
        $this->parseSource();
        $this->validateToken();
        $this->parseSourceCampaignSettings();
        $this->parseCampaign();

        return $this;
    }

    /**
     * Capture a clean array input trimming all keys and values, excluding empties.
     * Throw exception if no fields provided.
     *
     * @return $this
     *
     * @throws ContactSourceException
     */
    private function parseFieldsProvided()
    {
        if (null === $this->fieldsProvided) {
            $fieldsProvided = [];
            foreach ($this->request->request->all() as $k => $v) {
                $k = trim($k);
                if ('' !== $k) {
                    $v = trim($v);
                    if ('' !== $v) {
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

        return $this;
    }

    /**
     * @throws ContactSourceException
     */
    private function parseCampaignId()
    {
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

    /**
     * Ensure the required parameters were provided and not empty while parsing.
     *
     * @throws ContactSourceException
     */
    private function parseToken()
    {
        // There are many ways to send a simple token... Let's support them all to be friendly to our Sources.
        $this->token = trim($this->request->get('token'));
        if (!$this->token) {
            $this->token = trim($this->request->headers->get('token'));
            if (!$this->token) {
                $this->token = trim($this->request->headers->get('X-Auth-Token'));
                if (!$this->token) {
                    $bearer = $this->request->headers->get('authorization');
                    if ($bearer) {
                        $this->token = trim(str_ireplace('Bearer ', '', $bearer));
                    }
                }
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
    }

    /**
     * @throws ContactSourceException
     */
    private function validateToken()
    {
        if ($this->token !== $this->contactSource->getToken()) {
            throw new ContactSourceException(
                'The token specified is invalid. Please request a new token.',
                Codes::HTTP_UNAUTHORIZED,
                null,
                Stat::TYPE_INVALID,
                'token'
            );
        }
    }

    /**
     * Generate a new contact entity (not yet saved so that we can use it for validations).
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function createContact()
    {
        // By this point we have already filtered empty values/keys.
        // Filter out disallowed fields to prevent errors with queries down the line.

        $allowedFields = $this->getAllowedFields();

        $disallowedFields = array_diff(array_keys($this->fieldsProvided), array_keys($allowedFields));
        foreach ($disallowedFields as $key) {
            // Help them out with a suggestion.
            $closest  = null;
            $shortest = -1;
            foreach (array_keys($allowedFields) as $allowedKey) {
                $lev = levenshtein($key, $allowedKey);
                if ($lev <= $shortest || $shortest < 0) {
                    $closest  = $allowedKey;
                    $shortest = $lev;
                }
            }
            unset($this->fieldsProvided[$key]);
            $msg = 'This field is not currently supported and was ignored.';
            if ($closest && isset($allowedKey)) {
                $msg .= ' Did you mean \''.$closest.'\' ('.$allowedFields[$closest].')?';
            }
            $this->errors[$key] = $msg;
        }

        // Move UTM tags to another array to avoid use in import, since it doesn't support them.
        $utmTagData = [];
        foreach ($this->getUtmSetters() as $k => $v) {
            if (isset($this->fieldsProvided[$k])) {
                $utmTagData[$k] = $this->fieldsProvided[$k];
                unset($this->fieldsProvided[$k]);
            }
        }

        // Must have at least ONE valid contact field (some are to be ignored since we provide them or they are core).
        $ignore = ['ip', 'attribution', 'attribution_date', 'utm_source'];
        if (!count(array_diff_key($this->fieldsProvided, array_flip($ignore)))) {
            return null;
        }

        // Dynamically generate the field map and import.
        // @todo - Discern and assign owner.
        $contact = $this->importContact(
            array_combine(array_keys($this->fieldsProvided), array_keys($this->fieldsProvided)),
            $this->fieldsProvided
        );

        if (!$contact) {
            return null;
        }

        // Accepted fields straight from the contact entity.
        $this->fieldsAccepted = $contact->getUpdatedFields();
        if (!count($this->fieldsAccepted)) {
            throw new ContactSourceException(
                'There were no valid fields needed to create a contact for this campaign.',
                Codes::HTTP_BAD_REQUEST,
                null,
                Stat::TYPE_INVALID
            );
        }
        if (isset($this->fieldsProvided['ip'])) {
            $this->fieldsAccepted['ip'] = $this->fieldsProvided['ip'];
        }

        // Cycle through calling appropriate setters if there is utm data.
        if (count($utmTagData)) {
            foreach ($this->getUtmSetters() as $q => $setter) {
                if (isset($utmTagData[$q])) {
                    $this->getUtmTag()->$setter($utmTagData[$q]);
                    $this->fieldsAccepted[$q] = $utmTagData[$q];
                }
            }

            // Set the UTM query from the URL if provided.
            if (isset($utmTagData['url'])) {
                parse_url($utmTagData['url'], PHP_URL_QUERY);
                parse_str(parse_url($utmTagData['url'], PHP_URL_QUERY), $query);
                $this->getUtmTag()->setQuery($query);
            }

            // Add date added, critical for inserts.
            $this->getUtmTag()->setDateAdded(new \DateTime());

            // Apply to the contact for save later.
            $this->getUtmTag()->setLead($contact);
            $contact->setUtmTags($this->getUtmTag());
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
        $contact->setNew();

        // Exclude fields from the accepted array that we overrode.
        if (0 !== $this->attribution) {
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
     * Retrieve a complete list of supported custom fields for import, including IP and UTM data.
     *
     * @param bool $asEntities
     *
     * @return array|null
     */
    public function getAllowedFields($asEntities = false)
    {
        if (null === $this->allowedFields) {
            try {
                /** @var \Mautic\LeadBundle\Model\FieldModel $fieldModel */
                $fieldModel = $this->container->get('mautic.lead.model.field');

                // Exclude company fields as they cannot be created/related on insert due to performance implications.
                $this->allowedFieldEntities = $fieldModel->getEntities(
                    [
                        'filter'         => [
                            'force' => [
                                [
                                    'column' => 'f.isPublished',
                                    'expr'   => 'eq',
                                    'value'  => true,
                                ],
                                [
                                    'column' => 'f.object',
                                    'expr'   => 'notLike',
                                    'value'  => 'company',
                                ],
                            ],
                        ],
                        'hydration_mode' => 'HYDRATE_ARRAY',
                    ]
                );

                // Also build an inclusive array for API and output.
                $allowedFields = [];
                foreach ($this->allowedFieldEntities as $field) {
                    $allowedFields[$field['alias']] = $field['label'];
                }
                // Add IP as an allowed import field.
                $allowedFields['ip'] = 'IP Addresses (comma delimited)';

                // Get available UTM fields and their setters.
                foreach ($this->getUtmSetters() as $q => $v) {
                    $allowedFields[$q] = str_replace('Utm', 'UTM', ucwords(str_replace('_', ' ', $q)));
                }

                uksort($allowedFields, 'strnatcmp');

                $this->allowedFields = $allowedFields;
            } catch (\Exception $exception) {
                $this->handleException($exception);
            }
        }

        return $asEntities ? $this->allowedFieldEntities : $this->allowedFields;
    }

    /**
     * Return all utm setters except query which is self-set.
     *
     * @return array
     */
    private function getUtmSetters()
    {
        if (null === $this->utmSetters) {
            $utmSetters = $this->getUtmTag()->getFieldSetterList();
            unset($utmSetters['query']);
            $this->utmSetters = $utmSetters;
        }

        return $this->utmSetters;
    }

    /**
     * @return UtmTag
     */
    private function getUtmTag()
    {
        if (null === $this->utmTag) {
            $this->utmTag = new UtmTag();
        }

        return $this->utmTag;
    }

    /**
     * This is a clone of the function \Mautic\LeadBundle\Model\LeadModel::import
     * With some changes for the sake of performance in real-time posting.
     *
     * @param      $fields
     * @param      $data
     * @param null $owner
     * @param null $list
     * @param null $tags
     *
     * @return bool|Contact
     *
     * @throws \Exception
     */
    public function importContact(
        $fields,
        $data,
        $owner = null,
        $list = null,
        $tags = null
    ) {
        $fields    = array_flip($fields);
        $fieldData = [];

        // Fields have already been cleaned by this point, so we can remove the helper.
        foreach ($fields as $leadField => $importField) {
            if (array_key_exists($importField, $data) && !is_null($data[$importField]) && '' != $data[$importField]) {
                $fieldData[$leadField] = $data[$importField];
            }
        }

        // Sources will not be able to set this on creation: Companies and their linkages.

        // These contacts are always going to be new.
        $contact = new Contact();

        if (!empty($fields['dateAdded']) && !empty($data[$fields['dateAdded']])) {
            $dateAdded = new DateTimeHelper($data[$fields['dateAdded']]);
            $contact->setDateAdded($dateAdded->getUtcDateTime());
        }
        unset($fieldData['dateAdded']);

        if (!empty($fields['dateModified']) && !empty($data[$fields['dateModified']])) {
            $dateModified = new DateTimeHelper($data[$fields['dateModified']]);
            $contact->setDateModified($dateModified->getUtcDateTime());
        }
        unset($fieldData['dateModified']);

        if (!empty($fields['lastActive']) && !empty($data[$fields['lastActive']])) {
            $lastActive = new DateTimeHelper($data[$fields['lastActive']]);
            $contact->setLastActive($lastActive->getUtcDateTime());
        }
        unset($fieldData['lastActive']);

        if (!empty($fields['dateIdentified']) && !empty($data[$fields['dateIdentified']])) {
            $dateIdentified = new DateTimeHelper($data[$fields['dateIdentified']]);
            $contact->setDateIdentified($dateIdentified->getUtcDateTime());
        }
        unset($fieldData['dateIdentified']);

        // Sources will not be able to set this on creation: createdByUser
        unset($fieldData['createdByUser']);

        // Sources will not be able to set this on creation: modifiedByUser
        unset($fieldData['modifiedByUser']);

        if (!empty($fields['ip']) && !empty($data[$fields['ip']])) {
            $addresses = explode(',', $data[$fields['ip']]);
            foreach ($addresses as $address) {
                $ipAddress = new IpAddress();
                $ipAddress->setIpAddress(trim($address));
                $contact->addIpAddress($ipAddress);
            }
        }
        unset($fieldData['ip']);

        // Sources will not be able to set this on creation: points

        // Sources will not be able to set this on creation: stage
        unset($fieldData['stage']);

        // Sources will not be able to set this on creation: doNotEmail
        unset($fieldData['doNotEmail']);

        // Sources will not be able to set this on creation: ownerusername
        unset($fieldData['ownerusername']);

        if (null !== $owner) {
            $contact->setOwner($this->getContactModel()->getReference('MauticUserBundle:User', $owner));
        }

        if (null !== $tags) {
            $this->getContactModel()->modifyTags($contact, $tags, null, false);
        }

        foreach ($this->getAllowedFields(true) as $contactField) {
            if (isset($fieldData[$contactField['alias']])) {
                if ('NULL' === $fieldData[$contactField['alias']]) {
                    $fieldData[$contactField['alias']] = null;
                    continue;
                }
                try {
                    $this->getContactModel()->cleanFields($fieldData, $contactField);
                    if ('email' === $contactField['type'] && !empty($fieldData[$contactField['alias']])) {
                        $this->getEmailValidator()->validate($fieldData[$contactField['alias']], false);
                    }
                } catch (\Exception $exception) {
                    throw new ContactSourceException(
                        $exception->getMessage(),
                        Codes::HTTP_BAD_REQUEST,
                        $exception,
                        Stat::TYPE_INVALID,
                        $contactField['alias']
                    );
                }
                continue;
            } elseif ($contactField['defaultValue']) {
                // Fill in the default value if any.
                $fieldData[$contactField['alias']] = ('multiselect' === $contactField['type']) ? [$contactField['defaultValue']] : $contactField['defaultValue'];
            }
        }

        // All clear.
        foreach ($fieldData as $field => $value) {
            $contact->addUpdatedField($field, $value);
        }
        $contact->imported = true;

        return $contact;
    }

    /**
     * Return our extended contact model.
     *
     * @return ContactModel|object
     *
     * @throws \Exception
     */
    private function getContactModel()
    {
        if (!$this->contactModel) {
            /* @var ContactModel */
            $this->contactModel = $this->container->get('mautic.lead.model.lead');
        }

        return $this->contactModel;
    }

    /**
     * @return EmailValidator|object
     *
     * @throws \Exception
     */
    private function getEmailValidator()
    {
        if (!$this->emailValidator) {
            $this->emailValidator = $this->container->get('mautic.validator.email');
        }

        return $this->emailValidator;
    }

    /**
     * Evaluate Source & Campaign limits using the Cache.
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function evaluateLimits()
    {
        $limitRules        = new \stdClass();
        $limitRules->rules = $this->limits;

        $this->getCacheModel()->evaluateLimits($limitRules, $this->campaignId);
    }

    /**
     * @return Cache
     *
     * @throws \Exception
     */
    private function getCacheModel()
    {
        if (!$this->cacheModel) {
            /* @var \MauticPlugin\MauticContactSourceBundle\Model\Cache $cacheModel */
            $this->cacheModel = $this->container->get('mautic.contactsource.model.cache');
            $this->cacheModel->setContact($this->contact);
            $this->cacheModel->setContactSource($this->contactSource);
        }

        return $this->cacheModel;
    }

    /**
     * @throws ContactSourceException
     */
    private function saveContact()
    {
        $exception    = null;
        $this->status = Stat::TYPE_SAVING;
        $this->dispatchContextCreate();
        try {
            $this->getContactModel()->saveEntity($this->contact);
        } catch (\Exception $exception) {
        }
        if ($exception || !$this->contact->getId()) {
            throw new ContactSourceException(
                'Could not confirm the contact was saved.',
                Codes::HTTP_INTERNAL_SERVER_ERROR,
                $exception,
                Stat::TYPE_ERROR
            );
        }
        $this->status = Stat::TYPE_SAVED;
    }

    /**
     * Provide context to Ledger plugin (or others) about this contact for save events.
     */
    private function dispatchContextCreate()
    {
        $event = new ContactLedgerContextEvent(
            $this->campaign, $this->contactSource, $this->status, 'New contact is being created', $this->contact
        );
        $this->dispatcher->dispatch(
            'mautic.contactledger.context_create',
            $event
        );
    }

    /**
     * Feed a contact to a campaign. If real-time is enabled, skip event dispatching to prevent recursion.
     *
     * @return $this
     *
     * @throws \Exception
     */
    private function addContactToCampaign()
    {
        if ($this->contact->getId()) {
            // Add the contact directly to the campaign without duplicate checking.
            $this->addContactsToCampaign($this->campaign, [$this->contact], false, $this->realTime);
            $this->status = Stat::TYPE_QUEUED;
        }

        return $this;
    }

    /**
     * Add contact to a campaign, and optionally run in real-time.
     *
     * @param Campaign $campaign
     * @param array    $contacts
     * @param bool     $manuallyAdded
     * @param bool     $realTime
     *
     * @throws \Exception
     */
    public function addContactsToCampaign(
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
            $saved = $this->getCampaignModel()->saveCampaignLead($campaignContact);

            // @todo - Support non realtime event firing.
            // if (!$realTime) {
            //     // Only trigger events if not in realtime where events would be followed directly.
            //     if ($saved && $this->getCampaignModel()->hasListeners(CampaignEvents::CAMPAIGN_ON_LEADCHANGE)) {
            //         $event = new CampaignLeadChangeEvent($campaign, $contact, 'added');
            //         $this->dispatcher->dispatch(CampaignEvents::CAMPAIGN_ON_LEADCHANGE, $event);
            //         unset($event);
            //     }
            //
            //     // Detach to save memory
            //     $this->em->detach($campaignContact);
            //     unset($campaignContact);
            // }
        }
        unset($campaign, $campaignContact, $contacts);
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
                $this->valid  = false;
            } else {
                // Asynchronous acceptance.
                $this->status = Stat::TYPE_ACCEPT;
                $this->valid  = true;
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
        if (null === $this->scrubbed) {
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
            $campaignResult     = $campaignEventModel->triggerContactStartingEvents(
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
                foreach ($campaignResult['contactClientEvents'][$this->contact->getId()] as $eventId => $event) {
                    if (!empty($event['error'])) {
                        $eventName = !empty($event['name']) ? $event['name'] : '';
                        if (!is_array($event['error'])) {
                            $event['error'] = [$event['error']];
                        }
                        $this->eventErrors[$eventId] = $eventName.' ('.$eventId.'): '.implode(', ', $event['error']);
                    }
                    if (isset($event['valid']) && $event['valid']) {
                        // One valid Contact Client was found to accept the lead.
                        $this->status = Stat::TYPE_ACCEPT;
                        $this->valid  = true;
                        break;
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
                $this->valid  = false;
            }
        }
    }

    /**
     * Invert the original attribution if we did not accept the lead (for any reason) and an attribution was given.
     * The end result may NOT balance out to 0, as we may have run through campaign actions that
     * had costs/values associated. We are only reversing the original attribution value.
     *
     * @throws \Exception
     */
    private function refund()
    {
        if (0 == $this->attribution) {
            return;
        }
        if ($this->status !== Stat::TYPE_ACCEPT) {
            $originalAttribution = $this->contact->getAttribution();
            $newAttribution      = $originalAttribution + ($this->attribution * -1);
            if ($newAttribution != $originalAttribution) {
                $this->contact->addUpdatedField(
                    'attribution',
                    $newAttribution
                );
                $this->dispatchContextCreate();
                try {
                    $this->getContactModel()->saveEntity($this->contact);
                } catch (\Exception $exception) {
                    throw new ContactSourceException(
                        'Could not confirm the contact was saved (ref).',
                        Codes::HTTP_INTERNAL_SERVER_ERROR,
                        $exception,
                        Stat::TYPE_ERROR
                    );
                }
            }
        }
    }

    /**
     * Create cache entry if a contact was created, used for duplicate checking and limits (with final attribution).
     *
     * @throws \Exception
     */
    private function createCache()
    {
        if ($this->contact->getId()) {
            $this->getCacheModel()
                ->setContact($this->contact)
                ->setContactSource($this->contactSource)
                ->create($this->campaignId);
        }
    }

    /**
     * Use LeadTimelineEvent.
     */
    private function logResults()
    {
        /** @var ContactSourceModel $sourceModel */
        $sourceModel = $this->container->get('mautic.contactsource.model.contactsource');

        if ($this->valid) {
            $statLevel = 'INFO';
            $message   = 'Contact '.$this->contact->getId(
                ).' was imported successfully from Campaign: '.$this->campaign->getname();
        } else {
            $statLevel = 'ERROR';
            $message   = isset($this->errors) ? implode(PHP_EOL, $this->errors) : '';
            if ($this->eventErrors) {
                $message = implode(PHP_EOL.'  ', $this->eventErrors);
            }
        }

        // Session storage for external plugins (should probably be dispatcher instead).
        $session = $this->container->get('session');
        // Indicates that a single (or more) valid sends have been made.
        if ($this->valid) {
            $session->set('contactsource_valid', true);
        }
        // get the campaign if exists
        $campaignId = !empty($this->campaignId) ? $this->campaignId : 0;

        // Add log entry for statistics / charts.
        $attribution = !empty($this->attribution) ? $this->attribution : 0;
        $sourceModel->addStat($this->contactSource, $this->status, $this->contact, $attribution, $campaignId);
        $log     = [
            'status'         => $this->status,
            'fieldsAccepted' => $this->fieldsAccepted,
            'fieldsProvided' => $this->fieldsProvided,
            'realTime'       => $this->realTime,
            'scrubbed'       => $this->scrubbed,
            'utmSource'      => $this->utmSource,
            'campaign'       => $this->campaign,
            'contact'        => $this->contact,
            'events'         => $this->events,
        ];
        $logYaml = Yaml::dump($log, 10, 2);

        // Add transactional event for deep dive into logs.
        $sourceModel->addEvent(
            $this->contactSource,
            $this->status,
            $this->contact,
            $logYaml, // kinda just made up a log
            $message
        );
    }

    /**
     * Get the result array of the import process.
     *
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
            $result['campaign']         = [];
            $result['campaign']['id']   = $this->campaign->getId();
            $result['campaign']['name'] = $this->campaign->getName();
            if ($this->verbose) {
                $result['campaign']['description'] = $this->campaign->getDescription();
                $result['campaign']['category']    = $this->campaign->getCategory();
            }
        }
        if ($this->fieldsAccepted) {
            $result['fields'] = $this->fieldsAccepted;
        }
        if ($this->contactSource) {
            $result['source']         = [];
            $result['source']['id']   = $this->contactSource->getId();
            $result['source']['name'] = $this->contactSource->getName();
            if ($this->verbose) {
                $result['source']['description']   = $this->contactSource->getDescriptionPublic();
                $result['source']['category']      = $this->contactSource->getCategory();
                $result['source']['documentation'] = $this->contactSource->getDocumentation();
            }
        }
        if ($this->verbose && $this->utmSource) {
            $result['utmSource'] = $this->utmSource;
        }
        $result['success'] = $this->valid;

        /*
         * Optionally include debug data.
         *
         * @deprecated
         */
        if ($this->verbose) {
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
            $result['contact']       = $this->fieldsAccepted;
            $result['contact']['id'] = $this->contact->getId();
        }

        $result['statusCode'] = $this->statusCode;

        if ($this->verbose) {
            $result['allowedFields'] = $this->getAllowedFields();
        }

        return $result;
    }
}
