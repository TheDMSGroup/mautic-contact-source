<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Controller;

use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;
use MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel;

/**
 * Trait ContactSourceDetailsTrait.
 */
trait ContactSourceDetailsTrait
{
    /**
     * @param array      $contactSources
     * @param array|null $filters
     * @param array|null $orderBy
     * @param int        $page
     * @param int        $limit
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getAllEngagements(
        array $contactSources,
        array $filters = null,
        array $orderBy = null,
        $page = 1,
        $limit = 25
    ) {
        $session = $this->get('session');

        if (null == $filters) {
            $filters = $session->get(
                'mautic.plugin.timeline.filters',
                [
                    'search'        => '',
                    'includeEvents' => [],
                    'excludeEvents' => [],
                ]
            );
        }
        $filters = $this->sanitizeEventFilter(InputHelper::clean($this->request->get('filters', [])));

        if (null == $orderBy) {
            if (!$session->has('mautic.plugin.timeline.orderby')) {
                $session->set('mautic.plugin.timeline.orderby', 'timestamp');
                $session->set('mautic.plugin.timeline.orderbydir', 'DESC');
            }

            $orderBy = [
                $session->get('mautic.plugin.timeline.orderby'),
                $session->get('mautic.plugin.timeline.orderbydir'),
            ];
        }

        // prepare result object
        $result = [
            'events'   => [],
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => [],
            'total'    => 0,
            'page'     => $page,
            'limit'    => $limit,
            'maxPages' => 0,
        ];

        // get events for each contact
        foreach ($contactSources as $contactSource) {
            //  if (!$contactSource->getEmail()) continue; // discard contacts without email

            /** @var ContactSourceModel $model */
            $model       = $this->getModel('contactSource');
            $engagements = $model->getEngagements($contactSource, $filters, $orderBy, $page, $limit);
            $events      = $engagements['events'];
            $types       = $engagements['types'];

            // inject contactSource into events
            foreach ($events as &$event) {
                $event['contactSourceId']    = $contactSource->getId();
                $event['contactSourceEmail'] = $contactSource->getEmail();
                $event['contactSourceName']  = $contactSource->getName() ? $contactSource->getName(
                ) : $contactSource->getEmail();
            }

