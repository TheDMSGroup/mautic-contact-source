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

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use FOS\RestBundle\Util\Codes;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Lead as CampaignContact;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Helper\CacheStorageHelper;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\CoreBundle\Helper\PhoneNumberHelper;
use Mautic\EmailBundle\Helper\EmailValidator;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\LeadBundle\Entity\LeadDevice as ContactDevice;
use Mautic\LeadBundle\Entity\UtmTag;
use Mautic\LeadBundle\Entity\UtmTagRepository;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\LeadModel as ContactModel;
use Mautic\PluginBundle\Entity\IntegrationEntity;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticContactSourceBundle\Entity\CacheRepository;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use MauticPlugin\MauticContactSourceBundle\Entity\Event;
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
    const CACHE_TTL = 300;

    /** @var string */
    protected $status;

    /** @var int */
    protected $limits = [];

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
    protected $realTime = true;

    /** @var int */
    protected $scrubRate = 0;

    /** @var int */
    protected $cost = 0;

    /** @var string */
    protected $utmSource = '';

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

    /** @var float */
    protected $startTime;

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
    protected $allowedFieldLabels;

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

    /** @var array */
    protected $campaignSettingsParsed = [];

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

    /** @var ContactDevice */
    protected $device;

    /** @var PhoneNumberHelper */
    protected $phoneHelper;

    /** @var CacheStorageHelper */
    protected $cacheStorageHelper;

    /** @var CoreParametersHelper */
    protected $coreParametersHelper;

    /** @var PathsHelper */
    protected $pathsHelper;

    /** @var array */
    private $integrationSettings;

    /** @var array */
    private $contactCampaigns = [];

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
     * @param CoreParametersHelper     $coreParametersHelper
     * @param PathsHelper              $pathsHelper
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
        Session $session,
        CoreParametersHelper $coreParametersHelper,
        PathsHelper $pathsHelper
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
        $this->coreParametersHelper  = $coreParametersHelper;
        $this->pathsHelper           = $pathsHelper;
    }

    /**
     * @param float $startTime
     *
     * @return $this
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

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
     * @return int
     */
    public function getSourceId()
    {
        return $this->sourceId;
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
     * @return int
     */
    public function getCampaignId()
    {
        return $this->campaignId;
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
     * Ensure the required parameters were provided and not empty while parsing.
     * There are many ways to send a simple token... Let's support them all to be friendly to our Sources.
     *
     * @throws ContactSourceException
     */
    private function parseToken()
    {
        // Provided as a token param.
        $this->token = trim($this->request->get('token'));
        if (!$this->token) {
            // Provided as a token header.
            $this->token = trim($this->request->headers->get('token'));
            if (!$this->token) {
                // Provided as a X-Auth-Token header.
                $this->token = trim($this->request->headers->get('X-Auth-Token'));
                if (!$this->token) {
                    // Provided as a password header.
                    $this->token = trim($this->request->headers->get('password'));
                    if (!$this->token) {
                        // Various ways a token may come in via authorization headers.
                        $auth = $this->request->headers->get('authorization');
                        if ($auth) {
                            if (false !== strpos($auth, 'Bearer ')) {
                                // Provided as a bearer token.
                                $this->token = trim(str_ireplace('Bearer ', '', $auth));
                            } elseif (false !== strpos($auth, 'Basic ')) {
                                // Provided as a password.
                                $userPass = base64_decode(trim(str_ireplace('Basic ', '', $auth)));
                                if (strpos($userPass, ':') > 0) {
                                    $userPassParts = explode(':', $userPass);
                                    if (!empty($userPassParts[1])) {
                                        $this->token = $userPassParts[1];
                                    }
                                }
                            }
                        }
                        // Re-use the last token provided for this user for this source.
                        if (!$this->token && $this->sourceId) {
                            $tokens = $this->session->get('mautic.contactSource.tokens');
                            if ($tokens && !empty($tokens[$this->sourceId])) {
                                $this->token           = $tokens[$this->sourceId];
                                $this->errors['token'] = 'Token was supplied earlier in your session, but missing in this request. Please provide your authentication "token" parameter with every request.';
                            }
                        }
                    }
                }
            }
        }
        if (!$this->token) {
            throw new ContactSourceException(
                'The token was not supplied for this request or session. Please provide your authentication "token" parameter with every request.',
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
            $this->addTrace('contactSource', $this->contactSource->getName());
            $this->addTrace('contactSourceId', $this->contactSource->getId());
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
            } elseif (
                false === $this->campaign->getIsPublished()
                && !$this->contactSource->getInternal()
            ) {
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
     * Load the settings attached to the Source.
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    public function parseSourceCampaignSettings()
    {
        if (isset($this->campaignSettingsParsed[$this->campaignId])) {
            // Campaign settings have already been parsed for this campaign/session, likely due to a batch import.
            return;
        }

        // Check that the campaign is in the whitelist for this source.
        $campaignSettings = $this->campaignSettingsModel->setContactSource($this->contactSource)
            ->getCampaignSettingsById($this->campaignId);

        // @todo - Support or thwart multiple copies of the same campaign, should it occur. In the meantime...
        $campaignSettings = reset($campaignSettings);
        if (
            !$campaignSettings
            && !$this->imported
            && !$this->contactSource->getInternal()
        ) {
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
            // Establish defaults (especially for allCampaigns).
            $this->realTime  = isset($campaignSettings->realTime) ? boolval($campaignSettings->realTime) : true;
            $this->limits    = isset($campaignSettings->limits) ? $campaignSettings->limits : [];
            $this->scrubRate = isset($campaignSettings->scrubRate) ? intval($campaignSettings->scrubRate) : 0;
        }
        $this->cost      = isset($campaignSettings->cost) ? (abs(floatval($campaignSettings->cost))) : 0;
        $this->utmSource = !empty($this->contactSource->getUtmSource()) ? $this->contactSource->getUtmSource() : '';

        // Apply field overrides.
        if ($this->utmSource) {
            $this->fieldsProvided['utm_source'] = $this->utmSource;
        }
        $this->addTrace('contactSourceRealTime', $this->realTime);
        $this->addTrace('contactSourceCost', $this->cost);
        $this->addTrace('contactSourceUtmSource', $this->utmSource);

        $this->campaignSettingsParsed[$this->campaignId] = true;
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
        define('MAUTIC_SOURCE_INGESTION', 1);

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
            $this->processParallel();
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
            $verboseKey = $this->getIntegrationSetting('verbose', '1');
            if ($verboseHeader == $verboseKey) {
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
     * Get all global Source integration settings, or a single feature setting.
     *
     * @param string $key
     * @param string $default
     *
     * @return array|mixed|string
     */
    public function getIntegrationSetting($key = '', $default = '')
    {
        if (null === $this->integrationSettings) {
            $this->integrationSettings = [];
            $object                    = $this->integrationHelper->getIntegrationObject('Source');
            if ($object) {
                $objectSettings = $object->getIntegrationSettings();
                if ($objectSettings) {
                    $this->integrationSettings = $objectSettings->getFeatureSettings();
                }
            }
        }
        if ($key) {
            if (isset($this->integrationSettings[$key])) {
                return $this->integrationSettings[$key];
            } else {
                return $default;
            }
        } else {
            return $this->integrationSettings;
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

        // Move UTM tags to another array to avoid use in import, since it doesn't support them.
        $deviceData = [];
        foreach ($this->getDeviceSetters() as $k => $v) {
            if (isset($this->fieldsProvided[$k])) {
                $deviceData[$k] = $this->fieldsProvided[$k];
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
                /* @var UtmTagRepository $utmRepo */
                // Randomly causes Doctrine\ORM\ORMInvalidArgumentException in production.
                // $utmRepo = $this->em->getRepository('MauticLeadBundle:UtmTag');
                // $utmRepo->saveEntity($this->getUtmTag(), false);
                $contact->setUtmTags($this->getUtmTag());
            }

            // Cycle through calling appropriate setters if there is device data.
            if (count($deviceData)) {
                foreach ($this->getDeviceSetters() as $q => $setter) {
                    if (isset($deviceData[$q])) {
                        $this->getDevice()->$setter($deviceData[$q]);
                        $this->fieldsStored[$q] = $deviceData[$q];
                    }
                }

                // Add date added, critical for inserts.
                $this->getDevice()->setDateAdded(new \DateTime());

                // Apply to the contact for save later.
                $this->getDevice()->setLead($contact);
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
     * Returns an array containing type, alias, defaultValue
     *
     * @param bool $details
     *
     * @return array|bool|mixed
     */
    public function getAllowedFields($details = false)
    {
        if (null === $this->allowedFields) {
            $cache = $this->cacheStorageHelper();
            if ($cachedAllowedFields = $cache->get('allowedFields', self::CACHE_TTL)) {
                $this->allowedFields = $cachedAllowedFields;
            }
            if (!$this->allowedFields) {
                try {
                    // Exclude company fields as they cannot be created/related on insert due to performance implications.
                    $entities = $this->fieldModel->getEntities(
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
                                        'expr'   => 'neq',
                                        'value'  => 'company',
                                    ],
                                ],
                            ],
                            'hydration_mode' => 'HYDRATE_ARRAY',
                        ]
                    );

                    // Also build an inclusive array for API and output.
                    $allowedFields = [];
                    foreach ($entities as $field) {
                        $allowedFields[$field['alias']] = [
                            'alias'        => $field['alias'],
                            'type'         => $field['type'],
                            'defaultValue' => $field['defaultValue'],
                            'label'        => $field['label'],
                            'group'        => $field['group'],
                        ];
                    }
                    // Add IP as an allowed import field.
                    $allowedFields['ip'] = [
                        'alias'        => 'ip',
                        'type'         => 'text',
                        'defaultValue' => '',
                        'label'        => 'IP Addresses (comma delimited)',
                        'field_group'  => 'system',
                    ];

                    // Get available UTM fields and their setters.
                    foreach ($this->getUtmSetters() as $q => $v) {
                        $allowedFields[$q] = [
                            'alias'        => $q,
                            'type'         => 'text',
                            'defaultValue' => '',
                            'label'        => str_replace(
                                ['Utm', 'Set'],
                                ['UTM', ''],
                                ucwords(str_replace('_', ' ', $q))
                            ),
                        ];
                    }

                    // Get available Device fields and their setters.
                    foreach ($this->getDeviceSetters() as $q => $v) {
                        $allowedFields[$q] = [
                            'alias'        => $q,
                            'type'         => 'text',
                            'defaultValue' => '',
                            'label'        => ucwords(str_replace('_', ' ', $q)),
                        ];
                    }

                    unset($allowedFields['attribution'], $allowedFields['attribution_date']);

                    uksort($allowedFields, 'strnatcmp');

                    $this->allowedFields = $allowedFields;

                    $cache->set('allowedFields', $this->allowedFields, self::CACHE_TTL);
                } catch (\Exception $exception) {
                    $this->handleException($exception);
                }
            }
            if ($this->allowedFields && !$this->allowedFieldLabels) {
                $allowedFieldLabels = [];
                foreach ($this->allowedFields as $key => $value) {
                    $allowedFieldLabels[$key] = $value['label'];
                }
                $this->allowedFieldLabels = $allowedFieldLabels;
            }
        }

        return $details ? $this->allowedFields : $this->allowedFieldLabels;
    }

    /**
     * @return CacheStorageHelper
     */
    private function cacheStorageHelper()
    {
        if (!$this->cacheStorageHelper) {
            $this->cacheStorageHelper = new CacheStorageHelper(
                CacheStorageHelper::ADAPTOR_FILESYSTEM,
                'ContactSource',
                null,
                $this->coreParametersHelper->getParameter(
                    'cached_data_dir',
                    $this->pathsHelper->getSystemPath('cache', true)
                ),
                self::CACHE_TTL
            );
        }

        return $this->cacheStorageHelper;
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
            unset($utmSetters['date_added']);
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
     * Return all utm setters except query which is self-set.
     *
     * @return array
     */
    private function getDeviceSetters()
    {
        // This entity does not have a handy getFieldSetterList;
        return [
            'device'               => 'setDevice',
            'device_brand'         => 'setDeviceBrand',
            'device_model'         => 'setDeviceModel',
            'device_os_name'       => 'setDeviceOsName',
            'device_os_short_name' => 'setDeviceOsShortName',
            'device_os_version'    => 'setDeviceOsVersion',
            'device_os_platform'   => 'setDeviceOsPlatform',
            'device_fingerprint'   => 'setDeviceFingerprint',
        ];
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
        $allowedFieldsAliases = $this->getAllowedFields();
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
                        $this->errors['ip'] = '';
                    }
                    $this->errors['ip'] .= 'The IP address "'.$ipAddressString.'" is invalid.';
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
                // If NULL string provided, treat it as null.
                if ('NULL' === $fieldData[$contactField['alias']]) {
                    $fieldData[$contactField['alias']] = null;
                    continue;
                }
                // Perform standard core field cleaning.
                try {
                    $this->contactModel->cleanFields($fieldData, $contactField);
                } catch (\Exception $exception) {
                    unset($fieldData[$contactField['alias']]);
                    $this->errors[$contactField['alias']] = ucfirst(
                            $contactField['type']
                        ).' is invalid and will not be stored.';
                }
                // Normalize and validate emails.
                if ('email' === $contactField['type'] && !empty($fieldData[$contactField['alias']])) {
                    try {
                        $this->emailValidator->validate(
                            $fieldData[$contactField['alias']],
                            $this->getIntegrationSetting('email_dns_check', false)
                        );
                    } catch (\Exception $exception) {
                        unset($fieldData[$contactField['alias']]);
                        $this->errors[$contactField['alias']] = 'Email is invalid and will not be stored.';
                    }
                }
                // Normalize and validate phone numbers.
                if ('tel' === $contactField['type'] && !empty($fieldData[$contactField['alias']])) {
                    try {
                        $this->phoneNormalize($fieldData[$contactField['alias']]);
                    } catch (\Exception $exception) {
                        unset($fieldData[$contactField['alias']]);
                        $this->errors[$contactField['alias']] = 'Phone number is invalid and will not be stored.';
                    }
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
     * @param $phone
     *
     * @return string
     */
    private function phoneNormalize(&$phone)
    {
        $phone = trim($phone);
        if (!empty($phone)) {
            if (!$this->phoneHelper) {
                $this->phoneHelper = new PhoneNumberHelper();
            }
            $normalized = $this->phoneHelper->format($phone);
            if (!empty($normalized)) {
                $phone = $normalized;
            }
        }
    }

    /**
     * @return ContactDevice
     */
    private function getDevice()
    {
        if (null === $this->device) {
            $this->device = new ContactDevice();
        }

        return $this->device;
    }

    /**
     * Evaluate Source & Campaign limits using the Cache.
     *
     * @throws ContactSourceException
     * @throws \Exception
     */
    private function evaluateLimits()
    {
        $limits        = new \stdClass();
        $limits->rules = $this->limits;

        $this->excludeIrrelevantRules($limits);

        $this->getCacheModel()->evaluateLimits($limits, $this->campaignId);
    }

    /**
     * Exclude limits that are not currently applicable, because of a tighter scope.
     *
     * @param $rules
     *
     * @throws \Exception
     */
    private function excludeIrrelevantRules(&$rules)
    {
        if (!empty($rules->rules)) {
            foreach ($rules->rules as $key => $limit) {
                if (
                    isset($limit->scope)
                    && CacheRepository::SCOPE_UTM_SOURCE === intval($limit->scope)
                    && strlen(trim($limit->value))
                    && trim($limit->value) !== $this->utmSource
                ) {
                    // This is a UTM Source limit, and we do not match it, so it is currently irrelevant to us.
                    unset($rules->rules[$key]);
                    continue;
                }
                if (
                    isset($limit->scope)
                    && CacheRepository::SCOPE_CATEGORY === intval($limit->scope)
                    && $limit->value
                    && $limit->value != $this->contactSource->getCategory()
                ) {
                    // This is a Category limit, and we do not match it, so it is currently irrelevant to us.
                    unset($rules->rules[$key]);
                    continue;
                }
            }
        }
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
     * @throws \Doctrine\ORM\OptimisticLockException
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
        $this->saveDevice();
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
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function saveDevice()
    {
        // Save attached device data since it's a pivot unlike utm source.
        $device = $this->getDevice();
        if ($device->getDateAdded()) {
            $this->em->persist($device);
            $this->em->flush($device);
        }
        $this->em->clear(ContactDevice::class);
        $this->device = null;
    }

    /**
     * Feed a contact to a campaign. If real-time is enabled, skip event dispatching to prevent recursion.
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function addContactToCampaign()
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
    private function addContactsToCampaign(
        Campaign $campaign,
        $contacts = [],
        $manuallyAdded = false
    ) {
        foreach ($contacts as $contact) {
            $campaignContact = new CampaignContact();
            $alreadyExists   = false;
            if (!null == $contact->getDateModified(
                )) { // see if New Contact b/c isNew() is unreliable on PostSave Event
                $leadRepository = $this->em->getRepository('MauticCampaignBundle:Lead');
                $alreadyExists  = $leadRepository->checkLeadInCampaigns(
                    $contact,
                    ['campaigns' => [$campaign->getId()]]
                );
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
            // }
            $this->em->detach($campaignContact);
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
            $this->resetRandomGeneratorByContact();
            $this->scrubbed = $this->scrubRate > rand(0, 99);
        }

        return $this->scrubbed;
    }

    /**
     * Reset random generator based on the current contact.
     *
     * This is to help avoid gaming the Source API scrub rate by re-posting the same lead.
     *
     * @return bool|string
     */
    private function resetRandomGeneratorByContact()
    {
        if ($this->contact) {
            $string = trim(
                strtolower(
                    implode(
                        '|',
                        [
                            $this->contact->getEmail(),
                            $this->contact->getPhone(),
                            $this->contact->getMobile(),
                        ]
                    )
                )
            );
            if (strlen($string) > 3) {
                $binHash = md5($string, true);
                $numHash = unpack('N2', $binHash);
                $hash    = $numHash[1].$numHash[2];
                $hashInt = (int) substr($hash, 0, 10);
                mt_srand($hashInt);
            }
        }
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
        if (!$this->realTime || !$this->campaign || !$this->contact) {
            return;
        }

        if (false == defined('MAUTIC_PLUGIN_CONTACT_SOURCE_REALTIME')) {
            define('MAUTIC_PLUGIN_CONTACT_SOURCE_REALTIME', true);
        }

        $this->campaignExecutioner->execute($this->campaign, [$this->contact->getId()]);
        $this->contactCampaigns[$this->campaign->getId()] = true;

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
                $this->contact = $this->contactModel->getEntity($this->contact->getId());
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
     * Attempt to run kickoff events for a single contact in a parallel process if possible, otherwise synchronously.
     *
     * @return $this|Api
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function processParallel()
    {
        if (
            !$this->campaign
            || !$this->contact
            || !$this->contact->getId()
        ) {
            return $this;
        }

        if (
            $this->realTime
            && !boolval($this->getIntegrationSetting('parallel_realtime', false))
        ) {
            $this->logger->debug('Parallel processing disabled for realtime.');

            return $this;
        }

        if (
            !$this->realTime
            && !boolval($this->getIntegrationSetting('parallel_offline', false))
        ) {
            $this->logger->debug('Parallel processing disabled for non-realtime.');

            return $this;
        }

        if (
            $this->imported
            && !boolval($this->getIntegrationSetting('parallel_import', false))
        ) {
            $this->logger->debug('Parallel processing disabled for imports.');

            return $this;
        }

        // Update the list of campaigns for this contact, and get a list of those that need to be processed.
        $campaignsFound = false;
        foreach (array_keys($this->campaignModel->getLeadCampaigns($this->contact, true)) as $campaignId) {
            if (!isset($this->contactCampaigns[$campaignId])) {
                $this->contactCampaigns[$campaignId] = false;
                $campaignsFound                      = true;
            }
        }
        if (!$campaignsFound) {
            $this->logger->debug('No campaigns to process in parallel');

            return $this;
        }

        return $this->kickoffParallelCampaigns($this->contact, $this->contactCampaigns, !$this->imported);
    }

    /**
     * @param Contact $contact
     * @param array   $campaignIds
     * @param bool    $allowFork   disable PCNTL forking by setting to false, useful during batches
     *
     * @return $this
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function kickoffParallelCampaigns(Contact $contact, &$campaignIds = [], $allowFork = true)
    {
        if (defined('MAUTIC_SOURCE_FORKED_CHILD')) {
            // Do not allow recursive forks.
            return $this;
        }

        if ('WIN' === strtoupper(substr(PHP_OS, 0, 3))) {
            $this->logger->error('Parallel processing not available in Windows.');

            return $this;
        }

        $ramRequiredPercent = intval($this->getIntegrationSetting('parallel_ram', 20));
        if ($ramRequiredPercent > 0) {
            try {
                $memInfo = file_get_contents('/proc/meminfo');
                preg_match('#MemTotal:[\s\t]+([\d]+)\s+kB#', $memInfo, $a);
                if (isset($a[1])) {
                    $memTotal = (int) $a[1];
                    preg_match('#MemAvailable:[\s\t]+([\d]+)\s+kB#', $memInfo, $b);
                    if (isset($b[1])) {
                        $memAvailable = (int) $b[1];
                        if (100 / $memTotal * $memAvailable < $ramRequiredPercent) {
                            $this->logger->debug('Parallel processing aborted due to low RAM.');

                            return $this;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Parallel processing could not discern available RAM.');

                return $this;
            }
        }

        if (!$allowFork || !function_exists('pcntl_fork') || PHP_SAPI !== 'cli') {
            // This appears to be a web request or PCNTL is not enabled.
            if (!function_exists('exec') && !function_exists('popen')) {
                $this->logger->error('Parallel processing not available due to exec/popen methods being disabled.');

                return $this;
            }
            // Instead of forking the process run a CLI thread, this is less efficient but will not cause problems with apache.
            foreach ($campaignIds as $campaignId => &$isProcessed) {
                if (!$isProcessed) {
                    $campaign = $this->campaignModel->getEntity($campaignId);
                    if ($campaign && $campaign->getIsPublished()) {
                        $this->logger->info('Thread kicking off campaign: '.$campaignId);
                        // Explanation:
                        //  Start a new thread defaulting as a child of the current user (typically www-data or webapp).
                        //  Run a new bash shell, to allow us to continue without waiting for a result.
                        //  During the bash shell execute nohup to prevent the child process dying when we terminate.
                        //  Use nice level 19 to prioritize this thread as low as possible (-20 is highest).
                        $execString = 'bash -c "nohup nice -19 '.
                            // Execute the same PHP binary that is currently running if possible.
                            (PHP_BINDIR ? PHP_BINDIR.'/php' : 'php').' '.
                            // Execute the symfony console that is in the current Mautic.
                            // Run mautic:campaign:trigger which is normally ran via cron.
                            getcwd().'/app/console mautic:campaign:trigger '.
                            // Use quiet mode to reduce operation time.
                            '--quiet '.
                            // Skip pid checks as irrelevant given the context.
                            '--force '.
                            // Only run kickoff events (top of campaign).
                            '--kickoff-only '.
                            // Feed it the campaign and contact to operate with.
                            '--campaign-id='.$campaign->getId().' '.
                            '--contact-id='.$contact->getId().
                            // Discard all stdout/stderr output.
                            ' > /dev/null 2>&1 &"';
                        $this->logger->debug('Running parallel CLI thread: '.$execString);
                        if (function_exists('exec')) {
                            @exec($execString);
                        } else {
                            $handle = @popen($execString, 'r');
                            if ($handle) {
                                pclose($handle);
                            }
                        }
                    }
                    $isProcessed = true;
                }
            }
            $this->logger->debug('Parallel process complete.');

            return $this;
        }

        if (!$allowFork) {
            return $this;
        }

        // Commit any MySQL changes and close the connection/s to prepare for a process fork.
        if ($this->em->isOpen()) {
            // There may be entitys ready to save.
            try {
                $this->em->flush();
            } catch (\Exception $e) {
                $this->logger->error('Attempting to flush '.$e->getMessage());
            }
        }
        /** @var Connection $connection */
        $connection = $this->em->getConnection();
        if ($connection) {
            // There may be manual transactions ready to save.
            while (0 !== $connection->getTransactionNestingLevel()) {
                // Check for RollBackOnly to avoid exceptions.
                if (!$connection->isRollbackOnly()) {
                    // Follow behavior of the private commitAll method, in case there are nested transactions.
                    if (false === $connection->isAutoCommit() && 1 === $connection->getTransactionNestingLevel()) {
                        $connection->commit();
                        break;
                    }
                    $connection->commit();
                }
            }
            $connection->close();
        }

        // Calling this way since this is a soft dependency, it's also faster in 7+
        $pid = call_user_func('pcntl_fork');
        if (-1 === $pid) {
            $this->logger->error(
                'Contact Source '.
                ($this->contactSource ? $this->contactSource->getId() : 'NA').
                ': Could not fork the process'
            );

            return $this;
        } elseif ($pid) {
            $this->logger->debug('Parent continues process.');

            // Parent process can continue.
            return $this;
        } else {
            define('MAUTIC_SOURCE_FORKED_CHILD', 1);

            // Child process has work to do.
            foreach ($campaignIds as $campaignId => &$isProcessed) {
                if (!$isProcessed) {
                    $campaign = $this->campaignModel->getEntity($campaignId);
                    if ($campaign && $campaign->getIsPublished()) {
                        $this->logger->info('Child kicking off campaign: '.$campaignId);

                        $this->campaignExecutioner->execute($campaign, [$contact->getId()]);
                    }
                    $isProcessed = true;
                }
            }
            $this->logger->debug('Parallel process complete.');
            exit;
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
            ($this->campaign ? $this->campaign->getId() : intval($this->campaignId))
        );
        $this->em->clear(Stat::class);

        // Add utm/pivot back into fieldsProvided for logging purposes.
        $fieldsProvided = $this->fieldsProvided;
        foreach ($this->getUtmSetters() as $k => $v) {
            if (isset($this->fieldsStored[$k])) {
                $fieldsProvided[$k] = $this->fieldsStored[$k];
            }
        }

        foreach ($this->getDeviceSetters() as $k => $v) {
            if (isset($this->fieldsStored[$k])) {
                $fieldsProvided[$k] = $this->fieldsStored[$k];
            }
        }

        $this->logs = array_merge(
            $this->logs,
            [
                'duration'       => microtime(true) - $this->startTime,
                'status'         => $this->status,
                'sourceIP'       => $this->request ? $this->request->getClientIp() : '127.0.0.1',
                'fieldsProvided' => $fieldsProvided,
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
                'errors'         => $this->errors,
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
        $this->em->clear(Event::class);

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
     *
     * @throws \Exception
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
     */
    public function setContactSource($contactSource = null)
    {
        $this->contactSource = $contactSource;

        return $this;
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

        if ($this->verbose) {
            // These are campaigns that the contact was added to during kickoff events.
            // It is not a given that all children will be here, only those that we know about during this request.
            // I am including this to aid in debugging of the new parallel functionality.
            $result['campaigns'] = $this->contactCampaigns;
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

    /**
     * @param $realtime
     *
     * @return $this
     */
    public function setRealtime($realtime)
    {
        $this->realTime = $realtime;

        return $this;
    }

    /**
     * Allow imports to set Utm Tags.
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function setUtmSourceTag()
    {
        if ($this->utmSource) {
            $utmTags = $this->getUtmTag();
            if ($originalUtmTags = $this->contact->getUtmTags()) {
                $utmTags = $originalUtmTags[0];
            } else {
                $utmTags->setLead($this->contact);
            }
            $utmTags->setUtmSource($this->utmSource);
            $utmTags->setDateAdded(new \DateTime());
            $this->em->persist($utmTags);
            if ($originalUtmTags) {
                $this->em->flush($utmTags);
            }
            $this->contact->setUtmTags($utmTags);
        }
    }
}
