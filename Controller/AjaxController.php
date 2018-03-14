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

/**
 * Class AjaxController.
 */
class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

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
