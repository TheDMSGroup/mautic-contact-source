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

use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Helper\UTF8Helper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AjaxController.
 */
class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;
    use ContactSourceAccessTrait;
    use ContactSourceDetailsTrait;

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Exception
     */
    public function ajaxTimelineAction(Request $request)
    {
        $dataArray = [
            'html'    => '',
            'success' => 0,
        ];
        $filters   = [];
        /** @var \MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel $contactSourceModel */
        $contactSourceModel = $this->get('mautic.contactsource.model.contactsource');

        // filters means the transaction table had a column sort, filter submission or pagination, otherwise its a fresh page load
        if ($request->request->has('filters')) {
            foreach ($request->request->get('filters') as $filter) {
                if (in_array($filter['name'], ['dateTo', 'dateFrom']) && !empty($filter['value'])) {
                    $filter['value']        = new \DateTime($filter['value']);
                    list($hour, $min, $sec) = 'dateTo' == $filter['name'] ? [23, 59, 59] : [00, 00, 00];
                    $filter['value']->setTime($hour, $min, $sec);
                }
                if (!empty($filter['value'])) {
                    $filters[$filter['name']] = $filter['value'];
                }
            }
        }
        $page     = isset($filters['page']) && !empty($filters['page']) ? $filters['page'] : 1;
        $objectId = InputHelper::clean($request->request->get('objectId'));
        if (empty($objectId)) {
            return $this->sendJsonResponse($dataArray);
        }

        $contactSource = $contactSourceModel->getEntity($objectId);

        $order = [
            'date_added',
            'DESC',
        ];
        if (isset($filters['orderby']) && !empty($filters['orderby'])) {
            $order[0] = $filters['orderby'];
        }
        if (isset($filters['orderbydir']) && !empty($filters['orderbydir'])) {
            $order[1] = $filters['orderbydir'];
        }
        $transactions         = $contactSourceModel->getEngagements($contactSource, $filters, $order, $page);
        $dataArray['html']    = $this->renderView(
            'MauticContactSourceBundle:Timeline:list.html.php',
            [
                'page'                => $page,
                'contactSource'       => $contactSource,
                'transactions'        => $transactions,
                'order'               => $order,
            ]
        );
        $dataArray['success'] = 1;
        $dataArray['total']   = $transactions['total'];

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * Get the current Campaign Limits (for real-time updates).
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Exception
     * @throws \MauticPlugin\MauticContactSourceBundle\Exception\ContactSourceException
     */
    public function getCampaignLimitsAction(Request $request)
    {
        $contactSourceId = $request->request->get('contactSourceId');

        /** @var \MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel $contactSourceModel */
        $contactSourceModel = $this->get('mautic.contactsource.model.contactsource');
        if (!$contactSourceId || !$contactSource = $contactSourceModel->getEntity($contactSourceId)) {
            throw new \InvalidArgumentException('Contact Source argument is Invalid.');
        }

        $limits = $contactSourceModel->evaluateAllCampaignLimits($contactSource);

        return $this->sendJsonResponse(
            [
                'array' => $limits,
            ]
        );
    }

    /**
     * Retrieve a list of campaigns for use in drop-downs.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Exception
     */
    protected function getCampaignListAction()
    {
        $output = [];
        /** @var CampaignRepository */
        $campaignRepository = $this->get('mautic.campaign.model.campaign')->getRepository();
        $campaigns          = $campaignRepository->getEntities();
        foreach ($campaigns as $campaign) {
            $id                                  = $campaign->getId();
            $published                           = $campaign->isPublished();
            $name                                = $campaign->getName();
            $category                            = $campaign->getCategory();
            $category                            = $category ? $category->getName() : '';
            $output[$name.'_'.$category.'_'.$id] = [
                'category'  => $category,
                'published' => $published,
                'name'      => $name,
                'title'     => htmlspecialchars_decode($name.($category ? '  ('.$category.')' : '').(!$published ? '  (unpublished)' : '')),
                'value'     => $id,
            ];
        }
        $output['   '] = [
            'value' => 0,
            'title' => count($output) ? '-- Select a Campaign --' : '-- Please create a Campaign --',
        ];
        // Sort by name and category if not already, then drop the keys.
        ksort($output);

        return $this->sendJsonResponse(
            [
                'array' => array_values($output),
            ]
        );
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Exception
     */
    protected function campaignBudgetsAction(Request $request)
    {
        // Get the API payload to test.
        $params['campaignId'] = $this->request->request->get('campaignId');
        $params['dateFrom']   = new \DateTime('now');
        $em                   = $this->container->get('doctrine.orm.entity_manager');
        $statRepo             = $em->getRepository(\MauticPlugin\MauticContactSourceBundle\Entity\Stat::class);
        $data                 = $statRepo->getCampaignBudgetsData($params);
        $headers              = [
            'mautic.contactsource.campaign.budgets.header.source',
            'mautic.contactsource.campaign.budgets.header.cap_name',
            'mautic.contactsource.campaign.budgets.header.today',
            'mautic.contactsource.campaign.budgets.header.daily_cap',
            'mautic.contactsource.campaign.budgets.header.daily_reached',
            'mautic.contactsource.campaign.budgets.header.mtd',
            'mautic.contactsource.campaign.budgets.header.monthly_cap',
            'mautic.contactsource.campaign.budgets.header.monthly_reached',
        ];
        foreach ($headers as $header) {
            $data['columns'][] = [
                'title' => $this->translator->trans($header),
            ];
        }
        $data = UTF8Helper::fixUTF8($data);

        return $this->sendJsonResponse($data);
    }

    protected function campaignBudgetsTabAction(Request $request)
    {
        //calculate time since values for generating forecasts
        $campaignId                         = $request->request->get('campaignId');
        $container                          = $this->dispatcher->getContainer();
        $timezone                           = $container->get('mautic.helper.core_parameters')->getParameter(
            'default_timezone'
        );
        $now                                = new \DateTime('now', new \DateTimezone($timezone));
        $midnight                           = new \DateTime('midnight', new \DateTimezone($timezone));
        $dateDiff                           = $now->diff($midnight);
        $forecast                           = [];
        $forecast['elapsedHoursInDaySoFar'] = $dateDiff->h;
        $forecast['hoursLeftToday']         = 24 - $forecast['elapsedHoursInDaySoFar'];
        $forecast['currentDayOfMonth']      = intval($now->format('d'));
        $forecast['daysInMonthLeft']        = intval($now->format('t')) - $forecast['currentDayOfMonth'];
        $limits                             = $container->get(
            'mautic.contactsource.model.contactsource'
        )->evaluateAllSourceLimits($campaignId);

        $view = $this->render(
            'MauticContactSourceBundle:Tabs:events.html.php',
            [
                'limits'   => $limits,
                'forecast' => $forecast,
                'group'    => 'source',
            ]
        );

        return $view;
    }

    protected function campaignBudgetsDashboardAction(Request $request)
    {
        //calculate time since values for generating forecasts

        $container                          = $this->dispatcher->getContainer();
        $timezone                           = $container->get('mautic.helper.core_parameters')->getParameter(
            'default_timezone'
        );
        $now                                = new \DateTime('now', new \DateTimezone($timezone));
        $midnight                           = new \DateTime('midnight', new \DateTimezone($timezone));
        $dateDiff                           = $now->diff($midnight);
        $forecast                           = [];
        $forecast['elapsedHoursInDaySoFar'] = $dateDiff->h;
        $forecast['hoursLeftToday']         = 24 - $forecast['elapsedHoursInDaySoFar'];
        $forecast['currentDayOfMonth']      = intval($now->format('d'));
        $forecast['daysInMonthLeft']        = intval($now->format('t')) - $forecast['currentDayOfMonth'];

        $data = [];
        //get all published campaigns and get limits for each
        $campaigns = $container->get(
            'mautic.campaign.model.campaign'
        )->getPublishedCampaigns(true);
        foreach ($campaigns as $campaign) {
            $limits = $container->get(
                'mautic.contactsource.model.contactsource'
            )->evaluateAllSourceLimits($campaign['id']);
            if (!empty($limits)) {
                foreach ($limits as $campaignLimits) {
                    foreach ($campaignLimits['limits'] as $limit) {
                        $row           = [];
                        $pending       = 0;
                        $forecastValue = $leadForecast = '';
                        if ('P1D' == $limit['rule']['duration'] && $limit['logCount'] > 0) {
                            $pending = floatval(
                                ($limit['logCount'] / $forecast['elapsedHoursInDaySoFar']) * $forecast['hoursLeftToday']
                            );
                        }

                        if ('1M' == $limit['rule']['duration'] && $limit['logCount'] > 0) {
                            $pending = floatval(
                                ($limit['logCount'] / $forecast['currentDayOfMonth']) * $forecast['daysInMonthLeft']
                            );
                        }
                        if (!empty($pending)) {
                            $forecastValue = number_format(
                                    ($pending + $limit['logCount']) / $limit['rule']['quantity'],
                                    2,
                                    '.',
                                    ','
                                ) * 100;
                            $forecastValue = $forecastValue.'%';
                            $leadForecast  = intval($pending + $limit['logCount']);
                        }

                        $row[]          = $forecastValue >= 90 ? 'fa-exclamation-triangle' : 'fa-heartbeat'; //status
                        $row[]          = $campaign['name']; //campaignName
                        $row[]          = $container->get(
                            'mautic.contactsource.model.contactsource'
                        )->buildUrl(
                            'mautic_campaign_action',
                            ['objectAction' => 'view', 'objectId' => $campaign['id']]
                        ); // campaingLink
                        $row[]          = $campaignLimits['name']; // source
                        $row[]          = $campaignLimits['link']; //sourceLink
                        $row[]          = $limit['name']; //description
                        $row[]          = $limit['logCount']; //capCount
                        $row[]          = $limit['percent']; // % reached
                        $row[]          = $leadForecast; // projection
                        $row[]          = $forecastValue; //capPercent
                        $data['rows'][] = $row;
                    }
                }
            }
        }

        $headers = [
            'mautic.campaign.source.limit.status',
            'mautic.campaign.source.limit.campaign',
            'campaignLink',
            'mautic.campaign.source.limit.source',
            'sourceLink',
            'mautic.campaign.source.limit.description',
            'mautic.campaign.source.limit.cap_count',
            'mautic.campaign.source.limit.cap_percent',
            'mautic.campaign.source.limit.projection',
            'forecastPercent',
        ];
        foreach ($headers as $header) {
            $data['columns'][] = [
                'title' => $this->translator->trans($header),
            ];
        }
        $data = UTF8Helper::fixUTF8($data);

        return $this->sendJsonResponse($data);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *
     * @throws \Exception
     */
    public function confirmUtmSourceAction($request)
    {
        $utmSource          = $request->request->get('utmSource');
        $contactSourceModel = $this->get('mautic.contactsource.model.contactsource');
        $utmSourceExists    = $contactSourceModel->getRepository()->findBy(['utmSource' => $utmSource]);

        return $this->sendJsonResponse(!empty($utmSourceExists) ? true : false);
    }
}
