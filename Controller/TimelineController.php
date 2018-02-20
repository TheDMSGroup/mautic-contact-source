<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Helper\InputHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TimelineController
 * @package MauticPlugin\MauticContactSourceBundle\Controller
 */
class TimelineController extends CommonController
{
    use ContactSourceAccessTrait;
    use ContactSourceDetailsTrait;

    public function indexAction(Request $request, $contactSourceId, $page = 1)
    {
        if (empty($contactSourceId)) {
            return $this->accessDenied();
        }

        $contactSource = $this->checkContactSourceAccess($contactSourceId, 'view');
        if ($contactSource instanceof Response) {
            return $contactSource;
        }

        $this->setListFilters();

        $session = $this->get('session');
        if ($request->getMethod() == 'POST' && $request->request->has('search')) {
            $filters = [
                'search' => InputHelper::clean($request->request->get('search')),
                'includeEvents' => InputHelper::clean($request->request->get('includeEvents', [])),
                'excludeEvents' => InputHelper::clean($request->request->get('excludeEvents', [])),
            ];
            $session->set('mautic.contactSource.'.$contactSourceId.'.timeline.filters', $filters);
        } else {
            $filters = null;
        }

        $order = [
            $session->get('mautic.contactSource.'.$contactSourceId.'.timeline.orderby'),
            $session->get('mautic.contactSource.'.$contactSourceId.'.timeline.orderbydir'),
        ];

        $events = $this->getEngagements($contactSource, $filters, $order, $page);

        return $this->delegateView(
            [
                'viewParameters' => [
                    'contactSource' => $contactSource,
                    'page' => $page,
                    'events' => $events,
                ],
                'passthroughVars' => [
                    'route' => false,
                    'mauticContent' => 'contactSourceTimeline',
                    'timelineCount' => $events['total'],
                ],
                'contentTemplate' => 'MauticContactSourceBundle:Timeline:list.html.php',
            ]
        );
    }

