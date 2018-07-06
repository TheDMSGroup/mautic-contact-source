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
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\EmailBundle\Helper\EmailValidator;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\LeadBundle\Entity\UtmTag;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\LeadModel as ContactModel;
use Mautic\PluginBundle\Entity\IntegrationEntity;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use MauticPlugin\MauticContactSourceBundle\Entity\Stat;
use MauticPlugin\MauticContactSourceBundle\Event\ContactLedgerContextEvent;
use MauticPlugin\MauticContactSourceBundle\Exception\ContactSourceException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

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
    protected $cost = 0;

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

    /** @var int */
    protected $attribution;

    /** @var IpLookupHelper */
    protected $ipLookupHelper;

    /** @var LoggerInterface */
    protected $logger;

    /** @var IntegrationHelper */
    protected $integrationHelper;

    /** @var ContactSourceModel */
    protected $contactSourceModel;

    /** @var CampaignSettings */
    protected $campaignSettingsModel;

    /** @var CampaignExecutioner */
    protected $campaignExecutioner;

    /** @var FieldModel */
    protected $fieldModel;

    /** @var Session */
    protected $session;

    /** @var bool */
    protected $authenticated = false;

    /** @var bool */
    protected $imported = false;

    /**
     * Api constructor.
     *
     * @param EventDispatcherInterface $dispatcher
     * @param EntityManager            $em
     * @param IpLookupHelper           $ipLookupHelper
     * @param LoggerInterface          $logger
     * @param IntegrationHelper        $integrationHelper
     * @param ContactSourceModel       $contactSourceModel
     * @param CampaignSettings         $campaignSettingsModel
     * @param CampaignModel            $campaignModel
     * @param CampaignExecutioner      $campaignExecutioner
     * @param FieldModel               $fieldModel
     * @param ContactModel             $contactModel
     * @param EmailValidator           $emailValidator
     * @param Cache                    $cacheModel
     * @param Session                  $session
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        EntityManager $em,
        IpLookupHelper $ipLookupHelper,
        LoggerInterface $logger,
        IntegrationHelper $integrationHelper,
        ContactSourceModel $contactSourceModel,
        CampaignSettings $campaignSettingsModel,
        CampaignModel $campaignModel,
        CampaignExecutioner $campaignExecutioner,
        FieldModel $fieldModel,
        ContactModel $contactModel,
        EmailValidator $emailValidator,
        Cache $cacheModel,
        Session $session
    ) {
        $this->dispatcher            = $dispatcher;
        $this->em                    = $em;
        $this->ipLookupHelper        = $ipLookupHelper;
        $this->logger                = $logger;
        $this->integrationHelper     = $integrationHelper;
        $this->contactSourceModel    = $contactSourceModel;
        $this->campaignSettingsModel = $campaignSettingsModel;
        $this->campaignModel         = $campaignModel;
        $this->campaignExecutioner   = $campaignExecutioner;
        $this->fieldModel            = $fieldModel;
        $this->contactModel          = $contactModel;
        $this->emailValidator        = $emailValidator;
        $this->cacheModel            = $cacheModel;
        $this->session               = $session;
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
 * @param ContactSource $contactSource
 *
 * @return $this
 */
    public function setContactSource($contactSource = null)
    {
        $this->contactSource = $contactSource;

        return $this;
    }

    /**
     * @param Contact $contact
     *
     * @return $this
     */
    public function setContact($contact = null)
    {
        $this->contact = $contact;

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
     * @param Campaign $campaign
     *
     * @return $this
     */
    public function setCampaign($campaign = null)
    {
        $this->campaign = $campaign;

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
            $this->parseToken();
            $this->parseSourceId();
            $this->parseSource();
            $this->validateToken();
            $this->parseCampaignId();
            $this->parseCampaign();
            $this->parseSourceCampaignSettings();
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
            $this->contactSource = $this->contactSourceModel->getEntity($this->sourceId);
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
            $this->addTrace('contactSourceId', (int) $this->sourceId);
        }

        return $this;
    }

    /**
     * If available add a parameter to NewRelic tracing to aid in debugging.
     *
     * @param $parameter
     * @param $value
     */
    private function addTrace($parameter, $value)
    {
        if (function_exists('newrelic_add_custom_parameter')) {
            call_user_func('newrelic_add_custom_parameter', $parameter, $value);
        }
    }

    /**
     * Load the settings attached to the Source.
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    public function parseSourceCampaignSettings()
    {
        // Check that the campaign is in the whitelist for this source.
        $campaignSettings = $this->campaignSettingsModel->setContactSource($this->contactSource)
                ->getCampaignSettingsById($this->campaignId);

        // @todo - Support or thwart multiple copies of the same campaign, should it occur. In the meantime...
        $campaignSettings = reset($campaignSettings);
        if (!$campaignSettings && !$this->imported) {
            throw new ContactSourceException(
                    'The campaignId supplied is not currently in the permitted list of campaigns for this source.',
                    Codes::HTTP_GONE,
                    null,
                    Stat::TYPE_INVALID,
                    'campaignId'
                );
        }
        // Establish parameters from campaign settings; skip some settings on contact import from file.
        if (!$this->imported) {
            $this->realTime  = (bool) isset($campaignSettings->realTime) && $campaignSettings->realTime;
            $this->limits    = isset($campaignSettings->limits) ? $campaignSettings->limits : [];
            $this->scrubRate = isset($campaignSettings->scrubRate) ? intval($campaignSettings->scrubRate) : 0;
        }

        $this->cost      = isset($campaignSettings->cost) ? (abs(floatval($campaignSettings->cost))) : 0;
        $this->utmSource = !empty($this->contactSource->getUtmSource()) ? $this->contactSource->getUtmSource() : null;
        // Apply field overrides
        if ($this->utmSource) {
            $this->fieldsProvided['utm_source'] = $this->utmSource;
        }
        $this->addTrace('contactSourceRealTime', $this->realTime);
        $this->addTrace('contactSourceCost', $this->cost);
        $this->addTrace('contactSourceUtmSource', $this->utmSource);
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
            $this->campaign = $this->campaignModel->getEntity($this->campaignId);
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
            $this->addTrace('contactSourceCampaignId', (int) $this->campaignId);
        }

        return $this;
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
                    // Re-use the last token provided for this user for this source.
                    if (!$this->token && $this->sourceId) {
                        $tokens = $this->session->get('mautic.contactSource.tokens');
                        if ($tokens && isset($tokens[$this->sourceId])) {
                            $this->token = $tokens[$this->sourceId];
                        }
                    }
                }
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
        if ($this->sourceId) {
            $tokens                  = $this->session->get('mautic.contactSource.tokens', []);
            $tokens[$this->sourceId] = $this->token;
            $this->session->set('mautic.contactSource.tokens', $tokens);
        }
        $this->authenticated = true;
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
            if (method_exists($exception, 'getData')) {
                $data = $exception->getData();
                if ($data) {
                    $this->logs['exceptionData'] = $data;
                }
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
        $this->parseToken();
        $this->parseSourceId();
        $this->parseSource();
        $this->parseCampaignId();
        $this->validateToken();
        $this->parseSourceCampaignSettings();
        $this->parseCampaign();

        return $this;
    }

    /**
     * Evaluate the "verbose" header if provided, to match against configuration.
     *
     * @throws ContactSourceException
     */
    private function parseVerbosity()
    {
        $verboseHeader = $this->request->headers->get('verbose');
        if (null !== $verboseHeader) {
            // The default key is 1, for BC.
            $verboseKey = '1';
            /** @var \Mautic\PluginBundle\Integration\AbstractIntegration $object */
            $object = $this->integrationHelper->getIntegrationObject('Source');
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
                $msg .= ' Did you mean \''.$closest.'\' '.$allowedFields[$closest].'?';
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
            throw new ContactSourceException(
                'There were no fields provided. A contact could not be created.',
                Codes::HTTP_BAD_REQUEST,
                null,
                Stat::TYPE_INVALID
            );
        }

        // Dynamically generate the field map and import.
        // @todo - Discern and assign owner.
        $contact = $this->importContact(
            array_combine(array_keys($this->fieldsProvided), array_keys($this->fieldsProvided)),
            $this->fieldsProvided
        );

        if ($contact) {
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
                // Exclude company fields as they cannot be created/related on insert due to performance implications.
                $this->allowedFieldEntities = $this->fieldModel->getEntities(
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
     * @param bool $persist
     *
     * @return Contact
     *
     * @throws ContactSourceException
     */
    public function importContact(
        $fields,
        $data,
        $owner = null,
        $list = null,
        $tags = null,
        $persist = true
    ) {
        $fields    = array_flip($fields);
        $fieldData = [];

        // Alteration to core start.
        // Get an array of allowed field aliases.
        $allowedFields        = $this->getAllowedFields(true);
        $allowedFieldsAliases = [];
        foreach ($allowedFields as $contactField) {
            $allowedFieldsAliases[$contactField['alias']] = true;
        }
        // Alteration to core stop.

        // Alteration to core: Skip company import section.

        foreach ($fields as $leadField => $importField) {
            if (array_key_exists($importField, $data) && !is_null($data[$importField]) && '' != $data[$importField]) {
                // Alteration to core: Fields have already been cleaned by this point, so we can remove the helper.
                $fieldData[$leadField] = $data[$importField];
            }
        }

        // Alteration to core: Skip duplicate contact check and merge with checkForDuplicateContact.
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

        // Alteration to core: Validate IP addresses on import and support multiple for geolocation.
        if (!empty($fields['ip']) && !empty($data[$fields['ip']])) {
            $ipAddressArray = explode(',', $data[$fields['ip']]);
            foreach ($ipAddressArray as $ipAddressString) {
                $ipAddressString = trim($ipAddressString);
                // Validate the IP before attempting a lookup.
                if ($ipAddressString && $this->ipLookupHelper->ipIsValid($ipAddressString)) {
                    /** @var IpAddress $ipAddress */
                    $ipAddress = $this->ipLookupHelper->getIpAddress($ipAddressString);
                    if ($ipAddress) {
                        // If not provided, fill in location data based on the IP, without overriding any provided data.
                        $ipDetails = $ipAddress->getIpDetails();
                        if (is_array($ipDetails)) {
                            foreach ($ipDetails as $ipKey => $ipValue) {
                                if ('extra' != $ipKey
                                    && !empty($ipValue)
                                ) {
                                    if (
                                        isset($allowedFieldsAliases[$ipKey])
                                        && empty($data[$ipKey])
                                    ) {
                                        $fieldData[$ipKey] = $ipValue;
                                    } else {
                                        // Support a 'state' field where appropriate.
                                        if (
                                            'region' === $ipKey
                                            && isset($allowedFieldsAliases['state'])
                                            && empty($data['state'])
                                        ) {
                                            $fieldData['state'] = $ipValue;
                                        }
                                    }
                                }
                            }
                        }
                        $contact->addIpAddress($ipAddress);
                    }
                } else {
                    // An invalid IP address is a soft error.
                    if (!isset($this->errors['ip'])) {
                        $this->errors['ip'] = [];
                    }
                    $this->errors['ip'][$ipAddressString] = 'This IP address is invalid.';
                }
            }
        }
        unset($fieldData['ip']);

        // Alteration to core: Sources will not be able to set this on creation: points

        // Alteration to core: Sources will not be able to set this on creation: stage
        unset($fieldData['stage']);

        // Alteration to core: Sources will not be able to set this on creation: doNotEmail
        unset($fieldData['doNotEmail']);

        // Alteration to core: Sources will not be able to set this on creation: ownerusername
        unset($fieldData['ownerusername']);

        if (null !== $owner) {
            $contact->setOwner($this->contactModel->getReference('MauticUserBundle:User', $owner));
        }

        if (null !== $tags) {
            $this->contactModel->modifyTags($contact, $tags, null, false);
        }

        // Alteration to core: Use AllowedFields array instead of allowing all...
        // Apply custom field defaults and clean/validate inputs.
        // Return exception to API if validation fails.
        foreach ($allowedFields as $contactField) {
            if (isset($fieldData[$contactField['alias']])) {
                if ('NULL' === $fieldData[$contactField['alias']]) {
                    $fieldData[$contactField['alias']] = null;
                    continue;
                }
                try {
                    $this->contactModel->cleanFields($fieldData, $contactField);
                    if ('email' === $contactField['type'] && !empty($fieldData[$contactField['alias']])) {
                        $this->emailValidator->validate($fieldData[$contactField['alias']], false);
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

        // Alteration to core: Skip imported/merged event triggers, manipulator, and the persist/save.

        return $contact;
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
        $this->cacheModel->setContactSource($this->contactSource);

        return $this->cacheModel;
    }

    /**
     * @throws ContactSourceException
     */
    private function saveContact()
    {
        $exception = null;
        $this->dispatchContextCreate();
        try {
            $this->contactModel->saveEntity($this->contact);
        } catch (\Exception $exception) {
        }
        if ($exception || !$this->contact->getId()) {
            throw new ContactSourceException(
                'Could not confirm the contact was saved. '.($exception ? $exception->getMessage() : ''),
                Codes::HTTP_INTERNAL_SERVER_ERROR,
                $exception,
                Stat::TYPE_ERROR
            );
        }
        $this->addTrace('contactSourceContactId', $this->contact->getId());
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
            // Passing manuallyAdded as true is important, prevents cron from immediately removing the contacts.
            $this->addContactsToCampaign($this->campaign, [$this->contact], true);
        }

        return $this;
    }

    /**
     * Add contact to a campaign, and optionally run in real-time.
     *
     * @param Campaign $campaign
     * @param array    $contacts
     * @param bool     $manuallyAdded
     *
     * @throws \Exception
     */
    public function addContactsToCampaign(
        Campaign $campaign,
        $contacts = [],
        $manuallyAdded = false
    ) {
        foreach ($contacts as $contact) {
            $campaignContact = new CampaignContact();
            $alreadyExists   = false;
            if (!null == $contact->getDateModified()) { // see if New Contact b/c isNew() is unreliable on PostSave Event
                $leadRepository = $this->em->getRepository('MauticCampaignBundle:Lead');
                $alreadyExists  = $leadRepository->checkLeadInCampaigns($contact, ['campaigns' => [$campaign->getId()]]);
            }
            if (!$alreadyExists) {
                $campaignContact->setCampaign($campaign);
                $campaignContact->setDateAdded(new \DateTime());
                $campaignContact->setLead($contact);
                $campaignContact->setManuallyAdded($manuallyAdded);
                $saved = $this->campaignModel->saveCampaignLead($campaignContact);
            }

            // @todo - Support non realtime event firing.
            // if (!$realTime) {
            //     // Only trigger events if not in realtime where events would be followed directly.
            //     if ($saved && $this->campaignModel->hasListeners(CampaignEvents::CAMPAIGN_ON_LEADCHANGE)) {
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
    public function processOffline()
    {
        if (!$this->realTime) {
            // Establish scrub now.
            if ($this->isScrubbed()) {
                // Asynchronous rejection (scrubbed)
                $this->status = Stat::TYPE_SCRUBBED;
                $this->valid  = false;
            } else {
                // Asynchronous acceptance.
                $this->status = Stat::TYPE_ACCEPTED;
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
            $this->campaignExecutioner->execute($this->campaign, [$this->contact->getId()]);

            // Retrieve events fired by MauticContactClientBundle (if any)
            $events = $this->session->get('mautic.contactClient.events', []);
            if (!empty($events)) {
                $this->eventErrors = [];
                foreach ($events as $event) {
                    if (isset($event['contactId']) && $event['contactId'] !== $this->contact->getId()) {
                        // For not ignore/exclude all events not relating to the current contact.
                        continue;
                    }
                    $this->events[] = $event;
                    if (!empty($event['error'])) {
                        $eventName = !empty($event['name']) ? $event['name'] : '';
                        if (!is_array($event['errors'])) {
                            $event['errors'] = [$event['errors']];
                        }
                        $this->eventErrors[] = $eventName.' ('.$event['id'].'): '.implode(', ', $event['errors']);
                    }
                    if (isset($event['valid']) && $event['valid']) {
                        // One valid Contact Client was found to accept the lead.
                        $this->status = Stat::TYPE_ACCEPTED;
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
                $this->status = Stat::TYPE_SCRUBBED;
                $this->valid  = false;
            }
        }
    }

    /**
     * Apply attribution if we accepted the lead.
     *
     * @throws \Exception
     */
    public function applyAttribution()
    {
        if ($this->valid && $this->cost && Stat::TYPE_ACCEPTED === $this->status) {
            // check if an id exists and attribution field exists b/c sometimes its not a well-formed entity
            if ($this->contact->getId() && !property_exists($this->contact, 'attribution')) {
                $this->contact       = $this->contactModel->getEntity($this->contact->getId());
            }
            $originalAttribution = $this->contact->getAttribution();
            // Attribution is always a negative number to represent cost.
            $this->attribution = ($this->cost * -1);
            $this->contact->addUpdatedField(
                'attribution',
                $originalAttribution + $this->attribution,
                $originalAttribution
            );
            $this->dispatchContextCreate();

            if (!$this->imported) {
                // contacts imported via file upload hit this method via a pre-save event so dont save here.
                $this->contactModel->saveEntity($this->contact);
            }
        }
    }

    /**
     * Create cache entry if a contact was created, used for duplicate checking and limits.
     *
     * @throws \Exception
     */
    private function createCache()
    {
        if ($this->valid && $this->contact->getId()) {
            $this->getCacheModel()->setContact($this->contact)
                    ->setContactSource($this->contactSource)
                    ->create($this->campaignId);
        }
    }

    /**
     * Use LeadTimelineEvent.
     */
    public function logResults()
    {
        if ($this->valid) {
            $statLevel = 'INFO';
            $message   = 'Contact '.$this->contact->getId().
                ' was imported successfully into campaign '.$this->campaign->getName();
        } else {
            $statLevel = 'ERROR';
            $message   = isset($this->errors) ? implode(PHP_EOL, $this->errors) : '';
            if ($this->eventErrors) {
                if (!is_array($this->eventErrors)) {
                    $this->eventErrors = [$this->eventErrors];
                }
                $message = implode(PHP_EOL.'  ', $this->eventErrors);
            }
            $message = trim($message);
            if ($this->realTime && !$message) {
                $message = 'Contact was not accepted by any clients in real-time.';
            }
        }
        $this->addTrace('contactSourceStatType', $this->status);

        // Add log entry for statistics / charts.
        $this->contactSourceModel->addStat(
            $this->contactSource,
            $this->status,
            $this->contact,
            $this->attribution,
            intval($this->campaignId)
        );
        $this->logs = array_merge(
            $this->logs,
            [
                'status'         => $this->status,
                'fieldsProvided' => $this->fieldsProvided,
                'fieldsStored'   => $this->fieldsStored,
                'realTime'       => $this->realTime,
                'scrubbed'       => $this->scrubbed,
                'utmSource'      => $this->utmSource,
                'campaign'       => $this->campaign ? [
                    'id'   => $this->campaign->getId(),
                    'name' => $this->campaign->getName(),
                ] : null,
                'contact'        => $this->contact ? [
                    'id' => $this->contact->getId(),
                ] : null,
                'events'         => $this->events,
            ]
        );

        // Add transactional event for deep dive into logs.
        $this->contactSourceModel->addEvent(
            $this->contactSource,
            $this->status,
            $this->contact,
            $this->getLogsJSON(),
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
        // File-based logging.
        $this->logger->log(
            $statLevel,
            'Contact Source '.($this->contactSource ? $this->contactSource->getId() : 'NA').': '.$message
        );
    }

    /**
     * @return string
     */
    public function getLogsJSON()
    {
        return json_encode($this->logs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
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
     * @return Contact
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * @return ContactSource
     */
    public function getContactSource()
    {
        return $this->contactSource;
    }

    /**
     * @return ContactSourceModel
     */
    public function getContactSourceModel()
    {
        return $this->contactSourceModel;
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

        // Authentication.
        if ($this->verbose) {
            $result['authenticated'] = $this->authenticated;
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

        // Real-Time.
        if ($this->verbose) {
            $result['realTime'] = $this->realTime;
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

    /**
     * Retrieve a list of campaign fields.
     *
     * @param bool $asEntities
     *
     * @return array|null
     */
    public function getCampaignFields($asEntities = false)
    {
        return null;
    }

    /**
     * @return Campaign
     */
    public function getCampaign()
    {
        return $this->campaign;
    }

    /**
     * @return bool
     */
    public function getImported()
    {
        return $this->imported;
    }

    /**
     * @param $imported
     *
     * @return $this
     */
    public function setImported($imported)
    {
        $this->imported = $imported;

        return $this;
    }
}
