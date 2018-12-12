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

use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Helper\TemplatingHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\LeadBundle\Model\LeadModel as ContactModel;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\MauticContactSourceBundle\ContactSourceEvents;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use MauticPlugin\MauticContactSourceBundle\Entity\Event as EventEntity;
use MauticPlugin\MauticContactSourceBundle\Entity\Stat;
use MauticPlugin\MauticContactSourceBundle\Event\ContactSourceEvent;
use MauticPlugin\MauticContactSourceBundle\Event\ContactSourceTimelineEvent;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Class ContactSourceModel.
 */
class ContactSourceModel extends FormModel
{
    /** @var ContainerAwareEventDispatcher */
    protected $dispatcher;

    /** @var FormModel */
    protected $formModel;

    /** @var TrackableModel */
    protected $trackableModel;

    /** @var TemplatingHelper */
    protected $templating;

    /** @var ContactModel */
    protected $contactModel;

    /**
     * ContactSourceModel constructor.
     *
     * @param \Mautic\FormBundle\Model\FormModel $formModel
     * @param TrackableModel                     $trackableModel
     * @param TemplatingHelper                   $templating
     * @param EventDispatcherInterface           $dispatcher
     * @param ContactModel                       $contactModel
     */
    public function __construct(
        \Mautic\FormBundle\Model\FormModel $formModel,
        TrackableModel $trackableModel,
        TemplatingHelper $templating,
        EventDispatcherInterface $dispatcher,
        ContactModel $contactModel
    ) {
        $this->formModel      = $formModel;
        $this->trackableModel = $trackableModel;
        $this->templating     = $templating;
        $this->dispatcher     = $dispatcher;
        $this->contactModel   = $contactModel;
    }

    /**
     * @return string
     */
    public function getActionRouteBase()
    {
        return 'contactsource';
    }

