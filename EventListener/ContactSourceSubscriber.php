<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\EventListener;

use Mautic\AssetBundle\Helper\TokenHelper as AssetTokenHelper;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\FormBundle\Helper\TokenHelper as FormTokenHelper;
use Mautic\PageBundle\Helper\TokenHelper as PageTokenHelper;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\MauticContactSourceBundle\ContactSourceEvents;
use MauticPlugin\MauticContactSourceBundle\Entity\EventRepository;
use MauticPlugin\MauticContactSourceBundle\Entity\Stat;
use MauticPlugin\MauticContactSourceBundle\Event\ContactSourceEvent;
use MauticPlugin\MauticContactSourceBundle\Event\ContactSourceTimelineEvent;
use MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ContactSourceSubscriber.
 */
class ContactSourceSubscriber extends CommonSubscriber
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var IpLookupHelper
     */
    protected $ipHelper;

    /**
     * @var AuditLogModel
     */
    protected $auditLogModel;

    /**
     * @var TrackableModel
     */
    protected $trackableModel;

    /**
     * @var PageTokenHelper
     */
    protected $pageTokenHelper;

    /**
     * @var AssetTokenHelper
     */
    protected $assetTokenHelper;

    /**
     * @var FormTokenHelper
     */
    protected $formTokenHelper;

    /**
     * @var ContactSourceModel
     */
    protected $contactsourceModel;

    /** @var PageModel */
    protected $pageModel;

    /**
     * ContactSourceSubscriber constructor.
     *
     * @param RouterInterface    $router
     * @param IpLookupHelper     $ipLookupHelper
     * @param AuditLogModel      $auditLogModel
     * @param TrackableModel     $trackableModel
     * @param PageTokenHelper    $pageTokenHelper
     * @param AssetTokenHelper   $assetTokenHelper
     * @param FormTokenHelper    $formTokenHelper
     * @param ContactSourceModel $contactsourceModel
     */
    public function __construct(
        RouterInterface $router,
        IpLookupHelper $ipLookupHelper,
        AuditLogModel $auditLogModel,
        TrackableModel $trackableModel,
        PageTokenHelper $pageTokenHelper,
        AssetTokenHelper $assetTokenHelper,
        FormTokenHelper $formTokenHelper,
        ContactSourceModel $contactsourceModel
    ) {
        $this->router             = $router;
        $this->ipHelper           = $ipLookupHelper;
        $this->auditLogModel      = $auditLogModel;
        $this->trackableModel     = $trackableModel;
        $this->pageTokenHelper    = $pageTokenHelper;
        $this->assetTokenHelper   = $assetTokenHelper;
        $this->formTokenHelper    = $formTokenHelper;
        $this->contactsourceModel = $contactsourceModel;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ContactSourceEvents::POST_SAVE            => ['onContactSourcePostSave', 0],
            ContactSourceEvents::POST_DELETE          => ['onContactSourceDelete', 0],
            ContactSourceEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    /**
     * Add an entry to the audit log.
     *
     * @param ContactSourceEvent $event
     */
    public function onContactSourcePostSave(ContactSourceEvent $event)
    {
        $entity = $event->getContactSource();
        if ($details = $event->getChanges()) {
            $log = [
                'bundle'    => 'contactsource',
                'object'    => 'contactsource',
                'objectId'  => $entity->getId(),
                'action'    => ($event->isNew()) ? 'create' : 'update',
                'details'   => $details,
                'ipAddress' => $this->ipHelper->getIpAddressFromRequest(),
            ];
            $this->auditLogModel->writeToLog($log);
        }
    }

    /**
     * Add a delete entry to the audit log.
     *
     * @param ContactSourceEvent $event
     */
    public function onContactSourceDelete(ContactSourceEvent $event)
    {
        $entity = $event->getContactSource();
        $log    = [
            'bundle'    => 'contactsource',
            'object'    => 'contactsource',
            'objectId'  => $entity->deletedId,
            'action'    => 'delete',
            'details'   => ['name' => $entity->getName()],
            'ipAddress' => $this->ipHelper->getIpAddressFromRequest(),
        ];
        $this->auditLogModel->writeToLog($log);
    }

    /**
     * Compile events for the lead timeline.
     *
     * @param ContactSourceTimelineEvent $event
     */
    public function onTimelineGenerate(ContactSourceTimelineEvent $event)
    {
        // Set available event types
        // $event->addSerializerGroup(['formList', 'submissionEventDetails']);

        /** @var EventRepository $eventRepository */
        $eventRepository = $this->em->getRepository('MauticContactSourceBundle:Event');

        $stat    = new Stat();
        $types   = $stat->getAllTypes();
        $options = $event->getQueryOptions();
        foreach ($types as $eventTypeKey) {
            $eventTypeName = ucwords($eventTypeKey);
            if (!$event->isApplicable($eventTypeKey)) {
                continue;
            }
            $event->addEventType($eventTypeKey, $eventTypeName);
        }

        $rows = $eventRepository->getEvents($options['contactSourceId']);
        foreach ($rows as $row) {
            $eventTypeKey  = $row['type'];
            $eventTypeName = ucwords($eventTypeKey);

            // Add total to counter
            $event->addToCounter($eventTypeKey, 1);

            if (!$event->isEngagementCount()) {
//                if (!$this->pageModel) {
//                    $this->pageModel = new PageModel();
//                }

                $event->addEvent(
                    [
                        'event'           => $eventTypeKey,
                        'eventId'         => $eventTypeKey.$row['id'],
                        'eventLabel'      => [
                            'label' => $eventTypeName,
                            'href'  => $this->router->generate(
                                'mautic_form_action',
                                ['objectAction' => 'view', 'objectId' => $row['id']]
                            ),
                        ],
                        'eventType'       => $eventTypeName,
                        'timestamp'       => $row['date_added'],
                        'extra'           => [
                            // 'page' => $this->pageModel->getEntity($row['page_id']),
                            'logs'                => $row['logs'],
                            'integrationEntityId' => $row['integration_entity_id'],
                        ],
                        'contentTemplate' => 'MauticContactSourceBundle:SubscribedEvents\Timeline:index.html.php',
                        'icon'            => 'fa-plus-square-o',
                        'message'         => $row['message'],
                        'contactId'       => $row['contact_id'],
                    ]
                );
            }
        }
    }
}
