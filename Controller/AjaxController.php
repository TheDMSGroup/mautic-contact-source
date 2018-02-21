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
use Mautic\CoreBundle\Helper\InputHelper;
use Symfony\Component\HttpFoundation\Request;
use MauticPlugin\MauticContactSourceBundle\Integration\SourceIntegration;

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
     * @throws \Exception
     */
    protected function getCampaignListAction()
    {
        $output = [];
        /** @var CampaignRepository */
        $campaignRepository = $this->get('mautic.contactsource.model.campaign')->getRepository();
        $campaigns = $campaignRepository->getEntities();
        foreach ($campaigns as $campaign) {
            $published = $campaign->isPublished();
            $name = $campaign->getName();
            $category = $campaign->getCategory();
            $id = $campaign->getId();
            $output[$name.'_'.$category.'_'.$id] = [
                'value' => $id,
                'title' => $name.($category ? ' - '.$category : '').(!$published ? ' (unpublished)' : ''),
            ];
        }
        // Sort by name and category if not already, then drop the keys.
        ksort($output);

        return $this->sendJsonResponse(
            [
                'array' => array_values($output),
            ]
        );
    }
}