    /**
     * @param ContactSource $contactSource
     * @param array         $dateParams
     *
     * @return array|null
     */
    public function getCampaignList(ContactSource $contactSource, $dateParams = [])
    {
        if (!empty($contactSource)) {
            return $this->getCampaignsBySource($contactSource, $dateParams);
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public function getPermissionBase()
    {
        return 'plugin:contactsource:items';
    }

    /**
     * {@inheritdoc}
     *
     * @param object                              $entity
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param null                                $action
     * @param array                               $options
     *
     * @throws NotFoundHttpException
     */
    public function createForm($entity, $formFactory, $action = null, $options = [])
    {
        if (!$entity instanceof ContactSource) {
            throw new MethodNotAllowedHttpException(['ContactSource']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        // Prevent clone action from complaining about extra fields.
        $options['allow_extra_fields'] = true;

        return $formFactory->create('contactsource', $entity, $options);
    }

    /**
     * Add a stat entry.
     *
     * @param ContactSource|null $contactSource
     * @param                    $type
     * @param int                $contact
     * @param int                $attribution
     * @param int                $campaign
     */
    public function addStat(
        ContactSource $contactSource = null,
        $type = null,
        $contact = 0,
        $attribution = 0,
        $campaign = 0
    ) {
        $stat = new Stat();
        if ($contactSource) {
            $stat->setContactSource($contactSource);
        }
        $stat->setDateAdded(new \DateTime());
        $stat->setType($type);
        if ($contact) {
            $stat->setContact($contact);
        }
        if ($attribution) {
            $stat->setAttribution($attribution);
        }
        if ($campaign) {
            $stat->setCampaign($campaign);
        }

        $this->getStatRepository()->saveEntity($stat);
    }

    /**
     * {@inheritdoc}
     *
     * @return \MauticPlugin\MauticContactSourceBundle\Entity\StatRepository
     */
    public function getStatRepository()
    {
        return $this->em->getRepository('MauticContactSourceBundle:Stat');
    }

    /**
     * Add transactional log in contactsource_events.
     *
     * @param ContactSource $contactSource
     * @param               $type
     * @param null          $contact
     * @param null          $logs
     * @param null          $message
     */
    public function addEvent(
        ContactSource $contactSource = null,
        $type = null,
        $contact = null,
        $logs = null,
        $message = null
    ) {
        $event = new EventEntity();
        $event->setContactSource($contactSource)
            ->setDateAdded(new \DateTime())
            ->setType($type);
        if ($contact) {
            $event->setContact($contact);
        }
        if (!$logs) {
            // [ENG-418] Exception report - logs can not be null.
            $logs = '';
        }
        $event->setLogs($logs);

        if ($message) {
            $event->setMessage($message);
        }

        $this->getEventRepository()->saveEntity($event);
    }

    /**
     * {@inheritdoc}
     *
     * @return \MauticPlugin\MauticContactSourceBundle\Entity\EventRepository
     */
    public function getEventRepository()
    {
        return $this->em->getRepository('MauticContactSourceBundle:Event');
    }

    /**
     * @param ContactSource  $contactSource
     * @param                $unit
     * @param \DateTime|null $dateFrom
     * @param \DateTime|null $dateTo
     * @param campaignId|null $
     * @param null $dateFormat
     * @param bool $canViewOthers
     *
     * @return array
     */
    public function getStats(
        ContactSource $contactSource,
        $unit,
        \DateTime $dateFrom = null,
        \DateTime $dateTo = null,
        $campaignId = null,
        $dateFormat = null,
        $canViewOthers = true
    ) {
        $unit           = (null === $unit) ? $this->getTimeUnitFromDateRange($dateFrom, $dateTo) : $unit;
        $dateToAdjusted = clone $dateTo;
        if (in_array($unit, ['H', 'i', 's'])) {
            // draw the chart with the correct intervals for intra-day
            $dateToAdjusted->setTime(23, 59, 59);
        }
        $chart = new LineChart($unit, $dateFrom, $dateToAdjusted, $dateFormat);
        $query = new ChartQuery($this->em->getConnection(), $dateFrom, $dateToAdjusted, $unit);

        $params = ['contactsource_id' => $contactSource->getId()];

        if (isset($campaignId) && !empty($campaignId)) {
            $params['campaign_id'] = (int) $campaignId;
        }

        $stat = new Stat();
        foreach ($stat->getAllTypes() as $type) {
            $params['type'] = $type;
            $q              = $query->prepareTimeDataQuery(
                'contactsource_stats',
                'date_added',
                $params
            );

            if (!in_array($unit, ['H', 'i', 's'])) {
                // For some reason, Mautic only sets UTC in Query Date builder
                // if its an intra-day date range ¯\_(ツ)_/¯
                // so we have to do it here.
                $userTZ        = new \DateTime('now');
                $userTzName    = $userTZ->getTimezone()->getName();
                $paramDateTo   = $q->getParameter('dateTo');
                $paramDateFrom = $q->getParameter('dateFrom');
                $paramDateTo   = new \DateTime($paramDateTo);
                $paramDateTo->setTimeZone(new \DateTimeZone('UTC'));
                $q->setParameter('dateTo', $paramDateTo->format('Y-m-d H:i:s'));
                $paramDateFrom = new \DateTime($paramDateFrom);
                $paramDateFrom->setTimeZone(new \DateTimeZone('UTC'));
                $q->setParameter('dateFrom', $paramDateFrom->format('Y-m-d H:i:s'));
                $select    = $q->getQueryPart('select')[0];
                $newSelect = str_replace(
                    't.date_added,',
                    "CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'),",
                    $select
                );
                $q->resetQueryPart('select');
                $q->select($newSelect);

                // AND adjust the group By, since its using db timezone Date values
                $groupBy    = $q->getQueryPart('groupBy')[0];
                $newGroupBy = str_replace(
                    't.date_added,',
                    "CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'),",
                    $groupBy
                );
                $q->resetQueryPart('groupBy');
                $q->groupBy($newGroupBy);
            }

            if (!$canViewOthers) {
                $this->limitQueryToCreator($q);
            }
            $data = $query->loadAndBuildTimeData($q);
            foreach ($data as $val) {
                if (0 !== $val) {
                    $chart->setDataset($this->translator->trans('mautic.contactsource.graph.'.$type), $data);
                    break;
                }
            }
        }

        return $chart->render();
    }

    /**
     * Returns appropriate time unit from a date range so the line/bar charts won't be too full/empty.
     *
     * @param $dateFrom
     * @param $dateTo
     *
     * @return string
     */
    public function getTimeUnitFromDateRange($dateFrom, $dateTo)
    {
        $dayDiff = $dateTo->diff($dateFrom)->format('%a');
        $unit    = 'd';

        if ($dayDiff <= 1) {
            $unit = 'H';

            $sameDay    = $dateTo->format('d') == $dateFrom->format('d') ? 1 : 0;
            $hourDiff   = $dateTo->diff($dateFrom)->format('%h');
            $minuteDiff = $dateTo->diff($dateFrom)->format('%i');
            if ($sameDay && !intval($hourDiff) && intval($minuteDiff)) {
                $unit = 'i';
            }
            $secondDiff = $dateTo->diff($dateFrom)->format('%s');
            if (!intval($minuteDiff) && intval($secondDiff)) {
                $unit = 'm';
            }
        }
        if ($dayDiff > 31) {
            $unit = 'W';
        }
        if ($dayDiff > 100) {
            $unit = 'm';
        }
        if ($dayDiff > 1000) {
            $unit = 'Y';
        }

        return $unit;
    }

    /**
     * Joins the email table and limits created_by to currently logged in user.
     *
     * @param QueryBuilder $q
     */
    public function limitQueryToCreator(QueryBuilder $q)
    {
        $q->join('t', MAUTIC_TABLE_PREFIX.'contactsource', 'm', 'e.id = t.contactsource_id')
            ->andWhere('m.created_by = :userId')
            ->setParameter('userId', $this->userHelper->getUser()->getId());
    }

    /**
     * @param ContactSource  $contactSource
     * @param                $unit
     * @param                $type
     * @param \DateTime|null $dateFrom
     * @param \DateTime|null $dateTo
     * @param null           $dateFormat
     * @param bool           $canViewOthers
     *
     * @return array
     */
    public function getStatsByCampaign(
        ContactSource $contactSource,
        $unit,
        $type,
        \DateTime $dateFrom = null,
        \DateTime $dateTo = null,
        $campaignId = null,
        $dateFormat = null,
        $canViewOthers = true
    ) {
        $unit           = (null === $unit) ? $this->getTimeUnitFromDateRange($dateFrom, $dateTo) : $unit;
        $dateToAdjusted = clone $dateTo;

        $userTZ     = new \DateTime('now');
        $userTzName = $userTZ->getTimezone()->getName();

        if (in_array($unit, ['H', 'i', 's'])) {
            // draw the chart with the correct intervals for intra-day
            $dateToAdjusted->setTime(23, 59, 59);
        }
        $chart = new LineChart($unit, $dateFrom, $dateToAdjusted, $dateFormat);
        $query = new ChartQuery($this->em->getConnection(), $dateFrom, $dateToAdjusted, $unit);

        if (isset($campaignId) && !empty($campaignId)) {
            $campaign    = $this->em->getRepository('MauticCampaignBundle:Campaign')->getEntity($campaignId);
            $campaigns[] = ['campaign_id' => $campaign->getId(), 'name' => $campaign->getName()];
        } else {
            $campaigns = $this->getCampaignsBySource(
                $contactSource,
                ['dateTo' => $dateToAdjusted, 'dateFrom' => $dateFrom]
            );
        }

        if ('cost' != $type) {
            foreach ($campaigns as $campaign) {
                $q = $query->prepareTimeDataQuery(
                    'contactsource_stats',
                    'date_added',
                    [
                        'contactsource_id' => $contactSource->getId(),
                        'type'             => $type,
                        'campaign_id'      => $campaign['campaign_id'],
                    ]
                );

                if (!in_array($unit, ['H', 'i', 's'])) {
                    // For some reason, Mautic only sets UTC in Query Date builder
                    // if its an intra-day date range ¯\_(ツ)_/¯
                    // so we have to do it here.
                    $paramDateTo   = $q->getParameter('dateTo');
                    $paramDateFrom = $q->getParameter('dateFrom');
                    $paramDateTo   = new \DateTime($paramDateTo);
                    $paramDateTo->setTimeZone(new \DateTimeZone('UTC'));
                    $q->setParameter('dateTo', $paramDateTo->format('Y-m-d H:i:s'));
                    $paramDateFrom = new \DateTime($paramDateFrom);
                    $paramDateFrom->setTimeZone(new \DateTimeZone('UTC'));
                    $q->setParameter('dateFrom', $paramDateFrom->format('Y-m-d H:i:s'));
                    $select    = $q->getQueryPart('select')[0];
                    $newSelect = str_replace(
                        't.date_added,',
                        "CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'),",
                        $select
                    );
                    $q->resetQueryPart('select');
                    $q->select($newSelect);

                    // AND adjust the group By, since its using db timezone Date values
                    $groupBy    = $q->getQueryPart('groupBy')[0];
                    $newGroupBy = str_replace(
                        't.date_added,',
                        "CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'),",
                        $groupBy
                    );
                    $q->resetQueryPart('groupBy');
                    $q->groupBy($newGroupBy);
                }

                if (!$canViewOthers) {
                    $this->limitQueryToCreator($q);
                }
                $data = $query->loadAndBuildTimeData($q);
                foreach ($data as $val) {
                    if (0 !== $val) {
                        if (empty($campaign['name'])) {
                            $campaign['name'] = 'No Campaign';
                        }
                        $chart->setDataset($campaign['name'], $data);
                        break;
                    }
                }
            }
        } else {
            // Revenue has a different scale and data source so do it as a one off

            $dbUnit        = $query->getTimeUnitFromDateRange($dateFrom, $dateTo);
            $dbUnit        = $query->translateTimeUnit($dbUnit);
            $dateConstruct = "DATE_FORMAT(CONVERT_TZ(t.date_added, @@global.time_zone, '$userTzName'), '$dbUnit.')";
            foreach ($campaigns as $key => $campaign) {
                $q = $query->prepareTimeDataQuery(
                    'contactsource_stats',
                    'date_added',
                    [
                        'contactsource_id' => $contactSource->getId(),
                        'type'             => Stat::TYPE_ACCEPTED,
                    ]
                );
                if (!$canViewOthers) {
                    $this->limitQueryToCreator($q);
                }
                $q->select($dateConstruct.' AS date, ROUND(SUM(t.attribution) * -1, 2) AS count')
                    ->andWhere('campaign_id= :campaign_id'.$key)
                    ->setParameter('campaign_id'.$key, $campaign['campaign_id'])
                    ->groupBy($dateConstruct);
                $data = $query->loadAndBuildTimeData($q);
                foreach ($data as $val) {
                    if (0 !== $val) {
                        if (empty($campaign['name'])) {
                            $campaign['name'] = 'No Campaign';
                        }
                        $chart->setDataset($campaign['name'], $data);
                        break;
                    }
                }
            }
        }

        return $chart->render();
    }

    /**
     * @param ContactSource $contactSource
     * @param array         $dateParams
     *
     * @return array
     */
    private function getCampaignsBySource(ContactSource $contactSource, $dateParams = [])
    {
        $id = $contactSource->getId();

        $q = $this->em->getConnection()->createQueryBuilder()
            ->from(MAUTIC_TABLE_PREFIX.'contactsource_stats', 'cs')
            ->select('DISTINCT(cs.campaign_id), c.name');

        $q->where(
            $q->expr()->eq('cs.contactsource_id', ':contactSourceId')
        )
            ->setParameter('contactSourceId', $id);

        $default  = $this->dispatcher->getContainer()->get('mautic.helper.core_parameters')->getParameter(
            'default_daterange_filter',
            'midnight -1 month'
        );
        $dateTo   = isset($dateParams['dateTo']) && !empty($dateParams['dateTo']) ? $dateParams['dateTo']->setTime(23, 59, 59) : new \DateTime(
            'midnight -1 second'
        );
        $dateFrom = isset($dateParams['dateFrom']) && !empty($dateParams['dateFrom']) ? $dateParams['dateFrom']->setTime(00, 00, 00) : new \DateTime(
            $default
        );

        $q->andWhere('cs.date_added BETWEEN FROM_UNIXTIME(:dateFrom) AND FROM_UNIXTIME(:dateTo)')
            ->setParameter('dateFrom', $dateFrom->getTimestamp(), \PDO::PARAM_INT)
            ->setParameter('dateTo', $dateTo->getTimestamp(), \PDO::PARAM_INT);

        $q->join('cs', MAUTIC_TABLE_PREFIX.'campaigns', 'c', 'cs.campaign_id = c.id');
        $q->orderBY('c.name', 'ASC');

        $result =  $q->execute()->fetchAll();

        return $result;
    }

    /**
     * Get timeline/engagement data.
     *
     * @param ContactSource|null $contactSource
     * @param array              $filters
     * @param null               $orderBy
     * @param int                $page
     * @param int                $limit
     * @param bool               $forTimeline
     *
     * @return array
     */
    public function getEngagements(
        ContactSource $contactSource = null,
        $filters = [],
        $orderBy = null,
        $page = 1,
        $limit = 25,
        $forTimeline = true
    ) {
        $orderBy = empty($orderBy) ? ['date_added', 'DESC'] : $orderBy;
        $session = $this->dispatcher->getContainer()->get('session');

        if (null === $filters || empty($filters)) {
            $sourcechartFilters = $session->get('mautic.contactsource.'.$contactSource->getId().'.sourcechartfilter');

            $dateFrom     = new \DateTime($sourcechartFilters['date_from']);
            $dateFrom->setTime(00, 00, 00); // set to beginning of day, Timezone should be OK.

            $dateTo       = new \DateTime($sourcechartFilters['date_to']);
            $dateTo->setTime(23, 59, 59);

            $filters      = [
                'dateFrom'   => $dateFrom,
                'dateTo'     => $dateTo,
                'type'       => $sourcechartFilters['type'],
            ];
            if (isset($sourcechartFilters['campaign']) && !empty($sourcechartFilters['campaign'])) {
                $filters['campaignId'] = $sourcechartFilters['campaign'];
            }
        }

        $event   = $this->dispatcher->dispatch(
            ContactSourceEvents::TIMELINE_ON_GENERATE,
            new ContactSourceTimelineEvent(
                $contactSource,
                $filters,
                $orderBy,
                $page,
                $limit,
                $forTimeline,
                $this->coreParametersHelper->getParameter('site_url')
            )
        );
        $payload = [
            'events'   => $event->getEvents(),
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => $event->getEventTypes(),
            'total'    => $event->getQueryTotal(),
            'page'     => $page,
            'limit'    => $limit,
            'maxPages' => $event->getMaxPage(),
        ];

        return ($forTimeline) ? $payload : [$payload, $event->getSerializerGroups()];
    }

    /**
     * @return array
     */
    public function getEngagementTypes()
    {
        $event = new ContactSourceTimelineEvent();
        $event->fetchTypesOnly();

        $this->dispatcher->dispatch(ContactSourceEvents::TIMELINE_ON_GENERATE, $event);

        return $event->getEventTypes();
    }

    /**
     * Get engagement counts by time unit.
     *
     * @param Contact         $contact
     * @param \DateTime|null  $dateFrom
     * @param \DateTime|null  $dateTo
     * @param string          $unit
     * @param ChartQuery|null $chartQuery
     *
     * @return array
     */
    public function getEngagementCount(
        Contact $contact,
        \DateTime $dateFrom = null,
        \DateTime $dateTo = null,
        $unit = 'm',
        ChartQuery $chartQuery = null
    ) {
        $event = new ContactSourceTimelineEvent($contact);
        $event->setCountOnly($dateFrom, $dateTo, $unit, $chartQuery);

        $this->dispatcher->dispatch(ContactSourceEvents::TIMELINE_ON_GENERATE, $event);

        return $event->getEventCounter();
    }

    /**
     * Evaluate all limits (budgets/caps) for a source, return them by campaign.
     *
     * @param ContactSource $contactSource
     *
     * @return array
     *
     * @throws \Exception
     * @throws \MauticPlugin\MauticContactSourceBundle\Exception\ContactSourceException
     */
    public function evaluateAllCampaignLimits(ContactSource $contactSource)
    {
        $container = $this->dispatcher->getContainer();
        /** @var CampaignSettings $campaignSettingsModel */
        $campaignModel         = $container->get('mautic.campaign.model.campaign');
        $campaignSettingsModel = $container->get('mautic.contactsource.model.campaign_settings');
        $campaignSettingsModel->setContactSource($contactSource);
        $campaignSettingsAll = $campaignSettingsModel->getCampaignSettings();

        $campaignLimits = [];
        if (!empty($campaignSettingsAll->campaigns)) {
            /* @var \MauticPlugin\MauticContactSourceBundle\Model\Cache $cacheModel */
            $cacheModel = $container->get('mautic.contactsource.model.cache');
            $cacheModel->setContactSource($contactSource);

            foreach ($campaignSettingsAll->campaigns as $campaign) {
                // Establish parameters from campaign settings.
                if (!empty($campaign->limits) && isset($campaign->campaignId)) {
                    $id                = intval($campaign->campaignId);
                    $name              = $campaignModel->getEntity($id)->getName();
                    $limitRules        = new \stdClass();
                    $limitRules->rules = $campaign->limits;
                    $limits            = $cacheModel->evaluateLimits($limitRules, $id, false, true);
                    if ($limits) {
                        $campaignLimits[] = [
                            'campaignId' => $id,
                            'limits'     => $limits,
                            'name'       => $name,
                            'link'       => $this->buildUrl(
                                'mautic_campaign_action',
                                ['objectAction' => 'view', 'objectId' => $id]
                            ),
                        ];
                    }
                }
            }
        }

        return $campaignLimits;
    }

    /**
     * Evaluate all limits (budgets/caps) for a source, return them by campaign.
     *
     * @param ContactSource $contactSource
     *
     * @return array
     *
     * @throws \Exception
     * @throws \MauticPlugin\MauticContactSourceBundle\Exception\ContactSourceException
     */
    public function evaluateAllSourceLimits($campaignId)
    {
        $campaignLimits = [];
        $sources        = $this->getRepository()->getSourcesByCampaign($campaignId);

        $container = $this->dispatcher->getContainer();
        /** @var CampaignSettings $campaignSettingsModel */
        $campaignSettingsModel = $container->get('mautic.contactsource.model.campaign_settings');

        foreach ($sources as $source) {
            $sourceEntity = $this->getEntity($source['id']);
            $campaignSettingsModel->setContactSource($sourceEntity);
            $campaignSettings = $campaignSettingsModel->getCampaignSettingsById($campaignId);

            /* @var \MauticPlugin\MauticContactSourceBundle\Model\Cache $cacheModel */
            $cacheModel = $container->get('mautic.contactsource.model.cache');
            $cacheModel->reset();
            $cacheModel->setContactSource($sourceEntity);

            foreach ($campaignSettings as $campaign) {
                // Establish parameters from campaign settings.
                if (!empty($campaign->limits) && isset($campaign->campaignId)) {
                    $id                = intval($campaign->campaignId);
                    $limitRules        = new \stdClass();
                    $limitRules->rules = $campaign->limits;
                    $limits            = $cacheModel->evaluateLimits($limitRules, $id, false, true);
                    if ($limits) {
                        $campaignLimits[] = [
                            'sourceId' => $source['id'],
                            'limits'   => $limits,
                            'name'     => $source['name'],
                            'link'     => $this->buildUrl(
                                'mautic_contactsource_action',
                                ['objectAction' => 'view', 'objectId' => $source['id']]
                            ),
                        ];
                    }
                } else {
                    $limitsPlaceholder = [
                        0 => [
                            'name' => 'Unlimited',
                        ],
                    ];
                    $campaignLimits[]  = [
                        'sourceId' => $source['id'],
                        'limits'   => $limitsPlaceholder,
                        'name'     => $source['name'],
                        'link'     => $this->buildUrl(
                            'mautic_contactsource_action',
                            ['objectAction' => 'view', 'objectId' => $source['id']]
                        ),
                    ];
                }
            }
        }

        return $campaignLimits;
    }

    /**
     * {@inheritdoc}
     *
     * @param null $id
     *
     * @return ContactSource
     */
    public function getEntity($id = null)
    {
        if (null === $id) {
            $entity           = new ContactSource();
            $defaultUtmSource = $this->getRepository()->getDefaultUTMSource();
            $entity->setUtmSource($defaultUtmSource);

            return $entity;
        }

        if ('clone' == $this->dispatcher->getContainer()->get('request')->attributes->get('objectAction')) {
            $entity           = parent::getEntity($id);
            $defaultUtmSource = $this->getRepository()->getDefaultUTMSource();
            $entity->setUtmSource($defaultUtmSource);

            return $entity;
        }

        return parent::getEntity($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param ContactSource $entity
     * @param bool|false    $unlock
     */
    public function saveEntity($entity, $unlock = true)
    {
        parent::saveEntity($entity, $unlock);

        $this->getRepository()->saveEntity($entity);
    }

    /**
     * {@inheritdoc}
     *
     * @return \MauticPlugin\MauticContactSourceBundle\Entity\ContactSourceRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository('MauticContactSourceBundle:ContactSource');
    }

    /**
     * {@inheritdoc}
     *
     * @return bool|ContactSourceEvent
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null)
    {
        if (!$entity instanceof ContactSource) {
            throw new MethodNotAllowedHttpException(['ContactSource']);
        }

        switch ($action) {
            case 'pre_save':
                $name = ContactSourceEvents::PRE_SAVE;
                break;
            case 'post_save':
                $name = ContactSourceEvents::POST_SAVE;
                break;
            case 'pre_delete':
                $name = ContactSourceEvents::PRE_DELETE;
                break;
            case 'post_delete':
                $name = ContactSourceEvents::POST_DELETE;
                break;
            default:
                return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new ContactSourceEvent($entity, $isNew);
                $event->setEntityManager($this->em);
            }

            $this->dispatcher->dispatch($name, $event);

            return $event;
        } else {
            return null;
        }
    }
}
