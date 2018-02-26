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

use Doctrine\DBAL\Query\QueryBuilder;
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

        return $formFactory->create('contactsource', $entity, $options);
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
            return new ContactSource();
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
     * Add a stat entry.
     *
     * @param ContactSource $contactSource
     * @param               $type
     * @param null|Contact  $contact
     * @param int           $attribution
     */
    public function addStat(ContactSource $contactSource, $type, $contact = null, $attribution = 0)
    {
        $stat = new Stat();
        $stat->setContactSource($contactSource)
            ->setDateAdded(new \DateTime())
            ->setType($type)
            ->setAttribution($attribution);
        if ($contact) {
            $stat->setContact($contact);
        }
        if ($attribution) {
            $stat->setAttribution($attribution);
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
     * @param null          $integration_entity_id
     */
    public function addEvent(
        ContactSource $contactSource,
        $type,
        $contact = null,
        $logs = null,
        $message = null,
        $integration_entity_id = null
    ) {
        $event = new EventEntity();
        $event->setContactSource($contactSource)
            ->setDateAdded(new \DateTime())
            ->setType($type);
        if ($contact) {
            $event->setContact($contact);
        }
        if ($logs) {
            $event->setLogs($logs);
        }
        if ($message) {
            $event->setMessage($message);
        }
        if ($integration_entity_id) {
            $event->setIntegrationEntityId($integration_entity_id);
        }

        $this->getEventRepository()->saveEntity($event);
    }

    /**
     * {@inheritdoc}
     *
     * @return \MauticPlugin\MauticContactSourceBundle\Entity\StatRepository
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
     * @param null           $dateFormat
     * @param bool           $canViewOthers
     *
     * @return array
     */
    public function getStats(
        ContactSource $contactSource,
        $unit,
        \DateTime $dateFrom = null,
        \DateTime $dateTo = null,
        $dateFormat = null,
        $canViewOthers = true
    ) {
        $chart = new LineChart($unit, $dateFrom, $dateTo, $dateFormat);
        $query = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo, $unit);

        $q = $query->prepareTimeDataQuery(
            'contactsource_stats',
            'date_added',
            ['contactsource_id' => $contactSource->getId()]
        );
        if (!$canViewOthers) {
            $this->limitQueryToCreator($q);
        }
        $stat = new Stat();
        foreach ($stat->getAllTypes() as $type) {
            $q = $query->prepareTimeDataQuery('contactsource_stats', 'date_added', ['type' => $type]);
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

        // Add attribution to the chart.
        // @todo - This should really be in it's own chart in the future.
        $q = $query->prepareTimeDataQuery('contactsource_stats', 'date_added', ['type' => Stat::TYPE_ACCEPT]);
        if (!$canViewOthers) {
            $this->limitQueryToCreator($q);
        }
        $dbUnit        = $query->getTimeUnitFromDateRange($dateFrom, $dateTo);
        $dbUnit        = $query->translateTimeUnit($dbUnit);
        $dateConstruct = 'DATE_FORMAT(t.date_added, \''.$dbUnit.'\')';
        $q->select($dateConstruct.' AS date, ROUND(SUM(t.attribution), 2) AS count')
            ->groupBy($dateConstruct);
        $data = $query->loadAndBuildTimeData($q);
        foreach ($data as $val) {
            if (0 !== $val) {
                $chart->setDataset($this->translator->trans('mautic.contactsource.graph.attribution'), $data);
                break;
            }
        }

        return $chart->render();
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
        $event = $this->dispatcher->dispatch(
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

        if (!isset($filters['search'])) {
            $filters['search'] = null;
        }
        $payload = [
            'events'   => $event->getEvents(),
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => $event->getEventTypes(),
            'total'    => $event->getEventCounter()['total'],
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
