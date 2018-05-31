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

use Mautic\CampaignBundle\Entity\CampaignRepository;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Helper\UTF8Helper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AjaxController.
 */
class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function ajaxTimelineAction(Request $request)
    {
        $filters = [];
        /** @var \MauticPlugin\MauticContactSourceBundle\Model\ContactSourceModel $contactSourceModel */
        $contactSourceModel = $this->get('mautic.contactsource.model.contactsource');

        foreach ($request->request->get('filters') as $key => $filter) {
            $filter['name']           = str_replace(
                '[]',
                '',
                $filter['name']
            ); // the serializeArray() js method seems to add [] to the key ???
            $filters[$filter['name']] = $filter['value'];
        }
        if (isset($filters['contactSourceId'])) {
            if (!$contactSource = $contactSourceModel->getEntity($filters['contactSourceId'])) {
                throw new \InvalidArgumentException('Contact Source argument is Invalid.');
            }
        } else {
            throw new \InvalidArgumentException('Contact Source argument is Missing.');
        }
        $orderBy = isset($filters['orderBy']) ? explode(':', $filters['orderBy']) : null;
        $page    = isset($filters['page']) ? $filters['page'] : 1;
        $limit   = isset($filters['limit']) ? $filters['limit'] : 25;

        $events = $contactSourceModel->getEngagements($contactSource, $filters, $orderBy, $page, $limit, true);
        $view   = $this->render(
            'MauticContactSourceBundle:Timeline:list.html.php',
            [
                'events'        => $events,
                'contactSource' => $contactSource,
                'tmpl'          => '',
            ]
        );

        return $view;
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
                'title'     => $name.($category ? '  ('.$category.')' : '').(!$published ? '  (unpublished)' : ''),
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
        $params['campaignId'] = $this->request->request->get('data')['campaignId'];
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
                        if ($limit['rule']['duration'] == 'P1D' && $limit['logCount'] > 0) {
                            $pending = floatval(
                                ($limit['logCount'] / $forecast['elapsedHoursInDaySoFar']) * $forecast['hoursLeftToday']
                            );
                        }

                        if ($limit['rule']['duration'] == '1M' && $limit['logCount'] > 0) {
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
}
