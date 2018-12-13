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
use Mautic\CoreBundle\Helper\InputHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $contactSource = $this->checkContactSourceAccess($contactSourceId, 'view');
        if ($contactSource instanceof Response) {
            return $contactSource;
        }
        // send a stream csv file of the timeline
        $name        = 'ContactSourceTransactionsExport';
        $headers     = [
            'type',
            'date_added',
            'message',
            'contact_id',
            'log',
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
                while ($params['start'] < $count[0]['count']) {
                    $timelineData = $eventRepository->getEventsForTimelineExport(
                        $contactSource->getId(),
                        $params,
                        false
                    );
                    foreach ($timelineData as $data) {
                        fputcsv($handle, array_values($data));
                    }
                    $params['start'] += $params['limit'];
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
}
