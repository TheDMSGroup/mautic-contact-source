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

use Doctrine\ORM\EntityManager;
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
use Mautic\PluginBundle\Entity\IntegrationEntity;
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
    protected $attribution = 0;

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
    protected $fieldsStored;

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

    /** @var array */
    protected $logs = [];

    /** @var EntityManager */
    protected $em;

    /**
     * Api constructor.
     *
     * @param EventDispatcherInterface $dispatcher
     * @param EntityManager            $em
     */
    public function __construct(EventDispatcherInterface $dispatcher, EntityManager $em)
    {
        $this->dispatcher = $dispatcher;
        $this->em         = $em;
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
            $this->applyAttribution();
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
        $this->parseVerbosity();
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
     * Evaluate the "verbose" header if provided, to match against configuration.
     */
    private function parseVerbosity()
    {
        $verboseHeader = $this->request->headers->get('verbose');
        if (null !== $verboseHeader) {
            // The default key is 1, for BC.
            $verboseKey = '1';
            /** @var \Mautic\PluginBundle\Helper\IntegrationHelper $helper */
            $helper = $this->dispatcher->getContainer()->get('mautic.helper.integration');
            /** @var \Mautic\PluginBundle\Integration\AbstractIntegration $object */
            $object = $helper->getIntegrationObject('Source');
            if ($object) {
                $objectSettings = $object->getIntegrationSettings();
                if ($objectSettings) {
                    $featureSettings = $objectSettings->getFeatureSettings();
                    if (!empty($featureSettings['verbose'])) {
                        $verboseKey = $featureSettings['verbose'];
                    }
                }
            }
            $verbose = $verboseHeader == $verboseKey;
            if ($verbose) {
                $this->setVerbose(true);
            } else {
                throw new ContactSourceException(
                    'The verbose token passed was not correct. This field should only be used for debugging.',
                    Codes::HTTP_UNAUTHORIZED,
                    null,
                    Stat::TYPE_INVALID,
                    'verbose'
                );
            }
        }
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
        $this->fieldsStored = $contact->getUpdatedFields();
        if (!count($this->fieldsStored)) {
            throw new ContactSourceException(
                'There were no valid fields needed to create a contact for this campaign.',
                Codes::HTTP_BAD_REQUEST,
                null,
                Stat::TYPE_INVALID
            );
        }
        if (isset($this->fieldsProvided['ip'])) {
            $this->fieldsStored['ip'] = $this->fieldsProvided['ip'];
        }

        // Cycle through calling appropriate setters if there is utm data.
        if (count($utmTagData)) {
            foreach ($this->getUtmSetters() as $q => $setter) {
                if (isset($utmTagData[$q])) {
                    $this->getUtmTag()->$setter($utmTagData[$q]);
                    $this->fieldsStored[$q] = $utmTagData[$q];
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

        // Sort the accepted fields for a nice output.
        ksort($this->fieldsStored);

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

                unset($allowedFields['attribution']);
                unset($allowedFields['attribution_date']);

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
     * Apply attribution if we accepted the lead.
     *
     * @throws \Exception
     */
    private function applyAttribution()
    {
        if (0 == $this->attribution) {
            return;
        }

        if (Stat::TYPE_ACCEPT === $this->status) {
            $originalAttribution = $this->contact->getAttribution();
            // Attribution is always a negative number to represent cost.
            $newAttribution = $originalAttribution + $this->attribution;
            if ($newAttribution != $originalAttribution) {
                $this->contact->addUpdatedField(
                    'attribution',
                    $newAttribution
                );
                $this->dispatchContextCreate();
                $this->getContactModel()->saveEntity($this->contact);
            }
        } else {
            // Since we did NOT accept the lead, invalidate attribution.
            $this->attribution = 0;
        }
    }

    /**
     * Create cache entry if a contact was created, used for duplicate checking and limits.
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
        $sourceModel->addStat($this->contactSource, $this->status, $this->contact, $this->attribution, $campaignId);
        $this->logs = array_merge(
            $this->logs,
            [
                'status'         => $this->status,
                'fieldsProvided' => $this->fieldsProvided,
                'fieldsStored'   => $this->fieldsStored,
                'realTime'       => $this->realTime,
                'scrubbed'       => $this->scrubbed,
                'utmSource'      => $this->utmSource,
                'campaign'       => $this->campaign ? $this->campaign->convertToArray() : null,
                'contact'        => $this->contact ? $this->contact->convertToArray() : null,
                'events'         => $this->events,
            ]
        );

        // Add transactional event for deep dive into logs.
        $sourceModel->addEvent(
            $this->contactSource,
            $this->status,
            $this->contact,
            Yaml::dump($this->logs, 10, 2),
            $message
        );

        // Integration entity creation (shows up under Integrations in a Contact).
        if ($this->contact && $this->contact->getId()) {
            $integrationEntities = [
                $this->saveSyncedData(
                    'Source',
                    'ContactSource',
                    $this->contactSource->getId(),
                    $this->contact
                ),
            ];
            if (!empty($integrationEntities)) {
                $this->em->getRepository('MauticPluginBundle:IntegrationEntity')->saveEntities($integrationEntities);
                $this->em->clear('Mautic\PluginBundle\Entity\IntegrationEntity');
            }
        }
    }

    /**
     * Create a new integration record (we are never updating here).
     *
     * @param        $integrationName
     * @param        $integrationEntity
     * @param        $integrationEntityId
     * @param        $entity
     * @param string $internalEntityType
     * @param null   $internalData
     *
     * @return IntegrationEntity
     */
    private function saveSyncedData(
        $integrationName,
        $integrationEntity,
        $integrationEntityId,
        $entity,
        $internalEntityType = 'lead',
        $internalData = null
    ) {
        /** @var IntegrationEntity $newIntegrationEntity */
        $newIntegrationEntity = new IntegrationEntity();
        $newIntegrationEntity->setDateAdded(new \DateTime());
        $newIntegrationEntity->setIntegration($integrationName);
        $newIntegrationEntity->setIntegrationEntity($integrationEntity);
        $newIntegrationEntity->setIntegrationEntityId($integrationEntityId);
        $newIntegrationEntity->setInternalEntity($internalEntityType);
        $newIntegrationEntity->setInternalEntityId($entity->getId());
        $newIntegrationEntity->setLastSyncDate(new \DateTime());

        // This is too heavy of data to log in multiple locations.
        if ($internalData) {
            $newIntegrationEntity->setInternal($internalData);
        }

        return $newIntegrationEntity;
    }

    /**
     * Get the result array of the import process.
     *
     * @return array
     */
    public function getResult()
    {
        $result = [];

        // Allowed fields.
        if ($this->verbose) {
            $result['allowedFields'] = $this->getAllowedFields();
        }

        // Attribution (cost) applied.
        if ($this->verbose) {
            // Attribution in this context is the revenue/cost for the third party.
            $result['attribution'] = $this->attribution;
        }

        // Campaign.
        if ($this->campaign) {
            $result['campaign']         = [];
            $result['campaign']['id']   = $this->campaign->getId();
            $result['campaign']['name'] = $this->campaign->getName();
            if ($this->verbose) {
                $result['campaign']['description'] = $this->campaign->getDescription();
                $result['campaign']['category']    = $this->campaign->getCategory();
            }
        }

        // Contact.
        $result['contact'] = null;
        if ($this->contact) {
            // This is a simplified output of the "Contact"
            // It is a flat array, containing only the fields that we accepted and used to create the contact.
            // It does not include the same entity structure as you would find in the core API.
            $result['contact']       = [];
            $result['contact']['id'] = $this->contact->getId();
            if ($this->verbose) {
                $result['contact']['fields'] = $this->fieldsStored;
            }
        }

        // Errors.
        $result['errors'] = null;
        if ($this->errors) {
            $result['errors'] = $this->errors;
        }

        // Events (for real-time campaigns).
        if ($this->verbose) {
            $result['events'] = $this->events;
        }

        // Source.
        if ($this->contactSource) {
            $result['source']         = [];
            $result['source']['id']   = $this->contactSource->getId();
            $result['source']['name'] = $this->contactSource->getName();
            if ($this->verbose) {
                $result['source']['category']      = $this->contactSource->getCategory();
                $result['source']['description']   = $this->contactSource->getDescriptionPublic();
                $result['source']['documentation'] = $this->contactSource->getDocumentation();
            }
        }

        // Status.
        if ($this->verbose) {
            $result['status'] = $this->status;
        }

        // HTTP Status Code.
        $result['statusCode'] = $this->statusCode;

        // Success boolean.
        $result['success'] = $this->valid;

        // UTM Source.
        if ($this->verbose) {
            $result['utmSource'] = $this->utmSource;
        }

        return $result;
    }
}