            $result['events'] = array_merge($result['events'], $events);
            $result['types']  = array_merge($result['types'], $types);
            $result['total'] += $engagements['total'];
        }

        $result['maxPages'] = ($limit <= 0) ? 1 : round(ceil($result['total'] / $limit));

        usort($result['events'], [$this, 'cmp']); // sort events by

        // now all events are merged, let's limit to   $limit
        array_splice($result['events'], $limit);

        $result['total'] = count($result['events']);

        return $result;
    }

    /**
     * Makes sure that the event filter array is in the right format.
     *
     * @param mixed $filters
     *
     * @return array
     *
     * @throws InvalidArgumentException if not an array
     */
    public function sanitizeEventFilter($filters)
    {
        if (!is_array($filters)) {
            throw new \InvalidArgumentException('filters parameter must be an array');
        }

        if (!isset($filters['search'])) {
            $filters['search'] = '';
        }

        if (!isset($filters['includeEvents'])) {
            $filters['includeEvents'] = [];
        }

        if (!isset($filters['excludeEvents'])) {
            $filters['excludeEvents'] = [];
        }

        return $filters;
    }

    /**
     * Get a list of places for the contactSource based on IP location.
     *
     * @param ContactSource $contactSource
     *
     * @return array
     */
    protected function getPlaces(ContactSource $contactSource)
    {
        // Get Places from IP addresses
        $places = [];
        if ($contactSource->getIpAddresses()) {
            foreach ($contactSource->getIpAddresses() as $ip) {
                if ($details = $ip->getIpDetails()) {
                    if (!empty($details['latitude']) && !empty($details['longitude'])) {
                        $name = 'N/A';
                        if (!empty($details['city'])) {
                            $name = $details['city'];
                        } elseif (!empty($details['region'])) {
                            $name = $details['region'];
                        }
                        $place    = [
                            'latLng' => [$details['latitude'], $details['longitude']],
                            'name'   => $name,
                        ];
                        $places[] = $place;
                    }
                }
            }
        }

        return $places;
    }

    /**
     * @param ContactSource  $contactSource
     * @param \DateTime|null $fromDate
     * @param \DateTime|null $toDate
     *
     * @return mixed
     */
    protected function getEngagementData(
        ContactSource $contactSource,
        \DateTime $fromDate = null,
        \DateTime $toDate = null
    ) {
        $translator = $this->get('translator');

        if (null == $fromDate) {
            $fromDate = new \DateTime('first day of this month 00:00:00');
            $fromDate->modify('-6 months');
        }
        if (null == $toDate) {
            $toDate = new \DateTime();
        }

        $lineChart  = new LineChart(null, $fromDate, $toDate);
        $chartQuery = new ChartQuery($this->getDoctrine()->getConnection(), $fromDate, $toDate);

        /** @var ContactSourceModel $model */
        $model       = $this->getModel('contactSource');
        $engagements = $model->getEngagementCount($contactSource, $fromDate, $toDate, 'm', $chartQuery);
        $lineChart->setDataset(
            $translator->trans('mautic.contactSource.graph.line.all_engagements'),
            $engagements['byUnit']
        );

        $pointStats = $chartQuery->fetchTimeData(
            'contactSource_points_change_log',
            'date_added',
            ['contactSource_id' => $contactSource->getId()]
        );
        $lineChart->setDataset($translator->trans('mautic.contactSource.graph.line.points'), $pointStats);

        return $lineChart->render();
    }

    /**
     * @param ContactSource $contactSource
     * @param array|null    $filters
     * @param array|null    $orderBy
     * @param int           $page
     * @param int           $limit
     *
     * @return array
     */
    protected function getAuditlogs(
        ContactSource $contactSource,
        array $filters = null,
        array $orderBy = null,
        $page = 1,
        $limit = 25
    ) {
        $session = $this->get('session');

        if (null == $filters) {
            $filters = $session->get(
                'mautic.contactSource.'.$contactSource->getId().'.auditlog.filters',
                [
                    'search'        => '',
                    'includeEvents' => [],
                    'excludeEvents' => [],
                ]
            );
        }

        if (null == $orderBy) {
            if (!$session->has('mautic.contactSource.'.$contactSource->getId().'.auditlog.orderby')) {
                $session->set('mautic.contactSource.'.$contactSource->getId().'.auditlog.orderby', 'al.dateAdded');
                $session->set('mautic.contactSource.'.$contactSource->getId().'.auditlog.orderbydir', 'DESC');
            }

            $orderBy = [
                $session->get('mautic.contactSource.'.$contactSource->getId().'.auditlog.orderby'),
                $session->get('mautic.contactSource.'.$contactSource->getId().'.auditlog.orderbydir'),
            ];
        }

        // Audit Log
        /** @var AuditLogModel $auditlogModel */
        $auditlogModel = $this->getModel('core.auditLog');

        $logs     = $auditlogModel->getLogForObject(
            'contactsource',
            $contactSource->getId(),
            $contactSource->getDateAdded()
        );
        $logCount = count($logs);

        $types = [
            'delete'     => $this->translator->trans('mautic.contactSource.event.delete'),
            'create'     => $this->translator->trans('mautic.contactSource.event.create'),
            'identified' => $this->translator->trans('mautic.contactSource.event.identified'),
            'ipadded'    => $this->translator->trans('mautic.contactSource.event.ipadded'),
            'merge'      => $this->translator->trans('mautic.contactSource.event.merge'),
            'update'     => $this->translator->trans('mautic.contactSource.event.update'),
        ];

        return [
            'events'   => $logs,
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => $types,
            'total'    => $logCount,
            'page'     => $page,
            'limit'    => $limit,
            'maxPages' => ceil($logCount / $limit),
        ];
    }

    /**
     * @param ContactSource $contactSource
     * @param array|null    $filters
     * @param array|null    $orderBy
     * @param int           $page
     * @param int           $limit
     *
     * @return array
     */
    protected function getEngagements(
        ContactSource $contactSource,
        array $filters = null,
        array $orderBy = null,
        $page = 1,
        $limit = 25
    ) {
        $session = $this->get('session');

        if (null == $filters) {
            $filters = $session->get(
                'mautic.contactSource.'.$contactSource->getId().'.timeline.filters',
                [
                    'search'        => '',
                    'includeEvents' => [],
                    'excludeEvents' => [],
                ]
            );
        }

        if (null == $orderBy) {
            if (!$session->has('mautic.contactSource.'.$contactSource->getId().'.timeline.orderby')) {
                $session->set('mautic.contactSource.'.$contactSource->getId().'.timeline.orderby', 'timestamp');
                $session->set('mautic.contactSource.'.$contactSource->getId().'.timeline.orderbydir', 'DESC');
            }

            $orderBy = [
                $session->get('mautic.contactSource.'.$contactSource->getId().'.timeline.orderby'),
                $session->get('mautic.contactSource.'.$contactSource->getId().'.timeline.orderbydir'),
            ];
        }
        /** @var ContactSourceModel $model */
        $model = $this->getModel('contactSource');

        return $model->getEngagements($contactSource, $filters, $orderBy, $page, $limit);
    }

    /**
     * @param ContactSource $contactSource
     *
     * @return array
     */
    protected function getScheduledCampaignEvents(ContactSource $contactSource)
    {
        // Upcoming events from Campaign Bundle
        /** @var \Mautic\CampaignBundle\Entity\ContactSourceEventLogRepository $contactSourceEventLogRepository */
        $contactSourceEventLogRepository = $this->getDoctrine()->getManager()->getRepository(
            'MauticCampaignBundle:ContactSourceEventLog'
        );

        return $contactSourceEventLogRepository->getUpcomingEvents(
            [
                'contactSource' => $contactSource,
                'eventType'     => ['action', 'condition'],
            ]
        );
    }

    /**
     * @param $a
     * @param $b
     *
     * @return int
     */
    private function cmp($a, $b)
    {
        if ($a['timestamp'] === $b['timestamp']) {
            return 0;
        }

        return ($a['timestamp'] < $b['timestamp']) ? +1 : -1;
    }
}