    public function pluginIndexAction(Request $request, $integration, $page = 1)
    {
        $limit = 25;
        $contactSources = $this->checkAllAccess('view', $limit);

        if ($contactSources instanceof Response) {
            return $contactSources;
        }

        $this->setListFilters();

        $session = $this->get('session');
        if ($request->getMethod() === 'POST' && $request->request->has('search')) {
            $filters = [
                'search' => InputHelper::clean($request->request->get('search')),
                'includeEvents' => InputHelper::clean($request->request->get('includeEvents', [])),
                'excludeEvents' => InputHelper::clean($request->request->get('excludeEvents', [])),
            ];
            $session->set('mautic.plugin.timeline.filters', $filters);
        } else {
            $filters = null;
        }

        $order = [
            $session->get('mautic.plugin.timeline.orderby'),
            $session->get('mautic.plugin.timeline.orderbydir'),
        ];

        // get all events grouped by contactSource
        $events = $this->getAllEngagements($contactSources, $filters, $order, $page, $limit);

        $str = $this->request->source->get('QUERY_STRING');
        parse_str($str, $query);

        $tmpl = 'table';
        if (array_key_exists('from', $query) && 'iframe' === $query['from']) {
            $tmpl = 'list';
        }
        if (array_key_exists('tmpl', $query)) {
            $tmpl = $query['tmpl'];
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'contactSources' => $contactSources,
                    'page' => $page,
                    'events' => $events,
                    'integration' => $integration,
                    'tmpl' => (!$this->request->isXmlHttpRequest()) ? 'index' : '',
                    'newCount' => (array_key_exists('count', $query) && $query['count']) ? $query['count'] : 0,
                ],
                'passthroughVars' => [
                    'route' => false,
                    'mauticContent' => 'pluginTimeline',
                    'timelineCount' => $events['total'],
                ],
                'contentTemplate' => sprintf('MauticContactSourceBundle:Timeline:plugin_%s.html.php', $tmpl),
            ]
        );
    }

    public function pluginViewAction(Request $request, $integration, $contactSourceId, $page = 1)
    {
        if (empty($contactSourceId)) {
            return $this->notFound();
        }

        $contactSource = $this->checkContactSourceAccess($contactSourceId, 'view', true, $integration);
        if ($contactSource instanceof Response) {
            return $contactSource;
        }

        $this->setListFilters();

        $session = $this->get('session');
        if ($request->getMethod() === 'POST' && $request->request->has('search')) {
            $filters = [
                'search' => InputHelper::clean($request->request->get('search')),
                'includeEvents' => InputHelper::clean($request->request->get('includeEvents', [])),
                'excludeEvents' => InputHelper::clean($request->request->get('excludeEvents', [])),
            ];
            $session->set('mautic.plugin.timeline.'.$contactSourceId.'.filters', $filters);
        } else {
            $filters = null;
        }

        $order = [
            $session->get('mautic.plugin.timeline.'.$contactSourceId.'.orderby'),
            $session->get('mautic.plugin.timeline.'.$contactSourceId.'.orderbydir'),
        ];

        $events = $this->getEngagements($contactSource, $filters, $order, $page);

        $str = $this->request->source->get('QUERY_STRING');
        parse_str($str, $query);

        $tmpl = 'table';
        if (array_key_exists('from', $query) && 'iframe' === $query['from']) {
            $tmpl = 'list';
        }
        if (array_key_exists('tmpl', $query)) {
            $tmpl = $query['tmpl'];
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'contactSource' => $contactSource,
                    'page' => $page,
                    'integration' => $integration,
                    'events' => $events,
                    'newCount' => (array_key_exists('count', $query) && $query['count']) ? $query['count'] : 0,
                ],
                'passthroughVars' => [
                    'route' => false,
                    'mauticContent' => 'pluginTimeline',
                    'timelineCount' => $events['total'],
                ],
                'contentTemplate' => sprintf('MauticContactSourceBundle:Timeline:plugin_%s.html.php', $tmpl),
            ]
        );
    }

    /**
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\StreamedResponse
     *
     * @todo - Needs refactoring to function.
     */
    public function batchExportAction(Request $request, $contactSourceId)
    {
        if (empty($contactSourceId)) {
            return $this->accessDenied();
        }

        $contactSource = $this->checkContactSourceAccess($contactSourceId, 'view');
        if ($contactSource instanceof Response) {
            return $contactSource;
        }

        $this->setListFilters();

        $session = $this->get('session');
        if ($request->getMethod() == 'POST' && $request->request->has('search')) {
            $filters = [
                'search' => InputHelper::clean($request->request->get('search')),
                'includeEvents' => InputHelper::clean($request->request->get('includeEvents', [])),
                'excludeEvents' => InputHelper::clean($request->request->get('excludeEvents', [])),
            ];
            $session->set('mautic.contactSource.'.$contactSourceId.'.timeline.filters', $filters);
        } else {
            $filters = null;
        }

        $order = [
            $session->get('mautic.contactSource.'.$contactSourceId.'.timeline.orderby'),
            $session->get('mautic.contactSource.'.$contactSourceId.'.timeline.orderbydir'),
        ];

        $dataType = $this->request->get('filetype', 'csv');

        $resultsCallback = function ($event) {
            $eventLabel = (isset($event['eventLabel'])) ? $event['eventLabel'] : $event['eventType'];
            if (is_array($eventLabel)) {
                $eventLabel = $eventLabel['label'];
            }

            return [
                'eventName' => $eventLabel,
                'eventType' => isset($event['eventType']) ? $event['eventType'] : '',
                'eventTimestamp' => $this->get('mautic.helper.template.date')->toText(
                    $event['timestamp'],
                    'local',
                    'Y-m-d H:i:s',
                    true
                ),
            ];
        };

        $results = $this->getEngagements($contactSource, $filters, $order, 1, 200);
        $count = $results['total'];
        $items = $results['events'];
        $iterations = ceil($count / 200);
        $loop = 1;

        // Max of 50 iterations for 10K result export
        if ($iterations > 50) {
            $iterations = 50;
        }

        $toExport = [];

        while ($loop <= $iterations) {
            if (is_callable($resultsCallback)) {
                foreach ($items as $item) {
                    $toExport[] = $resultsCallback($item);
                }
            } else {
                foreach ($items as $item) {
                    $toExport[] = (array)$item;
                }
            }

            $items = $this->getEngagements($contactSource, $filters, $order, $loop + 1, 200);

            $this->getDoctrine()->getManager()->clear();

            ++$loop;
        }

        return $this->exportResultsAs($toExport, $dataType, 'contact_timeline');
    }
}
