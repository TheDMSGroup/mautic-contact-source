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

use Mautic\CoreBundle\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Class TimelineController.
 */
class TimelineController extends CommonController
{
    use ContactSourceAccessTrait;
    use ContactSourceDetailsTrait;

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

        if (!$this->get('mautic.security')->isAdmin() && $this->get('mautic.security')->isGranted('contactsource:export:disable')) {
            return $this->accessDenied();
        }

        $contactSource = $this->checkContactSourceAccess($contactSourceId, 'view');
        if ($contactSource instanceof Response) {
            return $contactSource;
        }
        // send a stream csv file of the timeline
        $name        = 'ContactSourceTransactionsExport';
        $headers     = [
            'id',
            'type',
            'date_added',
            'message',
            'contact_id',
            'campaign_id',
            'campaign_name',
            'realTime',
            'scrubbed',
            'utmSource',
            'event_id',
            'event_client',
        ];
        $session     = $this->get('session');
        $chartFilter = $session->get('mautic.contactsource.'.$contactSource->getId().'.sourcechartfilter');
        $params      = [
            'dateTo'     => new \DateTime($chartFilter['date_to']),
            'dateFrom'   => new \DateTime($chartFilter['date_from']),
            'campaignId' => $chartFilter['campaign'],
            'message'    => !empty($request->query->get('message')) ? $request->query->get('message') : null,
            'type'       => !empty($request->query->get('type')) ? $request->query->get('type') : null,
            'contact_id' => !empty($request->query->get('contact_id')) ? $request->query->get('contact_id') : null,
            'start'      => 0,
            'limit'      => 1000,  // batch limit, not total limit
        ];
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->getDoctrine()->getEntityManager()->getRepository(
            'MauticContactSourceBundle:Event'
        );
        $count           = $eventRepository->getEventsForTimelineExport($contactSource->getId(), $params, true);
        ini_set('max_execution_time', 0);
        $response = new StreamedResponse();
        $response->setCallback(
            function () use ($params, $headers, $contactSource, $count, $eventRepository) {
                $handle = fopen('php://output', 'w+');
                fputcsv($handle, $headers);
                $iterator = 0;
                while ($iterator < $count[0]['count']) {
                    $timelineData = $eventRepository->getEventsForTimelineExport(
                        $contactSource->getId(),
                        $params,
                        false
                    );
                    foreach ($timelineData as $data) {
                        // depracating use of YAML for event logs, but need to be backward compatible
                        $csvRows = '{' === $data['logs'][0] ?
                            $this->parseLogJSONBlob(
                                $data
                            ) :
                            $this->parseLogYAMLBlob(
                                $data
                            );
                        // a single data row can be multiple operations and subsequent rows
                        foreach ($csvRows as $csvRow) {
                            fputcsv($handle, array_values($csvRow));
                        }
                    }
                    $iterator        = $iterator + $params['limit'];
                    $params['start'] = $data['id'];
                }
                fclose($handle);
            }
        );
        $fileName = $name.'.csv';
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'application/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$fileName.'"');

        return $response;
    }

    /**
     * @param $data
     *
     * @return array
     */
    private function parseLogJSONBlob($data)
    {
        $json = json_decode($data['logs'], true);
        unset($data['logs']);
        $rows    = [];
        $columns = [
            'realTime',
            'scrubbed',
            'utmSource',
        ];

        if (!empty($json)) {
            $data['campaign_id']   = isset($json['campaign']['id']) ? $json['campaign']['id'] : '';
            $data['campaign_name'] = isset($json['campaign']['name']) ? $json['campaign']['name'] : '';

            foreach ($columns as $column) {
                $data[$column] = isset($json[$column]) ? var_export($json[$column], true) : '';
            }

            foreach ($json['events'] as $id => $event) {
                $row = $data;

                $row['event_id']        = isset($event['id']) ? $event['id'] : '';
                $row['event_client']    = isset($event['contactClientName']) ? $event['contactClientName'] : '';
                $rows[$id]              = $row;
            }
        } else {
            $rows[0] = $data;
        }

        return $rows;
    }

    /**
     * @param $data
     *
     * @return array
     */
    private function parseLogYAMLBlob($data)
    {
        $yaml = Yaml::parse($data['logs']);
        unset($data['logs']);
        $rows    = [];
        $columns = [
            'realtime',
            'scrubbed',
            'utmSource',
        ];

        if (!empty($yaml)) {
            $data['campaign_id']   = isset($yaml['campaign']['id']) ? $yaml['campaign']['id'] : '';
            $data['campaign_name'] = isset($yaml['campaign']['name']) ? $yaml['campaign']['name'] : '';

            foreach ($columns as $column) {
                $data[$column] = isset($yaml[$column]) ? var_export($yaml[$column], true) : '';
            }

            foreach ($yaml['events'] as $event) {
                $row = $data;

                $row['event_id']     = isset($event['id']) ? $event['id'] : '';
                $row['event_client'] = isset($event['contactClientName']) ? $event['contactClientName'] : '';
                $rows[]              = $row;
            }
        } else {
            $rows[0] = $data;
        }

        return $rows;
    }
}
