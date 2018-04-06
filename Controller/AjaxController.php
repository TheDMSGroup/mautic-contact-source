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
     * Get the current Campaign Limits (for real-time updates)
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
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

}
